<?php
define('PAGE_TITLE', 'Nursing Tasks');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$isAdmin = Auth::isAdmin() || Auth::hasPermission('manage_nursing');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task' && $isAdmin) {
        $patientId = intval($_POST['patient_id']);
        $taskType = sanitize($_POST['task_type']);
        $description = sanitize($_POST['description']);
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $assignedTo = intval($_POST['assigned_to']);
        $dueDate = sanitize($_POST['due_date'] ?? '') ?: null;
        $notes = sanitize($_POST['notes'] ?? '');

        if ($patientId && $description && $assignedTo) {
            Database::insert(
                "INSERT INTO nursing_tasks (patient_id, assigned_by, assigned_to, task_type, description, priority, due_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$patientId, $userId, $assignedTo, $taskType, $description, $priority, $dueDate, $notes]
            );
            $patient = Database::fetch("SELECT first_name, last_name FROM patients WHERE id = ?", [$patientId]);
            $assigner = Auth::user()['first_name'] . ' ' . Auth::user()['last_name'];
            createNotification($assignedTo, 'task_assigned', 'New Nursing Task',
                "Task: $description for {$patient['first_name']} {$patient['last_name']} (assigned by $assigner)",
                APP_URL . '/modules/nursing/tasks.php', 'nursing_task', Database::getInstance()->getConnection()->lastInsertId()
            );
            logActivity($userId, 'task_created', 'nursing', "Nursing task created for patient #$patientId");
            set_flash('success', 'Nursing task created and assigned.');
        } else {
            set_flash('error', 'Please fill all required fields.', 'warning');
        }
        redirect('modules/nursing/tasks.php');
    }

    if ($action === 'update_status') {
        $taskId = intval($_POST['task_id']);
        $newStatus = sanitize($_POST['status']);
        $task = Database::fetch("SELECT * FROM nursing_tasks WHERE id = ?", [$taskId]);
        if ($task && in_array($newStatus, ['in_progress', 'completed', 'cancelled'])) {
            $updates = "status = ?";
            $params = [$newStatus];
            if ($newStatus === 'completed') {
                $updates .= ", completed_at = NOW()";
            }
            $params[] = $taskId;
            Database::query("UPDATE nursing_tasks SET $updates WHERE id = ?", $params);

            $patient = Database::fetch("SELECT first_name, last_name FROM patients WHERE id = ?", [$task['patient_id']]);
            createNotification($task['assigned_by'], 'task_updated', 'Task ' . ucfirst($newStatus),
                "Task \"{$task['description']}\" for {$patient['first_name']} {$patient['last_name']} was marked as $newStatus",
                APP_URL . '/modules/nursing/tasks.php', 'nursing_task', $taskId
            );
            logActivity($userId, 'task_status_changed', 'nursing', "Task #$taskId status -> $newStatus");
            set_flash('success', 'Task status updated.');
        }
        redirect('modules/nursing/tasks.php');
    }
}

$filterStatus = sanitize($_GET['status'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');
$filterType = sanitize($_GET['task_type'] ?? '');

$where = [];
$params = [];
if (!$isAdmin) {
    $where[] = "t.assigned_to = ?";
    $params[] = $userId;
}
if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}
if ($filterPriority) {
    $where[] = "t.priority = ?";
    $params[] = $filterPriority;
}
if ($filterType) {
    $where[] = "t.task_type = ?";
    $params[] = $filterType;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$tasks = Database::fetchAll(
    "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            CONCAT(ab.first_name, ' ', ab.last_name) as assigned_by_name,
            CONCAT(ao.first_name, ' ', ao.last_name) as assigned_to_name
     FROM nursing_tasks t
     JOIN patients p ON t.patient_id = p.id
     JOIN users ab ON t.assigned_by = ab.id
     JOIN users ao ON t.assigned_to = ao.id
     $whereClause
     ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.created_at DESC",
    $params
);

$pendingCount = Database::fetch("SELECT COUNT(*) as c FROM nursing_tasks WHERE status = 'pending'" . ($isAdmin ? '' : ' AND assigned_to = ?'), $isAdmin ? [] : [$userId])['c'];
$inProgressCount = Database::fetch("SELECT COUNT(*) as c FROM nursing_tasks WHERE status = 'in_progress'" . ($isAdmin ? '' : ' AND assigned_to = ?'), $isAdmin ? [] : [$userId])['c'];
$completedToday = Database::fetch("SELECT COUNT(*) as c FROM nursing_tasks WHERE status = 'completed' AND DATE(completed_at) = CURDATE()" . ($isAdmin ? '' : ' AND assigned_to = ?'), $isAdmin ? [] : [$userId])['c'];

$patients = Database::fetchAll("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name ASC LIMIT 200");
$nurses = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('nurse', 'admin', 'super_admin') AND u.status = 'active' ORDER BY u.first_name ASC");

$priorityBadges = ['urgent' => 'danger', 'high' => 'orange', 'medium' => 'warning', 'low' => 'info'];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Nursing Tasks</h4>
    <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTaskModal">
            <i class="fas fa-plus me-1"></i>Assign Task
        </button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-warning"><?= $pendingCount ?></h3>
                <small class="text-muted">Pending Tasks</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-info"><?= $inProgressCount ?></h3>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-success"><?= $completedToday ?></h3>
                <small class="text-muted">Completed Today</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Priority</label>
                <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Task Type</label>
                <select name="task_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="medication" <?= $filterType === 'medication' ? 'selected' : '' ?>>Medication</option>
                    <option value="wound_care" <?= $filterType === 'wound_care' ? 'selected' : '' ?>>Wound Care</option>
                    <option value="vital_signs" <?= $filterType === 'vital_signs' ? 'selected' : '' ?>>Vital Signs</option>
                    <option value="catheter" <?= $filterType === 'catheter' ? 'selected' : '' ?>>Catheter</option>
                    <option value="dressing" <?= $filterType === 'dressing' ? 'selected' : '' ?>>Dressing</option>
                    <option value="monitoring" <?= $filterType === 'monitoring' ? 'selected' : '' ?>>Monitoring</option>
                    <option value="other" <?= $filterType === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100 btn-sm"><i class="fas fa-filter"></i></button>
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
                        <th>Patient</th>
                        <th>Task Type</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <?php if ($isAdmin): ?><th>Assigned To</th><?php endif; ?>
                        <th>Assigned By</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="<?= $isAdmin ? 9 : 8 ?>" class="text-center text-muted py-4">No tasks found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $t): ?>
                            <?php $pColor = $priorityBadges[$t['priority']] ?? 'secondary'; ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $t['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($t['patient_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($t['patient_number']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= ucwords(str_replace('_', ' ', $t['task_type'])) ?></span></td>
                                <td><?= htmlspecialchars(truncate($t['description'], 60)) ?></td>
                                <td><span class="badge bg-<?= $pColor ?>"><?= ucfirst($t['priority']) ?></span></td>
                                <?php if ($isAdmin): ?>
                                    <td><small><?= htmlspecialchars($t['assigned_to_name']) ?></small></td>
                                <?php endif; ?>
                                <td><small><?= htmlspecialchars($t['assigned_by_name']) ?></small></td>
                                <td class="small"><?= $t['due_date'] ? formatDateTime($t['due_date']) : '-' ?></td>
                                <td><?= getStatusBadge($t['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($t['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-sm btn-outline-info me-1" title="Start Task"><i class="fas fa-play"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($t['status'] === 'in_progress'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Complete Task" onclick="return confirm('Mark task as completed?')"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($t['status'], ['pending', 'in_progress'])): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Task" onclick="return confirm('Cancel this task?')"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($t['status'] === 'completed' && $t['completed_at']): ?>
                                        <small class="text-muted"><?= formatDateTime($t['completed_at']) ?></small>
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

<div class="modal fade" id="newTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_task">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Assign Nursing Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select select2-patient" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_number'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Task Type <span class="text-danger">*</span></label>
                        <select name="task_type" class="form-select" required>
                            <option value="medication">Medication</option>
                            <option value="wound_care">Wound Care</option>
                            <option value="vital_signs">Vital Signs</option>
                            <option value="catheter">Catheter</option>
                            <option value="dressing">Dressing</option>
                            <option value="monitoring">Monitoring</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select select2-nurse" required>
                                <option value="">Select Nurse</option>
                                <?php foreach ($nurses as $n): ?>
                                    <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="datetime-local" name="due_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2-patient').select2({theme: 'bootstrap-5', dropdownParent: $('#newTaskModal'), width: '100%'});
    $('.select2-nurse').select2({theme: 'bootstrap-5', dropdownParent: $('#newTaskModal'), width: '100%'});
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
