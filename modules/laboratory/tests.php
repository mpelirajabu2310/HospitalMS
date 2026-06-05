<?php
define('PAGE_TITLE', 'Laboratory Test Requests');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $requestId = intval($_POST['request_id'] ?? 0);

    if ($action === 'collect_sample' && $requestId) {
        Database::query(
            "UPDATE lab_requests SET status = 'sample_collected', sample_collected_by = ?, sample_collected_at = NOW() WHERE id = ? AND status = 'pending'",
            [$userId, $requestId]
        );
        logActivity($userId, 'sample_collected', 'laboratory', "Sample collected for lab request #$requestId");
        set_flash('success', 'Sample collection recorded successfully.');
    } elseif ($action === 'start_test' && $requestId) {
        Database::query(
            "UPDATE lab_requests SET status = 'in_progress' WHERE id = ? AND status = 'sample_collected'",
            [$requestId]
        );
        logActivity($userId, 'test_started', 'laboratory', "Test started for lab request #$requestId");
        set_flash('success', 'Test started successfully.');
    } elseif ($action === 'cancel' && $requestId) {
        Database::query(
            "UPDATE lab_requests SET status = 'cancelled' WHERE id = ? AND status IN ('pending','sample_collected')",
            [$requestId]
        );
        logActivity($userId, 'test_cancelled', 'laboratory', "Lab request #$requestId cancelled");
        set_flash('success', 'Lab request cancelled.');
    }
    redirect('modules/laboratory/tests.php');
}

$filterStatus = sanitize($_GET['status'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = [];
$params = [];
if ($filterStatus) {
    $where[] = 'lr.status = ?';
    $params[] = $filterStatus;
}
if ($filterPriority) {
    $where[] = 'lr.priority = ?';
    $params[] = $filterPriority;
}
if ($dateFrom) {
    $where[] = 'DATE(lr.requested_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(lr.requested_at) <= ?';
    $params[] = $dateTo;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$requests = Database::fetchAll(
    "SELECT lr.*, p.first_name as p_first_name, p.last_name as p_last_name, p.patient_number,
            lt.name as test_name, lt.code as test_code,
            u.first_name as d_first_name, u.last_name as d_last_name
     FROM lab_requests lr
     JOIN patients p ON lr.patient_id = p.id
     JOIN lab_tests lt ON lr.lab_test_id = lt.id
     JOIN users u ON lr.doctor_id = u.id
     $whereClause
     ORDER BY lr.requested_at DESC
     LIMIT 100",
    $params
);

$pending = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'pending'")['c'];
$sampleCollected = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'sample_collected'")['c'];
$inProgress = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'in_progress'")['c'];
$completedToday = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")['c'];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-flask me-2 text-primary"></i>Laboratory Test Requests</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-warning"><?= $pending ?></h3>
                <small class="text-muted">Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-info"><?= $sampleCollected ?></h3>
                <small class="text-muted">Sample Collected</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-primary"><?= $inProgress ?></h3>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-success"><?= $completedToday ?></h3>
                <small class="text-muted">Completed Today</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="sample_collected" <?= $filterStatus === 'sample_collected' ? 'selected' : '' ?>>Sample Collected</option>
                    <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All</option>
                    <option value="routine" <?= $filterPriority === 'routine' ? 'selected' : '' ?>>Routine</option>
                    <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="stat" <?= $filterPriority === 'stat' ? 'selected' : '' ?>>STAT</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Doctor</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No test requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <?php
                            $priorityBadge = match ($r['priority']) {
                                'stat' => '<span class="badge bg-danger">STAT</span>',
                                'urgent' => '<span class="badge bg-warning text-dark">Urgent</span>',
                                default => '<span class="badge bg-primary">Routine</span>',
                            };
                            ?>
                            <tr>
                                <td class="small"><?= formatDateTime($r['requested_at']) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $r['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($r['p_first_name'] . ' ' . $r['p_last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['patient_number']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['test_name']) ?></td>
                                <td class="small"><?= htmlspecialchars($r['d_first_name'] . ' ' . $r['d_last_name']) ?></td>
                                <td><?= $priorityBadge ?></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Collect sample for this request?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="collect_sample">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-info"><i class="fas fa-syringe me-1"></i>Collect Sample</button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this request?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php elseif ($r['status'] === 'sample_collected'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start_test">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-play me-1"></i>Start Test</button>
                                        </form>
                                    <?php elseif ($r['status'] === 'in_progress'): ?>
                                        <a href="<?= APP_URL ?>/modules/laboratory/results.php?request_id=<?= $r['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-flask me-1"></i>Enter Results</a>
                                    <?php elseif ($r['status'] === 'completed'): ?>
                                        <span class="text-muted small">Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
