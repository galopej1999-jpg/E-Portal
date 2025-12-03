<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/audit.php';

$userId  = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? null;

$allowedViewRoles = ['police_staff','mtc_staff','mtc_judge','rtc_staff','rtc_judge'];
if (!in_array($userRole, $allowedViewRoles)) {
    die("Access denied");
}

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) {
    die("Invalid document id");
}

$sql = "SELECT d.*, c.stage, c.complainant_id, c.parent_case_id
        FROM case_documents d
        JOIN cases c ON d.case_id = c.id
        WHERE d.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document not found");
}

// Helper: Get all stages in the escalation chain (both ancestors and descendants)
function getCaseEscalationChain($pdo, $caseId) {
    $stages = [];
    
    // First, walk UP to find all parent cases
    $currentId = $caseId;
    $ancestorIds = [];
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, stage, parent_case_id FROM cases WHERE id = :id");
        $stmt->execute([':id' => $currentId]);
        $row = $stmt->fetch();
        if (!$row) break;
        $stages[] = $row['stage'];
        $ancestorIds[] = $row['id'];
        $currentId = $row['parent_case_id'];
    }
    
    // Then, walk DOWN to find all child cases
    $childIds = [$caseId];
    $processed = [];
    while (!empty($childIds)) {
        $currentId = array_shift($childIds);
        if (in_array($currentId, $processed)) continue;
        $processed[] = $currentId;
        
        $stmt = $pdo->prepare("SELECT id, stage FROM cases WHERE parent_case_id = :id");
        $stmt->execute([':id' => $currentId]);
        $children = $stmt->fetchAll();
        foreach ($children as $child) {
            $stages[] = $child['stage'];
            $childIds[] = $child['id'];
        }
    }
    
    return array_unique($stages);
}

// Get the escalation chain for the document's source case
$escalationChain = getCaseEscalationChain($pdo, $doc['case_id']);

// Enforce stage-based access: allow viewing if user's stage is in the escalation chain
$userCanAccess = false;
if ($userRole === 'police_staff' && in_array('police_blotter', $escalationChain)) {
    $userCanAccess = true;
}
if (in_array($userRole, ['mtc_staff','mtc_judge']) && in_array('mtc_case', $escalationChain)) {
    $userCanAccess = true;
}
if (in_array($userRole, ['rtc_staff','rtc_judge']) && in_array('rtc_case', $escalationChain)) {
    $userCanAccess = true;
}

if (!$userCanAccess) {
    die("Access denied (document not in your jurisdiction)");
}

$storageDir = __DIR__ . '/../storage/documents/';
$fullPath   = $storageDir . $doc['stored_filename'];

if (!file_exists($fullPath)) {
    die("File missing");
}

$encryptedData = file_get_contents($fullPath);

$decryptedData = openssl_decrypt(
    $encryptedData,
    DOC_ENC_METHOD,
    DOC_ENC_KEY,
    OPENSSL_RAW_DATA,
    $doc['iv']
);

if ($decryptedData === false) {
    die("Decryption failed");
}

// Log document access
logAuditAction($pdo, $userId, 'DOCUMENT_DECRYPT', $docId, $doc['case_id'], 
    'Document: ' . $doc['original_filename']);

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . strlen($decryptedData));

echo $decryptedData;
exit;
