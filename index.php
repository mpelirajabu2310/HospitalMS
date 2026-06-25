<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
define("PAGE_TITLE", t('Dashboard'));

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
    case "administrator":
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
        $stats["new_patients"] = Database::fetch("SELECT COUNT(*) as c FROM patients WHERE DATE(registration_date) = CURDATE()")["c"];
        $stats["payment_pending"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status IN ('PAYMENT_PENDING','NEW_VISIT')")["c"];
        $stats["awaiting_vitals"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status = 'WAITING_FOR_VITALS'")["c"];
        $stats["awaiting_doctor"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status = 'WAITING_FOR_DOCTOR'")["c"];
        $stats["in_treatment"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status IN ('WAITING_FOR_LAB','LAB_RESULTS_READY','WAITING_FOR_PHARMACY','MEDICATION_COMPLETED')")["c"];
        $stats["discharged_today"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() AND status IN ('DISCHARGED','VISIT_COMPLETED')")["c"];
        $pendingTasks = Database::fetchAll("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled' ORDER BY a.appointment_time LIMIT 10");
        $todayVisits = Database::fetchAll(
            "SELECT v.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_number, p.phone,
                    (SELECT COUNT(*) FROM invoices WHERE visit_id = v.id AND status = 'paid') as invoices_paid,
                    (SELECT COUNT(*) FROM invoices WHERE visit_id = v.id) as invoices_total,
                    (SELECT COUNT(*) FROM invoices WHERE visit_id = v.id AND status = 'pending') as invoices_pending
             FROM visits v
             JOIN patients p ON v.patient_id = p.id
             WHERE DATE(v.visit_date) = CURDATE()
             ORDER BY v.created_at DESC LIMIT 20");
        $todayNewPatients = Database::fetchAll(
            "SELECT p.* FROM patients p WHERE DATE(p.registration_date) = CURDATE() ORDER BY p.created_at DESC LIMIT 10"
        );
        break;

    case "doctor":
        $stats["assigned_patients"] = Database::fetch("SELECT COUNT(*) as c FROM visits WHERE referred_to = ? AND DATE(visit_date) = CURDATE() AND status NOT IN ('VISIT_COMPLETED','CANCELLED')", [$userId])["c"];
        $stats["today_consultations"] = Database::fetch("SELECT COUNT(*) as c FROM consultations WHERE doctor_id = ? AND DATE(consultation_date) = CURDATE()", [$userId])["c"];
        $stats["pending_lab_results"] = Database::fetch("SELECT COUNT(*) as c FROM lab_requests lr JOIN visits v ON lr.visit_id = v.id WHERE lr.doctor_id = ? AND lr.status = 'completed' AND lr.completed_at IS NOT NULL", [$userId])["c"];
        $stats["pending_prescriptions"] = Database::fetch("SELECT COUNT(*) as c FROM prescriptions WHERE doctor_id = ? AND status = 'active'", [$userId])["c"];
        $pendingTasks = Database::fetchAll("SELECT v.*, CONCAT(p.first_name,' ',p.last_name) as patient_name FROM visits v JOIN patients p ON v.patient_id = p.id WHERE v.referred_to = ? AND v.status IN ('PAYMENT_COMPLETED','WAITING_FOR_LAB','LAB_COMPLETED','WAITING_FOR_PHARMACY','MEDICATION_COMPLETED') AND DATE(v.visit_date) = CURDATE() ORDER BY v.created_at ASC", [$userId]);
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

$icons = [
    "total_patients" => ["users", "primary"], "total_doctors" => ["user-md", "info"],
    "total_appointments" => ["calendar-check", "warning"], "today_revenue" => ["money-bill-wave", "success"],
    "total_visits" => ["walking", "info"], "pending_lab" => ["flask", "danger"],
    "low_stock" => ["exclamation-triangle", "danger"], "active_admissions" => ["door-open", "primary"],
    "in_treatment" => ["procedures", "primary"], "discharged_today" => ["door-open", "success"],
    "today_appointments" => ["calendar-check", "primary"], "checked_in" => ["sign-in-alt", "success"],
    "new_patients" => ["user-plus", "info"], "pending_queue" => ["list-ol", "warning"],
    "assigned_patients" => ["users", "primary"], "today_consultations" => ["stethoscope", "success"],
    "pending_lab_results" => ["flask", "warning"], "pending_prescriptions" => ["prescription", "danger"],
    "assigned_tasks" => ["tasks", "primary"], "active_tasks" => ["spinner", "warning"],
    "ward_patients" => ["procedures", "info"], "pending_tests" => ["vial", "warning"],
    "in_progress" => ["spinner", "primary"], "completed_today" => ["check-circle", "success"],
    "dispensed_today" => ["hand-holding-medical", "success"], "total_medicines" => ["pills", "info"],
    "pending_invoices" => ["file-invoice", "warning"], "paid_today" => ["credit-card", "success"],
    "overdue" => ["exclamation-circle", "danger"],
];
$labels = [
    "total_patients" => t('Total Patients'), "total_doctors" => t('Doctors'),
    "total_appointments" => t("Today's Appointments"), "today_revenue" => t("Today's Revenue"),
    "total_visits" => t("Today's Visits"), "pending_lab" => t('Pending Lab Tests'),
    "low_stock" => t('Low Stock Items'), "active_admissions" => t('Active Admissions'),
    "in_treatment" => t('In Treatment'), "discharged_today" => t('Discharged Today'),
    "today_appointments" => t("Today's Appointments"), "checked_in" => t('Checked In'),
    "new_patients" => t('New Patients Today'), "pending_queue" => t('In Queue'),
    "assigned_patients" => t('Assigned Patients'), "today_consultations" => t('Consultations Today'),
    "pending_lab_results" => t('Pending Lab Results'), "pending_prescriptions" => t('Pending Prescriptions'),
    "assigned_tasks" => t('Pending Tasks'), "active_tasks" => t('Active Tasks'),
    "ward_patients" => t('Ward Patients'), "pending_tests" => t('Pending Tests'),
    "in_progress" => t('In Progress'), "completed_today" => t('Completed Today'),
    "dispensed_today" => t('Dispensed Today'), "total_medicines" => t('Total Medicines'),
    "pending_invoices" => t('Pending Invoices'), "paid_today" => t('Paid Today'),
    "overdue" => t('Overdue Invoices'),
];

include_once __DIR__ . "/includes/header.php";
?>

<div class="page-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="page-title mb-1"><?= t('Welcome') ?>, <?= Auth::user()["first_name"] ?>!</h4>
            <p class="page-subtitle mb-0"><?= Auth::user()["role_display"] ?> <?= t('Dashboard') ?> &bull; <?= date("l, F j, Y") ?></p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i> <?= t('Refresh') ?>
            </button>
        </div>
    </div>

<?php if (Auth::userHasRole("receptionist")): ?>

<!-- ═══════════════════════════════════ GOTHOMIS RECEPTION DASHBOARD ═══════════════════════════════════ -->

<!-- Patient Search -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form action="<?= APP_URL ?>/modules/patients/list.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-8">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search patient by name, ID number, or phone number..." style="font-size:1rem">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-search me-2"></i>Search</button>
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <a href="<?= APP_URL ?>/modules/patients/register.php" class="btn btn-success btn-lg flex-fill flex-md-grow-0">
                    <i class="fas fa-user-plus me-2"></i>New Patient
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Quick Action Tiles -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/patients/register.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#6366f1,#4338ca);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Register Patient</h6>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/reception/checkin.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-sign-in-alt fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Patient Check-In</h6>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/reception/queue.php?tab=payment_pending" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-credit-card fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Payment Collection</h6>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/reception/queue.php?tab=awaiting_vitals" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-heartbeat fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Vital Signs</h6>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/reception/queue.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-list-ol fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Patient Queue</h6>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/modules/reception/queue.php?tab=discharged" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100" style="background:linear-gradient(135deg,#6b7280,#4b5563);color:#fff;border-radius:12px">
                <div class="card-body py-2">
                    <i class="fas fa-door-open fa-2x mb-2"></i>
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem">Discharge</h6>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Status Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6366f1">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(99,102,241,0.12);color:#6366f1;flex-shrink:0">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= formatNumber($stats['today_appointments'] ?? 0) ?></h4>
                    <small class="text-muted">Today's Appointments</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0ea5e9">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(14,165,233,0.12);color:#0ea5e9;flex-shrink:0">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= formatNumber($stats['new_patients'] ?? 0) ?></h4>
                    <small class="text-muted">New Patients Today</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f59e0b">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(245,158,11,0.12);color:#f59e0b;flex-shrink:0">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= formatNumber($stats['payment_pending'] ?? 0) ?></h4>
                    <small class="text-muted">Payment Pending</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #10b981">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(16,185,129,0.12);color:#10b981;flex-shrink:0">
                    <i class="fas fa-door-open"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold"><?= formatNumber($stats['discharged_today'] ?? 0) ?></h4>
                    <small class="text-muted">Discharged Today</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Workflow Status Cards -->
    <div class="col-md-12">
        <div class="row g-2">
            <div class="col">
                <div class="card border-0 shadow-sm text-center py-2" style="background:linear-gradient(135deg,#fef3c7,#fde68a)">
                    <div class="card-body py-2">
                        <h5 class="mb-0 fw-bold text-warning-emphasis"><?= formatNumber($stats['payment_pending'] ?? 0) ?></h5>
                        <small class="text-warning-emphasis fw-semibold">Waiting Payment</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center py-2" style="background:linear-gradient(135deg,#ffedd5,#fed7aa)">
                    <div class="card-body py-2">
                        <h5 class="mb-0 fw-bold text-orange-emphasis"><?= formatNumber($stats['awaiting_vitals'] ?? 0) ?></h5>
                        <small class="text-orange-emphasis fw-semibold">Waiting Vitals</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center py-2" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe)">
                    <div class="card-body py-2">
                        <h5 class="mb-0 fw-bold text-primary-emphasis"><?= formatNumber($stats['awaiting_doctor'] ?? 0) ?></h5>
                        <small class="text-primary-emphasis fw-semibold">Waiting Doctor</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center py-2" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
                    <div class="card-body py-2">
                        <h5 class="mb-0 fw-bold text-indigo-emphasis"><?= formatNumber($stats['in_treatment'] ?? 0) ?></h5>
                        <small class="text-indigo-emphasis fw-semibold">In Treatment</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm text-center py-2" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0)">
                    <div class="card-body py-2">
                        <h5 class="mb-0 fw-bold text-success-emphasis"><?= formatNumber($stats['discharged_today'] ?? 0) ?></h5>
                        <small class="text-success-emphasis fw-semibold">Discharged</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Visits + Recent Registrations -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-clinic-medical me-2 text-primary"></i>Today's Patient Visits</h6>
                <a href="<?= APP_URL ?>/modules/reception/queue.php" class="btn btn-sm btn-outline-primary">View Full Queue <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todayVisits)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                        <p class="mb-0">No visits recorded today.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Visit #</th>
                                <th>Time</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayVisits as $tv): ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $tv['patient_id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($tv['patient_name']) ?></a>
                                    <br><small class="text-muted"><?= htmlspecialchars($tv['patient_number']) ?></small>
                                </td>
                                <td class="fw-medium"><?= htmlspecialchars($tv['visit_number']) ?></td>
                                <td><?= date('H:i', strtotime($tv['visit_time'])) ?></td>
                                <td>
                                    <?php if ($tv['invoices_paid'] > 0): ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Paid</span>
                                    <?php elseif ($tv['invoices_pending'] > 0): ?>
                                        <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($tv['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($tv['status'] === 'PAYMENT_PENDING' || $tv['status'] === 'NEW_VISIT'): ?>
                                        <?php if ($tv['invoices_pending'] > 0): ?>
                                            <a href="<?= APP_URL ?>/modules/reception/payment.php?visit_id=<?= $tv['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-check-double me-1"></i>Confirm</a>
                                        <?php else: ?>
                                            <a href="<?= APP_URL ?>/modules/reception/payment.php?visit_id=<?= $tv['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-credit-card me-1"></i>Pay</a>
                                        <?php endif; ?>
                                    <?php elseif ($tv['status'] === 'WAITING_FOR_VITALS'): ?>
                                        <a href="<?= APP_URL ?>/modules/reception/vitals.php?visit_id=<?= $tv['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-heartbeat me-1"></i>Vitals</a>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $tv['patient_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
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

    <div class="col-lg-4">
        <!-- Appointments -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-calendar-alt me-2 text-info"></i>Today's Appointments</h6>
                <a href="<?= APP_URL ?>/modules/reception/appointments.php" class="btn btn-sm btn-outline-info">Manage</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingTasks)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-check fa-2x mb-2 text-muted opacity-50"></i>
                        <p class="mb-0 small">No appointments today.</p>
                    </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingTasks as $apt): ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="fs-13"><?= htmlspecialchars($apt['patient_name']) ?></strong>
                                <small class="d-block text-muted"><i class="far fa-clock me-1"></i><?= date('H:i', strtotime($apt['appointment_time'])) ?></small>
                            </div>
                            <span class="badge bg-info"><?= htmlspecialchars($apt['type'] ?? 'General') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Registrations -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-user-plus me-2 text-success"></i>Recent Registrations</h6>
                <a href="<?= APP_URL ?>/modules/patients/list.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todayNewPatients)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-user-plus fa-2x mb-2 text-muted opacity-50"></i>
                        <p class="mb-0 small">No patients registered today.</p>
                    </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($todayNewPatients as $np): ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-size:12px">
                                <?= strtoupper(substr($np['first_name'], 0, 1)) ?>
                            </div>
                            <div class="flex-grow-1">
                                <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $np['id'] ?>" class="text-decoration-none fw-medium fs-13"><?= htmlspecialchars($np['first_name'] . ' ' . $np['last_name']) ?></a>
                                <small class="d-block text-muted" style="line-height:1.2"><?= htmlspecialchars($np['patient_number']) ?></small>
                            </div>
                            <span class="badge bg-success-subtle text-success-emphasis" style="font-size:10px">New</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="card-footer text-center py-2">
                    <a href="<?= APP_URL ?>/modules/patients/register.php" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>Register New Patient</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ═══════════════════════════════════ GENERIC DASHBOARD (other roles) ═══════════════════════════════════ -->

    <div class="row g-3 mb-4">
        <?php $i = 0; foreach ($stats as $key => $value): $i++;
            $icon = $icons[$key] ?? ["chart-bar", "secondary"];
            $label = $labels[$key] ?? ucwords(str_replace("_", " ", $key));
            $displayVal = is_numeric($value) ? (in_array($key, ["today_revenue"]) ? formatCurrency($value) : formatNumber($value)) : ($value ?? 0);
        ?>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 stagger-<?= $i ?> animate-fade-in-up">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1"><?= $label ?></p>
                        <h3 class="stat-value mb-0" data-count="<?= is_numeric($value) ? $value : 0 ?>"><?= $displayVal ?></h3>
                    </div>
                    <div class="stat-icon <?= $icon[1] ?>">
                        <i class="fas fa-<?= $icon[0] ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (Auth::isSuperAdmin() || Auth::hasPermission("manage_patients") || Auth::hasPermission("manage_appointments") || Auth::hasPermission("manage_invoices")): ?>
    <div class="row g-3 mb-4 animate-fade-in-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (Auth::isSuperAdmin() || Auth::hasPermission("manage_patients")): ?>
                        <a href="<?= APP_URL ?>/modules/patients/register.php" class="quick-action-btn">
                            <div class="q-icon" style="background:var(--primary-subtle);color:var(--primary)"><i class="fas fa-user-plus"></i></div>
                            <div class="q-text">
                                <strong><?= t('Register Patient') ?></strong>
                                <small><?= t('Add a new patient record') ?></small>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::userHasRole("receptionist") || Auth::isSuperAdmin()): ?>
                        <a href="<?= APP_URL ?>/modules/reception/appointments.php" class="quick-action-btn">
                            <div class="q-icon" style="background:var(--success-subtle);color:var(--success)"><i class="fas fa-calendar-plus"></i></div>
                            <div class="q-text">
                                <strong><?= t('New Appointment') ?></strong>
                                <small><?= t('Schedule appointment') ?></small>
                            </div>
                        </a>
                        <a href="<?= APP_URL ?>/modules/reception/checkin.php" class="quick-action-btn">
                            <div class="q-icon" style="background:var(--info-subtle);color:var(--info)"><i class="fas fa-sign-in-alt"></i></div>
                            <div class="q-text">
                                <strong><?= t('Check-In Patient') ?></strong>
                                <small><?= t('Start a visit') ?></small>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::userHasRole("cashier") || Auth::isSuperAdmin()): ?>
                        <a href="<?= APP_URL ?>/modules/billing/invoices.php" class="quick-action-btn">
                            <div class="q-icon" style="background:var(--warning-subtle);color:var(--warning)"><i class="fas fa-file-invoice"></i></div>
                            <div class="q-text">
                                <strong><?= t('New Invoice') ?></strong>
                                <small><?= t('Create invoice') ?></small>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if (Auth::isSuperAdmin() || Auth::userHasRole("nurse")): ?>
                        <a href="<?= APP_URL ?>/modules/nursing/tasks.php" class="quick-action-btn">
                            <div class="q-icon" style="background:var(--danger-subtle);color:var(--danger)"><i class="fas fa-tasks"></i></div>
                            <div class="q-text">
                                <strong><?= t('Nursing Tasks') ?></strong>
                                <small><?= t('View tasks') ?></small>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 animate-fade-in-up">
        <?php if (!empty($pendingTasks)): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-tasks me-2 text-primary"></i><?= t('Pending Tasks') ?></h6>
                    <span class="badge bg-primary"><?= count($pendingTasks) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($pendingTasks as $task): ?>
                        <div class="list-group-item px-3 py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="fs-13"><?= htmlspecialchars($task["patient_name"] ?? "N/A") ?></strong>
                                    <small class="d-block text-muted mt-0">
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
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-history me-2 text-info"></i><?= t('Recent Activities') ?></h6>
                    <button type="button" class="btn btn-sm btn-soft-info" onclick="loadActivityLogs()">
                        <i class="fas fa-external-link-alt me-1"></i><?= t('View All') ?>
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
                        <div class="list-group-item px-3 py-2 border-bottom">
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
                                        <strong class="fs-13"><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?></strong>
                                        <small class="d-block text-muted" style="font-size:11px;line-height:1.2"><?= htmlspecialchars($g['role_display']) ?> &bull; <?= $g['count'] ?> <?= t('activity(ies)') ?></small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted" style="font-size:11px;white-space:nowrap"><?= timeAgo($g['last_activity']) ?></small>
                                    <button type="button" class="btn btn-xs btn-soft-info" onclick="showUserActivities(<?= $g['user_id'] ?>, '<?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (count($recentActivities) > 6): ?>
                <div class="card-footer text-center py-2 border-top">
                    <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="loadActivityLogs()"><?= t('View All Activities') ?> <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($notifications)): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-bell me-2 text-warning"></i><?= t('Notifications') ?></h6>
                    <a href="<?= APP_URL ?>/modules/notifications/index.php" class="small text-decoration-none fw-semibold"><?= t('View All') ?></a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notif): ?>
                        <div class="list-group-item px-3 py-2 border-bottom <?= !$notif["is_read"] ? "bg-light" : "" ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="fs-13"><?= htmlspecialchars($notif["title"]) ?></strong>
                                    <small class="d-block text-muted mt-0"><?= htmlspecialchars(truncate($notif["message"], 80)) ?></small>
                                </div>
                                <small class="text-muted" style="white-space:nowrap"><?= timeAgo($notif["created_at"]) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

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
    <div class="row g-3 mt-3 animate-fade-in-up">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-line me-2 text-success"></i><?= t('Revenue Overview (12 Months)') ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-pie me-2 text-primary"></i><?= t('Visit Distribution') ?></h6>
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
</div>

<?php include_once __DIR__ . "/includes/footer.php"; ?>

<div class="modal fade" id="activitiesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold"><i class="fas fa-history me-2 text-info"></i><?= t('User Activities') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="activitiesModalContent" class="p-3">
                    <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-2 mb-0 small"><?= t('Loading activities...') ?></p></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="activityLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold"><i class="fas fa-clipboard-list me-2 text-info"></i><?= t('Activity Logs') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" id="activitySearch" class="form-control form-control-sm" placeholder="<?= t('Search activities...') ?>">
                    </div>
                    <div class="col-md-3">
                        <select id="activityModule" class="form-select form-select-sm">
                            <option value=""><?= t('All Modules') ?></option>
                            <option value="auth"><?= t('Auth') ?></option>
                            <option value="patients"><?= t('Patients') ?></option>
                            <option value="appointments"><?= t('Appointments') ?></option>
                            <option value="billing"><?= t('Billing') ?></option>
                            <option value="pharmacy"><?= t('Pharmacy') ?></option>
                            <option value="laboratory"><?= t('Laboratory') ?></option>
                            <option value="admissions"><?= t('Admissions') ?></option>
                            <option value="admin"><?= t('Admin') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="activityUser" class="form-select form-select-sm">
                            <option value=""><?= t('All Users') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-soft-primary w-100" onclick="loadActivityLogs()"><i class="fas fa-search me-1"></i><?= t('Search') ?></button>
                    </div>
                </div>
                <div id="activityLogsContent">
                    <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-2 mb-0 small"><?= t('Loading activity logs...') ?></p></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function showUserActivities(userId, userName) {
    document.getElementById('activitiesModal').querySelector('.modal-title').innerHTML = '<i class="fas fa-history me-2 text-info"></i>Activities - ' + userName;
    document.getElementById('activitiesModalContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-2 mb-0 small">Loading activities...</p></div>';
    new bootstrap.Modal(document.getElementById('activitiesModal')).show();

    fetch('<?= APP_URL ?>/api/activities.php?user_id=' + userId)
        .then(r => r.text())
        .then(html => { document.getElementById('activitiesModalContent').innerHTML = html; })
        .catch(() => { document.getElementById('activitiesModalContent').innerHTML = '<div class="alert alert-danger m-3">Failed to load activities.</div>'; });
}

function loadActivityLogs(page) {
    page = page || 1;
    document.getElementById('activityLogsContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="text-muted mt-2 mb-0 small">Loading activity logs...</p></div>';
    new bootstrap.Modal(document.getElementById('activityLogsModal')).show();

    const search = document.getElementById('activitySearch')?.value || '';
    const module = document.getElementById('activityModule')?.value || '';
    const userId = document.getElementById('activityUser')?.value || '';

    fetch('<?= APP_URL ?>/api/activities.php?page=' + page + '&search=' + encodeURIComponent(search) + '&module=' + encodeURIComponent(module) + '&user_id=' + encodeURIComponent(userId))
        .then(r => r.text())
        .then(html => { document.getElementById('activityLogsContent').innerHTML = html; })
        .catch(() => { document.getElementById('activityLogsContent').innerHTML = '<div class="alert alert-danger m-3">Failed to load activity logs.</div>'; });
}

function filterActivityLogs() { loadActivityLogs(1); }

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('activitySearch')?.addEventListener('keyup', filterActivityLogs);
    document.getElementById('activityModule')?.addEventListener('change', filterActivityLogs);
    document.getElementById('activityUser')?.addEventListener('change', filterActivityLogs);
});
</script>
