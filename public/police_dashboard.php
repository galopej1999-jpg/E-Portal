<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userRole = $_SESSION['role'] ?? null;
$allowedRoles = ['police_staff', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied. Only authorized Police personnel can access this module.");
}

$userId = $_SESSION['user_id'];

// Get Dashboard Statistics
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// Total police blotter cases this month
$totalCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                WHERE stage = 'police_blotter' AND DATE(created_at) BETWEEN :start AND :end");
$totalCasesStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$totalCasesMonth = $totalCasesStmt->fetch()['total'];

// Active cases (FILED, PENDING_BARANGAY, etc. - not escalated or decided)
$activeCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                 WHERE stage = 'police_blotter' 
                                 AND status NOT IN ('escalated_to_mtc', 'decided', 'dismissed', 'closed')");
$activeCasesStmt->execute();
$activeCases = $activeCasesStmt->fetch()['total'];

// Cases escalated to MTC this month
$escalatedStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                               WHERE parent_case_id IN (SELECT id FROM cases WHERE stage = 'police_blotter')
                               AND stage = 'mtc_case'
                               AND DATE(created_at) BETWEEN :start AND :end");
$escalatedStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$escalatedMonth = $escalatedStmt->fetch()['total'];

// Pending review (not yet assigned or under investigation)
$pendingReviewStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                   WHERE stage = 'police_blotter' 
                                   AND status IN ('FILED', 'PENDING_BARANGAY')");
$pendingReviewStmt->execute();
$pendingReview = $pendingReviewStmt->fetch()['total'];

// Get recent police blotter cases
$recentCasesStmt = $pdo->prepare("SELECT * FROM cases 
                                 WHERE stage = 'police_blotter'
                                 ORDER BY created_at DESC LIMIT 15");
$recentCasesStmt->execute();
$recentCases = $recentCasesStmt->fetchAll();

// Get cases pending review
$pendingCasesStmt = $pdo->prepare("SELECT * FROM cases 
                                  WHERE stage = 'police_blotter' 
                                  AND status IN ('FILED', 'PENDING_BARANGAY')
                                  ORDER BY created_at ASC LIMIT 10");
$pendingCasesStmt->execute();
$pendingCases = $pendingCasesStmt->fetchAll();

// Get cases ready for escalation
$readyForEscalationStmt = $pdo->prepare("SELECT * FROM cases 
                                        WHERE stage = 'police_blotter' 
                                        AND status IN ('under_investigation', 'ready_for_escalation')
                                        ORDER BY created_at ASC LIMIT 10");
$readyForEscalationStmt->execute();
$readyForEscalation = $readyForEscalationStmt->fetchAll();

// Get cases by status
$statusDistStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM cases 
                                WHERE stage = 'police_blotter' GROUP BY status");
$statusDistStmt->execute();
$statusDist = $statusDistStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get complaint types breakdown
$typeStmt = $pdo->prepare("SELECT complaint_type, COUNT(*) as count FROM cases 
                          WHERE stage = 'police_blotter' AND DATE(created_at) BETWEEN :start AND :end
                          GROUP BY complaint_type");
$typeStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$types = $typeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent activities/audit logs
$activitiesStmt = $pdo->prepare("SELECT al.*, c.case_number, u.full_name FROM audit_logs al
                                LEFT JOIN cases c ON al.case_id = c.id
                                LEFT JOIN users u ON al.user_id = u.id
                                WHERE c.stage = 'police_blotter'
                                ORDER BY al.created_at DESC LIMIT 15");
$activitiesStmt->execute();
$activities = $activitiesStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Police Dashboard - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
    <style>
        .stat-card { border-left: 4px solid #d32f2f; }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #d32f2f; }
        .stat-label { font-size: 0.95rem; color: #666; text-transform: uppercase; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h1>🚔 Police Blotter Dashboard</h1>
            <p class="text-muted">Manage cases, investigations, and escalations</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $totalCasesMonth; ?></div>
                    <div class="stat-label">Cases (This Month)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $activeCases; ?></div>
                    <div class="stat-label">Active Cases</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $pendingReview; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $escalatedMonth; ?></div>
                    <div class="stat-label">Escalated (Month)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group w-100" role="group">
                <a href="cases.php" class="btn btn-danger flex-fill">📋 View All Cases</a>
                <a href="#pending-section" class="btn btn-outline-warning flex-fill">⏳ Pending Review (<?php echo $pendingReview; ?>)</a>
                <a href="#ready-escalation-section" class="btn btn-outline-danger flex-fill">⬆️ Ready for Escalation (<?php echo count($readyForEscalation); ?>)</a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Recent Police Cases -->
        <div class="col-md-8">
            <div class="card card-court">
                <div class="card-header">
                    📋 Recent Police Blotter Cases
                </div>
                <div class="card-body">
                    <?php if (empty($recentCases)): ?>
                        <div class="alert alert-info">No cases in the system yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case #</th>
                                        <th>Complainant</th>
                                        <th>Respondent</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Filed</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCases as $case): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                                            <td><small><?php echo htmlspecialchars(substr($case['respondent_name'], 0, 15)); ?></small></td>
                                            <td><small><?php echo htmlspecialchars($case['respondent_name']); ?></small></td>
                                            <td><span class="badge bg-info"><?php echo ucfirst($case['complaint_type']); ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($case['status']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                            <td>
                                                <a href="case_view.php?id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-sm btn-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Sidebar -->
        <div class="col-md-4">
            <!-- Status Distribution -->
            <div class="card card-court mb-3">
                <div class="card-header">
                    📊 Cases by Status
                </div>
                <div class="card-body">
                    <?php if (empty($statusDist)): ?>
                        <p class="text-muted">No data available</p>
                    <?php else: ?>
                        <div style="font-size: 0.9rem;">
                            <?php foreach ($statusDist as $status => $count): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?php echo htmlspecialchars($status); ?></span>
                                    <strong><?php echo $count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complaint Types -->
            <div class="card card-court">
                <div class="card-header">
                    🏷️ Complaint Types (This Month)
                </div>
                <div class="card-body">
                    <?php if (empty($types)): ?>
                        <p class="text-muted">No data available</p>
                    <?php else: ?>
                        <div style="font-size: 0.9rem;">
                            <?php foreach ($types as $type => $count): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?php echo ucfirst(htmlspecialchars($type)); ?></span>
                                    <strong><?php echo $count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cases Pending Review -->
    <?php if (!empty($pendingCases)): ?>
    <div class="row mb-4" id="pending-section">
        <div class="col-12">
            <div class="alert alert-info">
                <strong>⏳ Cases Pending Review</strong>
                <p class="mb-0">There are <strong><?php echo count($pendingCases); ?></strong> case(s) awaiting initial review and processing.</p>
            </div>
        </div>
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    ⏳ Pending Review Cases
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Case #</th>
                                    <th>Complainant</th>
                                    <th>Respondent</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Filed Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingCases as $case): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($case['respondent_name'], 0, 20)); ?></td>
                                        <td><?php echo htmlspecialchars($case['respondent_name']); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($case['complaint_type']); ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo htmlspecialchars($case['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                        <td>
                                            <a href="case_view.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-info">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cases Ready for Escalation -->
    <?php if (!empty($readyForEscalation)): ?>
    <div class="row mb-4" id="ready-escalation-section">
        <div class="col-12">
            <div class="alert alert-warning">
                <strong>⚠️ Cases Ready for Escalation to MTC</strong>
                <p class="mb-0">There are <strong><?php echo count($readyForEscalation); ?></strong> case(s) ready to escalate to the Municipal Trial Court.</p>
            </div>
        </div>
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    ⬆️ Ready for Escalation
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Case #</th>
                                    <th>Complainant</th>
                                    <th>Respondent</th>
                                    <th>Category</th>
                                    <th>Filed Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($readyForEscalation as $case): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($case['respondent_name'], 0, 20)); ?></td>
                                        <td><?php echo htmlspecialchars($case['respondent_name']); ?></td>
                                        <td><span class="badge bg-warning"><?php echo ucfirst($case['complaint_type']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                        <td>
                                            <a href="case_view.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-warning">Review & Escalate</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    📝 Recent Activities
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted">No activities recorded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case #</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['case_number'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($activity['full_name'] ?? 'Unknown'); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($activity['action'] ?? 'N/A'); ?></span></td>
                                            <td><small><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
