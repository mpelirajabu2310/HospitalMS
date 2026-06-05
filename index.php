<?php
define("PAGE_TITLE", "Dashboard");
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";

if (!Auth::check()) {
    redirect("modules/auth/login.php");
}

$userId = Auth::id();
$role = Auth::role();

$stats = [];
$recentActivities = [];
$pendingTasks = [];
$notifications = getUnreadNotifications($userId, 5);
$notifCount = getNotificationCount($userId);

switch ($role) {
    case "super_admin":
    case "admin":
        $stats["total_patients"] = Database::fetch("SELECT COUNT(*) as c FROM patients")["c"];
        $stats["total_doctors"] = Database::fetch("SELECT COUNT(*) as c FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'doctor')")["c"];
        $stats["total_appointments"] = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE DATE(appointment_date) = CURDATE()")["c"];
        $stats["today_revenue"] = Database::fetch("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE DATE(payment_date) = CURDATE()")["c"];
        $stats["total_visits"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE()")["c"];
        $stats["pending_lab"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'pending'")["c"];
        $stats["low_stock"] = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE current_stock <= reorder_level")["c"];
        $stats["active_admissions"] = Database::fetch("SELECT COUNT(*) as c FROM admissions WHERE status = 'admitted'")["c"];
        $recentActivities = Database::fetchAll("SELECT ual.*, u.first_name, u.last_name FROM user_activity_logs ual JOIN users u ON ual.user_id = u.id ORDER BY ual.created_at DESC LIMIT 10");
        break;

    case "receptionist":
        $stats["today_appointments"] = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE DATE(appointment_date) = CURDATE()")["c"];
        $stats["checked_in"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status = 'waiting'")["c"];
        $stats["new_patients"] = Database::fetch("SELECT COUNT(*) as c FROM patients WHERE DATE(registration_date) = CURDATE()")["c"];
        $stats["pending_queue"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status IN ('waiting','in_consultation')")["c"];
        $pendingTasks = Database::fetchAll("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled' ORDER BY a.appointment_time LIMIT 10");
        break;

    case "doctor":
        $stats["assigned_patients"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE referred_to = ? AND DATE(visit_date) = CURDATE() AND status != 'completed'", [$userId])["c"];
        $stats["today_consultations"] = Database::fetch("SELECT COUNT(*) as c FROM consultations WHERE doctor_id = ? AND DATE(consultation_date) = CURDATE()", [$userId])["c"];
        $stats["pending_lab_results"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests lr JOIN visits v ON lr.visit_id = v.id WHERE lr.doctor_id = ? AND lr.status = 'completed' AND lr.completed_at IS NOT NULL", [$userId])["c"];
        $stats["pending_prescriptions"] = Database::fetch("SELECT COUNT(*) as c FROM prescriptions WHERE doctor_id = ? AND status = 'active'", [$userId])["c"];
        $pendingTasks = Database::fetchAll("SELECT v.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM visits v JOIN patients p ON v.patient_id = p.id WHERE v.referred_to = ? AND v.status IN ('waiting','in_consultation') AND DATE(v.visit_date) = CURDATE() ORDER BY v.created_at ASC", [$userId]);
        break;

    case "nurse":
        $stats["assigned_tasks"] = Database::fetch("SELECT COUNT(*) as c FROM nursing_tasks WHERE assigned_to = ? AND status = 'pending'", [$userId])["c"];
        $stats["active_tasks"] = Database::fetch("SELECT COUNT(*) as c FROM nursing_tasks WHERE assigned_to = ? AND status = 'in_progress'", [$userId])["c"];
        $stats["ward_patients"] = Database::fetch("SELECT COUNT(*) as c FROM admissions a JOIN beds b ON a.bed_id = b.id JOIN wards w ON b.ward_id = w.id WHERE a.status = 'admitted' AND w.department_id = ?", [Auth::user()["department_id"]])["c"];
        $pendingTasks = Database::fetchAll("SELECT nt.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM nursing_tasks nt JOIN patients p ON nt.patient_id = p.id WHERE nt.assigned_to = ? AND nt.status IN ('pending','in_progress') ORDER BY nt.priority DESC, nt.created_at ASC LIMIT 10", [$userId]);
        break;

    case "lab_technician":
        $stats["pending_tests"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'pending'")["c"];
        $stats["in_progress"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'in_progress'")["c"];
        $stats["completed_today"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")["c"];
        $pendingTasks = Database::fetchAll("SELECT lr.*, lt.name as test_name, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM lab_requests lr JOIN lab_tests lt ON lr.lab_test_id = lt.id JOIN patients p ON lr.patient_id = p.id WHERE lr.status IN ('pending','sample_collected') ORDER BY CASE lr.priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END, lr.requested_at ASC LIMIT 10");
        break;

    case "pharmacist":
        $stats["pending_prescriptions"] = Database::fetch("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'active'")["c"];
        $stats["dispensed_today"] = Database::fetch("SELECT COUNT(*) as c FROM pharmacy_sales WHERE DATE(sale_date) = CURDATE()")["c"];
        $stats["low_stock"] = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE current_stock <= reorder_level")["c"];
        $stats["total_medicines"] = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE status = 'active'")["c"];
        $pendingTasks = Database::fetchAll("SELECT p.*, CONCAT(pt.first_name,' ',pt.last_name) as patient_name, CONCAT(u.first_name,' ',u.last_name) as doctor_name FROM prescriptions p JOIN patients pt ON p.patient_id = pt.id JOIN users u ON p.doctor_id = u.id WHERE p.status = 'active' ORDER BY p.created_at ASC LIMIT 10");
        break;

    case "cashier":
        $stats["pending_invoices"] = Database::fetch("SELECT COUNT(*) as c FROM invoices WHERE status = 'pending'")["c"];
        $stats["paid_today"] = Database::fetch("SELECT COUNT(*) as c FROM payments WHERE DATE(payment_date) = CURDATE()")["c"];
        $stats["today_revenue"] = Database::fetch("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE DATE(payment_date) = CURDATE()")["c"];
        $stats["overdue"] = Database::fetch("SELECT COUNT(*) as c FROM invoices WHERE status = 'overdue'")["c"];
        $pendingTasks = Database::fetchAll("SELECT i.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.status IN ('pending','partial') ORDER BY i.created_at ASC LIMIT 10");
        break;

    default:
        $stats["total_patients"] = Database::fetch("SELECT COUNT(*) as c FROM patients")["c"];
}

include_once __DIR__ . "/includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Welcome, <?= Auth::user()["first_name"] ?>!</h4>
        <p class="text-muted mb-0"><?= Auth::user()["role_display"] ?> Dashboard &bull; <?= date("l, F j, Y") ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $key => $value): ?>
    <?php
        $icons = [
            "total_patients" => ["users", "primary"],
            "total_doctors" => ["user-md", "info"],
            "total_appointments" => ["calendar-check", "warning"],
            "today_revenue" => ["money-bill-wave", "success"],
            "total_visits" => ["walking", "info"],
            "pending_lab" => ["flask", "danger"],
            "low_stock" => ["exclamation-triangle", "danger"],
            "active_admissions" => ["door-open", "primary"],
            "today_appointments" => ["calendar-check", "primary"],
            "checked_in" => ["sign-in-alt", "success"],
            "new_patients" => ["user-plus", "info"],
            "pending_queue" => ["list-ol", "warning"],
            "assigned_patients" => ["users", "primary"],
            "today_consultations" => ["stethoscope", "success"],
            "pending_lab_results" => ["flask", "warning"],
            "pending_prescriptions" => ["prescription", "danger"],
            "assigned_tasks" => ["tasks", "primary"],
            "active_tasks" => ["spinner", "warning"],
            "ward_patients" => ["procedures", "info"],
            "pending_tests" => ["vial", "warning"],
            "in_progress" => ["spinner", "primary"],
            "completed_today" => ["check-circle", "success"],
            "dispensed_today" => ["hand-holding-medical", "success"],
            "total_medicines" => ["pills", "info"],
            "pending_invoices" => ["file-invoice", "warning"],
            "paid_today" => ["credit-card", "success"],
            "overdue" => ["exclamation-circle", "danger"],
        ];
        $icon = $icons[$key] ?? ["chart-bar", "secondary"];
        $labels = [
            "total_patients" => "Total Patients",
            "total_doctors" => "Doctors",
            "total_appointments" => "Today's Appointments",
            "today_revenue" => "Today's Revenue",
            "total_visits" => "Today's Visits",
            "pending_lab" => "Pending Lab Tests",
            "low_stock" => "Low Stock Items",
            "active_admissions" => "Active Admissions",
            "today_appointments" => "Today's Appointments",
            "checked_in" => "Checked In",
            "new_patients" => "New Patients Today",
            "pending_queue" => "In Queue",
            "assigned_patients" => "Assigned Patients",
            "today_consultations" => "Consultations Today",
            "pending_lab_results" => "Pending Lab Results",
            "pending_prescriptions" => "Pending Prescriptions",
            "assigned_tasks" => "Pending Tasks",
            "active_tasks" => "Active Tasks",
            "ward_patients" => "Ward Patients",
            "pending_tests" => "Pending Tests",
            "in_progress" => "In Progress",
            "completed_today" => "Completed Today",
            "dispensed_today" => "Dispensed Today",
            "total_medicines" => "Total Medicines",
            "pending_invoices" => "Pending Invoices",
            "paid_today" => "Paid Today",
            "overdue" => "Overdue Invoices",
        ];
        $label = $labels[$key] ?? ucwords(str_replace("_", " ", $key));
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1"><?= $label ?></p>
                        <h3 class="stat-value mb-0"><?= is_numeric($value) ? (in_array($key, ["today_revenue"]) ? formatCurrency($value) : formatNumber($value)) : ($value ?? 0) ?></h3>
                    </div>
                    <div class="stat-icon bg-<?= $icon[1] ?>-subtle text-<?= $icon[1] ?>">
                        <i class="fas fa-<?= $icon[0] ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <?php if (!empty($pendingTasks)): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Pending Tasks</h6>
                <span class="badge bg-warning"><?= count($pendingTasks) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingTasks as $task): ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($task["patient_name"] ?? "N/A") ?></strong>
                                <small class="d-block text-muted">
                                    <?php if (isset($task["appointment_time"])): ?>
                                        <i class="far fa-clock me-1"></i><?= date("H:i", strtotime($task["appointment_time"])) ?>
                                    <?php elseif (isset($task["priority"])): ?>
                                        <?= getStatusBadge($task["priority"]) ?>
                                    <?php elseif (isset($task["test_name"])): ?>
                                        <?= htmlspecialchars($task["test_name"]) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <?php if (isset($task["status"])): ?>
                                    <?= getStatusBadge($task["status"]) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recentActivities)): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Recent Activities</h6>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="loadActivityLogs()" title="View All Activities">
                    <i class="fas fa-external-link-alt me-1"></i>View All
                </button>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="recentActivitiesList">
                    <?php 
                    $grouped = [];
                    foreach ($recentActivities as $activity) {
                        $key = $activity['user_id'];
                        if (!isset($grouped[$key])) {
                            $userData = Database::fetch(
                                "SELECT u.id, u.first_name, u.last_name, u.avatar, r.display_name as role_display
                                 FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
                                [$key]
                            );
                            $grouped[$key] = [
                                'user_id' => $key,
                                'first_name' => $userData['first_name'] ?? $activity['first_name'],
                                'last_name' => $userData['last_name'] ?? $activity['last_name'],
                                'role_display' => $userData['role_display'] ?? '',
                                'avatar' => $userData['avatar'] ?? '',
                                'last_activity' => $activity['created_at'],
                                'count' => 1,
                                'activities' => [$activity]
                            ];
                        } else {
                            $grouped[$key]['count']++;
                            $grouped[$key]['last_activity'] = $activity['created_at'];
                            $grouped[$key]['activities'][] = $activity;
                        }
                    }
                    $grouped = array_slice($grouped, 0, 6);
                    ?>
                    <?php foreach ($grouped as $g): ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar flex-shrink-0">
                                    <?php if ($g['avatar']): ?>
                                        <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($g['avatar']) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover">
                                    <?php else: ?>
                                        <div class="nav-avatar-placeholder" style="width:32px;height:32px;font-size:12px"><?= strtoupper(substr($g['first_name'], 0, 1) . substr($g['last_name'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong style="font-size:13px"><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?></strong>
                                    <small class="d-block text-muted" style="font-size:11px;line-height:1.2"><?= htmlspecialchars($g['role_display']) ?> &bull; <?= $g['count'] ?> activity(ies)</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted" style="font-size:11px;white-space:nowrap"><?= timeAgo($g['last_activity']) ?></small>
                                <button type="button" class="btn btn-xs btn-outline-info" style="padding:2px 8px;font-size:11px" onclick="showUserActivities(<?= $g['user_id'] ?>, '<?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?>')" title="View Activities">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (count($recentActivities) > 6): ?>
            <div class="card-footer text-center py-2">
                <button class="btn btn-sm btn-link text-decoration-none" onclick="loadActivityLogs()">View All Activities <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($notifications)): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h6>
                <a href="<?= APP_URL ?>/modules/notifications/index.php" class="small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item px-3 py-2 <?= !$notif["is_read"] ? "bg-light" : "" ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($notif["title"]) ?></strong>
                                <small class="d-block text-muted"><?= htmlspecialchars(truncate($notif["message"], 80)) ?></small>
                            </div>
                            <small class="text-muted"><?= timeAgo($notif["created_at"]) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$hasChartRole = in_array($role, ["super_admin", "admin", "cashier"]);
if ($hasChartRole):
    $monthlyRevenue = Database::fetchAll("SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, COALESCE(SUM(amount),0) as total FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month ASC");
    $months = [];
    $revenues = [];
    foreach ($monthlyRevenue as $row) {
        $months[] = $row["month"];
        $revenues[] = $row["total"];
    }
?>
<div class="row g-3 mt-2">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Revenue Overview (12 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Visit Distribution</h6>
            </div>
            <div class="card-body">
                <canvas id="visitChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>
<script>
const months = <?= json_encode($months) ?>;
const revenues = <?= json_encode($revenues) ?>;
const visitTypes = <?= json_encode(Database::fetchAll("SELECT type, COUNT(*) as count FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY type")) ?>;
const currencySymbol = '<?= getSetting('currency', 'TZS') ?>';
</script>
<?php endif; ?>

<?php include_once __DIR__ . "/includes/footer.php"; ?>

<!-- Activities Modal -->
<div class="modal fade" id="activitiesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-history me-2 text-info"></i>User Activities</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="activitiesModalContent" class="p-3">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Full Activity Logs Modal -->
<div class="modal fade" id="activityLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-clipboard-list me-2 text-info"></i>Activity Logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" id="activitySearch" class="form-control form-control-sm" placeholder="Search activities..." onkeyup="filterActivityLogs()">
                    </div>
                    <div class="col-md-3">
                        <select id="activityModule" class="form-select form-select-sm" onchange="filterActivityLogs()">
                            <option value="">All Modules</option>
                            <option value="auth">Auth</option>
                            <option value="patients">Patients</option>
                            <option value="appointments">Appointments</option>
                            <option value="billing">Billing</option>
                            <option value="pharmacy">Pharmacy</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="admissions">Admissions</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="activityUser" class="form-select form-select-sm" onchange="filterActivityLogs()">
                            <option value="">All Users</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-primary w-100" onclick="loadActivityLogs()"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div id="activityLogsContent">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showUserActivities(userId, userName) {
    document.getElementById('activitiesModal').querySelector('.modal-title').innerHTML = '<i class="fas fa-history me-2 text-info"></i>Activities - ' + userName;
    document.getElementById('activitiesModalContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('activitiesModal')).show();

    fetch('<?= APP_URL ?>/api/activities.php?user_id=' + userId)
        .then(r => r.text())
        .then(html => {
            document.getElementById('activitiesModalContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('activitiesModalContent').innerHTML = '<div class="alert alert-danger m-3">Failed to load activities.</div>';
        });
}

function loadActivityLogs(page) {
    page = page || 1;
    document.getElementById('activityLogsContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('activityLogsModal')).show();

    const search = document.getElementById('activitySearch')?.value || '';
    const module = document.getElementById('activityModule')?.value || '';
    const userId = document.getElementById('activityUser')?.value || '';

    fetch('<?= APP_URL ?>/api/activities.php?page=' + page + '&search=' + encodeURIComponent(search) + '&module=' + encodeURIComponent(module) + '&user_id=' + encodeURIComponent(userId))
        .then(r => r.text())
        .then(html => {
            document.getElementById('activityLogsContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('activityLogsContent').innerHTML = '<div class="alert alert-danger m-3">Failed to load activity logs.</div>';
        });
}

function filterActivityLogs() {
    loadActivityLogs(1);
}
</script>
