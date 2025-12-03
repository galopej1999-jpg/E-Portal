<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userRole = $_SESSION['role'] ?? null;
$allowedRoles = ['rtc_staff', 'rtc_judge', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied. Only authorized RTC personnel can access this module.");
}

$userId = $_SESSION['user_id'];

// Get Dashboard Statistics
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// Total RTC cases this month
$totalCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                WHERE stage = 'rtc_case' AND DATE(created_at) BETWEEN :start AND :end");
$totalCasesStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$totalCasesMonth = $totalCasesStmt->fetch()['total'];

// Active cases (not decided)
$activeCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                 WHERE stage = 'rtc_case' 
                                 AND status NOT IN ('decided', 'dismissed', 'closed', 'appealed')");
$activeCasesStmt->execute();
$activeCases = $activeCasesStmt->fetch()['total'];

// Cases decided this month
$decidedStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                             WHERE stage = 'rtc_case' AND status = 'decided'
                             AND DATE(updated_at) BETWEEN :start AND :end");
$decidedStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$decidedMonth = $decidedStmt->fetch()['total'];

// Pending review (not yet assigned to judge or under deliberation)
$pendingReviewStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                   WHERE stage = 'rtc_case' 
                                   AND status IN ('escalated_to_rtc', 'filed', 'received')");
$pendingReviewStmt->execute();
$pendingReview = $pendingReviewStmt->fetch()['total'];

// Get recent RTC cases
$recentCasesStmt = $pdo->prepare("SELECT * FROM cases 
                                 WHERE stage = 'rtc_case'
                                 ORDER BY created_at DESC LIMIT 15");
$recentCasesStmt->execute();
$recentCases = $recentCasesStmt->fetchAll();

// Get cases under trial (ready for judgment)
$underTrialStmt = $pdo->prepare("SELECT * FROM cases 
                                WHERE stage = 'rtc_case' 
                                AND status IN ('under_trial', 'in_deliberation', 'ready_for_judgment')
                                ORDER BY created_at ASC LIMIT 10");
$underTrialStmt->execute();
$underTrial = $underTrialStmt->fetchAll();

// Get cases by status
$statusDistStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM cases 
                                WHERE stage = 'rtc_case' GROUP BY status");
$statusDistStmt->execute();
$statusDist = $statusDistStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get complaint types breakdown
$typeStmt = $pdo->prepare("SELECT complaint_type, COUNT(*) as count FROM cases 
                          WHERE stage = 'rtc_case' AND DATE(created_at) BETWEEN :start AND :end
                          GROUP BY complaint_type");
$typeStmt->execute([':start' => $thisMonth, ':end' => date('Y-m-t')]);
$types = $typeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent activities/audit logs
$activitiesStmt = $pdo->prepare("SELECT al.*, c.case_number, u.full_name FROM audit_logs al
                                LEFT JOIN cases c ON al.case_id = c.id
                                LEFT JOIN users u ON al.user_id = u.id
                                WHERE c.stage = 'rtc_case'
                                ORDER BY al.created_at DESC LIMIT 15");
$activitiesStmt->execute();
$activities = $activitiesStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RTC Dashboard - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
    <style>
        .stat-card { border-left: 4px solid #c41c3b; }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #c41c3b; }
        .stat-label { font-size: 0.95rem; color: #666; text-transform: uppercase; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h1>⚖️ Regional Trial Court (RTC) Dashboard</h1>
            <p class="text-muted">Manage appellate cases, trials, and final judgments</p>
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
                    <div class="stat-number"><?php echo $decidedMonth; ?></div>
                    <div class="stat-label">Decided (Month)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group w-100" role="group">
                <a href="cases.php" class="btn btn-danger flex-fill">📋 View All Cases</a>
                <a href="#pending-section" class="btn btn-outline-info flex-fill">⏳ Pending Review (<?php echo $pendingReview; ?>)</a>
                <a href="#under-trial-section" class="btn btn-outline-warning flex-fill">⚖️ Under Trial (<?php echo count($underTrial); ?>)</a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Recent RTC Cases -->
        <div class="col-md-8">
            <div class="card card-court">
                <div class="card-header">
                    📋 Recent RTC Cases
                </div>
                <div class="card-body">
                    <?php if (empty($recentCases)): ?>
                        <div class="alert alert-info">No cases assigned to RTC yet.</div>
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
                                                   class="btn btn-sm btn-danger">View</a>
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
                    🏷️ Case Types (This Month)
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

    <!-- Cases Under Trial / In Deliberation -->
    <?php if (!empty($underTrial)): ?>
    <div class="row mb-4" id="under-trial-section">
        <div class="col-12">
            <div class="alert alert-warning">
                <strong>⚖️ Cases Under Trial / In Deliberation</strong>
                <p class="mb-0">There are <strong><?php echo count($underTrial); ?></strong> case(s) currently under trial or in deliberation phase.</p>
            </div>
        </div>
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    ⚖️ Under Trial & Deliberation
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
                                <?php foreach ($underTrial as $case): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($case['case_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($case['respondent_name'], 0, 20)); ?></td>
                                        <td><?php echo htmlspecialchars($case['respondent_name']); ?></td>
                                        <td><span class="badge bg-warning"><?php echo ucfirst($case['complaint_type']); ?></span></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($case['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($case['created_at'])); ?></td>
                                        <td>
                                            <a href="case_view.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-danger">Review & Judge</a>
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
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($activity['action_type']); ?></span></td>
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
