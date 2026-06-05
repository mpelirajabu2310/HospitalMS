<?php
define('PAGE_TITLE', 'Reports');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$activeTab = sanitize($_GET['tab'] ?? 'daily');
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

$revenueData = [];
$admissionChartData = [];

// --- Daily Report ---
$dailyDate = sanitize($_GET['daily_date'] ?? date('Y-m-d'));
$dailyVisits = Database::fetch("SELECT COUNT(*) as count FROM visits WHERE visit_date = ?", [$dailyDate])['count'];
$dailyNewPatients = Database::fetch("SELECT COUNT(*) as count FROM patients WHERE DATE(registration_date) = ?", [$dailyDate])['count'];
$dailyAppointments = Database::fetch("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ?", [$dailyDate])['count'];
$dailyCheckins = Database::fetchAll(
    "SELECT HOUR(checked_in_at) as hr, COUNT(*) as cnt FROM visits WHERE visit_date = ? GROUP BY HOUR(checked_in_at) ORDER BY hr",
    [$dailyDate]
);

// --- Monthly Report ---
$monthYear = sanitize($_GET['month_year'] ?? date('Y-m'));
$mParts = explode('-', $monthYear);
$mYear = $mParts[0] ?? date('Y');
$mMonth = $mParts[1] ?? date('m');
$monthPatients = Database::fetch("SELECT COUNT(*) as count FROM patients WHERE YEAR(registration_date) = ? AND MONTH(registration_date) = ?", [$mYear, $mMonth])['count'];
$monthVisits = Database::fetch("SELECT COUNT(*) as count FROM visits WHERE YEAR(visit_date) = ? AND MONTH(visit_date) = ?", [$mYear, $mMonth])['count'];
$monthAppts = Database::fetch("SELECT COUNT(*) as count FROM appointments WHERE YEAR(appointment_date) = ? AND MONTH(appointment_date) = ?", [$mYear, $mMonth])['count'];

// --- Revenue Report ---
$revDateFrom = sanitize($_GET['rev_date_from'] ?? date('Y-m-01'));
$revDateTo = sanitize($_GET['rev_date_to'] ?? date('Y-m-t'));
$revenueSummary = Database::fetch(
    "SELECT COALESCE(SUM(i.total),0) as total_revenue, COALESCE(SUM(i.paid_amount),0) as total_paid,
            COUNT(i.id) as total_invoices
     FROM invoices i WHERE i.invoice_date BETWEEN ? AND ?",
    [$revDateFrom, $revDateTo]
);
$revenueByStatus = Database::fetchAll(
    "SELECT i.status, COUNT(*) as count, COALESCE(SUM(i.total),0) as total
     FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? GROUP BY i.status",
    [$revDateFrom, $revDateTo]
);
$revenueByMethod = Database::fetchAll(
    "SELECT p.payment_method, COALESCE(SUM(p.amount),0) as total, COUNT(*) as count
     FROM payments p WHERE DATE(p.payment_date) BETWEEN ? AND ? GROUP BY p.payment_method ORDER BY total DESC",
    [$revDateFrom, $revDateTo]
);
$revenueDaily = Database::fetchAll(
    "SELECT i.invoice_date, COALESCE(SUM(i.paid_amount),0) as paid
     FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? AND i.status IN ('paid','partial')
     GROUP BY i.invoice_date ORDER BY i.invoice_date",
    [$revDateFrom, $revDateTo]
);
foreach ($revenueDaily as $rd) {
    $revenueData['labels'][] = $rd['invoice_date'];
    $revenueData['data'][] = floatval($rd['paid']);
}

// --- Medicine Stock Report ---
$medicines = Database::fetchAll(
    "SELECT m.*, mc.name as category_name FROM medicines m LEFT JOIN medicine_categories mc ON m.category_id = mc.id ORDER BY m.name"
);
$lowStockItems = Database::fetchAll(
    "SELECT m.*, mc.name as category_name FROM medicines m LEFT JOIN medicine_categories mc ON m.category_id = mc.id WHERE m.current_stock <= m.reorder_level AND m.status = 'active' ORDER BY m.current_stock ASC"
);
$totalStockValue = Database::fetch("SELECT COALESCE(SUM(current_stock * unit_price),0) as val FROM medicines WHERE status = 'active'")['val'];

// --- Laboratory Report ---
$labDateFrom = sanitize($_GET['lab_date_from'] ?? date('Y-m-01'));
$labDateTo = sanitize($_GET['lab_date_to'] ?? date('Y-m-t'));
$labTotal = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE DATE(requested_at) BETWEEN ? AND ?", [$labDateFrom, $labDateTo])['c'];
$labCompleted = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status = 'completed' AND DATE(requested_at) BETWEEN ? AND ?", [$labDateFrom, $labDateTo])['c'];
$labPending = Database::fetch("SELECT COUNT(*) as c FROM lab_requests WHERE status IN ('pending','sample_collected','in_progress') AND DATE(requested_at) BETWEEN ? AND ?", [$labDateFrom, $labDateTo])['c'];
$labByCategory = Database::fetchAll(
    "SELECT ltc.name as category, COUNT(lr.id) as count
     FROM lab_requests lr JOIN lab_tests lt ON lr.lab_test_id = lt.id JOIN lab_test_categories ltc ON lt.category_id = ltc.id
     WHERE DATE(lr.requested_at) BETWEEN ? AND ?
     GROUP BY ltc.name ORDER BY count DESC",
    [$labDateFrom, $labDateTo]
);

// --- Admissions & Discharges Report ---
$admDateFrom = sanitize($_GET['adm_date_from'] ?? date('Y-m-01'));
$admDateTo = sanitize($_GET['adm_date_to'] ?? date('Y-m-t'));
$admissionsTotal = Database::fetch("SELECT COUNT(*) as c FROM admissions WHERE DATE(admission_date) BETWEEN ? AND ?", [$admDateFrom, $admDateTo])['c'];
$dischargesTotal = Database::fetch("SELECT COUNT(*) as c FROM discharges WHERE DATE(discharge_date) BETWEEN ? AND ?", [$admDateFrom, $admDateTo])['c'];
$currentOccupancy = Database::fetch("SELECT COUNT(*) as c FROM admissions WHERE status = 'admitted'")['c'];
$totalBeds = Database::fetch("SELECT SUM(total_beds) as c FROM wards WHERE status = 'active'")['c'];
$admissionTrend = Database::fetchAll(
    "SELECT DATE(admission_date) as dt, COUNT(*) as cnt FROM admissions WHERE DATE(admission_date) BETWEEN ? AND ? GROUP BY DATE(admission_date) ORDER BY dt",
    [$admDateFrom, $admDateTo]
);
foreach ($admissionTrend as $at) {
    $admissionChartData['labels'][] = $at['dt'];
    $admissionChartData['data'][] = intval($at['cnt']);
}

// --- Department Performance ---
$deptDateFrom = sanitize($_GET['dept_date_from'] ?? date('Y-m-01'));
$deptDateTo = sanitize($_GET['dept_date_to'] ?? date('Y-m-t'));
$deptPerformance = Database::fetchAll(
    "SELECT d.name, d.code,
            COUNT(DISTINCT v.id) as visits,
            COUNT(DISTINCT c.id) as consultations,
            COUNT(DISTINCT lr.id) as lab_requests
     FROM departments d
     LEFT JOIN visits v ON v.referred_to = d.id AND DATE(v.visit_date) BETWEEN ? AND ?
     LEFT JOIN consultations c ON c.doctor_id IN (SELECT id FROM users WHERE department_id = d.id) AND DATE(c.consultation_date) BETWEEN ? AND ?
     LEFT JOIN lab_requests lr ON lr.doctor_id IN (SELECT id FROM users WHERE department_id = d.id) AND DATE(lr.requested_at) BETWEEN ? AND ?
     WHERE d.status = 'active'
     GROUP BY d.id ORDER BY visits DESC",
    [$deptDateFrom, $deptDateTo, $deptDateFrom, $deptDateTo, $deptDateFrom, $deptDateTo]
);

// --- User Activity Report ---
$actDateFrom = sanitize($_GET['act_date_from'] ?? date('Y-m-01'));
$actDateTo = sanitize($_GET['act_date_to'] ?? date('Y-m-t'));
$userLogins = Database::fetchAll(
    "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as user_name, r.display_name as role_name,
            COUNT(CASE WHEN al.action = 'login' THEN 1 END) as logins,
            COUNT(CASE WHEN al.action != 'login' THEN 1 END) as actions
     FROM users u
     JOIN roles r ON u.role_id = r.id
     LEFT JOIN user_activity_logs al ON al.user_id = u.id AND DATE(al.created_at) BETWEEN ? AND ?
     WHERE u.status = 'active'
     GROUP BY u.id ORDER BY logins DESC",
    [$actDateFrom, $actDateTo]
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<style>
.report-section { display: none; }
.report-section.active { display: block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Reports</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'daily' ? 'active' : '' ?>" href="?tab=daily&daily_date=<?= $dailyDate ?>">Daily</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'monthly' ? 'active' : '' ?>" href="?tab=monthly&month_year=<?= $monthYear ?>">Monthly</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'revenue' ? 'active' : '' ?>" href="?tab=revenue">Revenue</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'medicines' ? 'active' : '' ?>" href="?tab=medicines">Medicine Stock</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'laboratory' ? 'active' : '' ?>" href="?tab=laboratory">Laboratory</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'admissions' ? 'active' : '' ?>" href="?tab=admissions">Admissions</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'departments' ? 'active' : '' ?>" href="?tab=departments">Departments</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'activity' ? 'active' : '' ?>" href="?tab=activity">User Activity</a></li>
</ul>

<?php
$sections = [
    'daily' => function() use ($dailyDate, $dailyVisits, $dailyNewPatients, $dailyAppointments, $dailyCheckins) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="daily">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">Select Date</label>
                    <input type="date" name="daily_date" class="form-control" value="<?= $dailyDate ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $dailyVisits ?></h3><small>Total Visits</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-success bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-success mb-1"><?= $dailyNewPatients ?></h3><small>New Patients</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-info bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-info mb-1"><?= $dailyAppointments ?></h3><small>Appointments</small></div></div></div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Hourly Check-Ins</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('dailyTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="dailyTable">
                    <thead class="table-light">
                        <tr><th>Hour</th><th>Check-Ins</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyCheckins)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No data for this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyCheckins as $dc): ?>
                                <tr><td><?= str_pad($dc['hr'], 2, '0', STR_PAD_LEFT) ?>:00</td><td><?= $dc['cnt'] ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
    'monthly' => function() use ($monthYear, $monthPatients, $monthVisits, $monthAppts) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="monthly">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">Month / Year</label>
                    <input type="month" name="month_year" class="form-control" value="<?= $monthYear ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $monthVisits ?></h3><small>Visits</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-success bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-success mb-1"><?= $monthPatients ?></h3><small>New Patients</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-info bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-info mb-1"><?= $monthAppts ?></h3><small>Appointments</small></div></div></div>
    </div>
<?php
    },
    'revenue' => function() use ($revDateFrom, $revDateTo, $revenueSummary, $revenueByStatus, $revenueByMethod, $revenueData) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="revenue">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="rev_date_from" class="form-control" value="<?= $revDateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="rev_date_to" class="form-control" value="<?= $revDateTo ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-success bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-success mb-1"><?= formatCurrency($revenueSummary['total_revenue']) ?></h3><small>Total Revenue</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-info bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-info mb-1"><?= formatCurrency($revenueSummary['total_paid']) ?></h3><small>Amount Collected</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $revenueSummary['total_invoices'] ?></h3><small>Total Invoices</small></div></div></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Revenue by Status</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="revenueStatusTable">
                            <thead class="table-light"><tr><th>Status</th><th>Count</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($revenueByStatus as $rs): ?>
                                    <tr><td><?= getStatusBadge($rs['status']) ?></td><td><?= $rs['count'] ?></td><td><?= formatCurrency($rs['total']) ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($revenueByStatus)): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Revenue by Payment Method</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="revenueMethodTable">
                            <thead class="table-light"><tr><th>Method</th><th>Transactions</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($revenueByMethod as $rm): ?>
                                    <tr><td><?= ucwords(str_replace('_', ' ', $rm['payment_method'] ?? 'N/A')) ?></td><td><?= $rm['count'] ?></td><td><?= formatCurrency($rm['total']) ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($revenueByMethod)): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Daily Revenue Trend</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('revenueTrendTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body">
            <canvas id="revenueChart" height="100"></canvas>
            <div class="table-responsive mt-3">
                <table class="table table-sm mb-0" id="revenueTrendTable">
                    <thead class="table-light"><tr><th>Date</th><th>Collected</th></tr></thead>
                    <tbody>
                        <?php foreach ($revenueDaily ?? [] as $rd): ?>
                            <tr><td><?= $rd['invoice_date'] ?></td><td><?= formatCurrency($rd['paid']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($revenueDaily ?? [])): ?><tr><td colspan="2" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
    'medicines' => function() use ($medicines, $lowStockItems, $totalStockValue) {
        $totalMeds = count($medicines);
        $lowCount = count($lowStockItems);
?>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $totalMeds ?></h3><small>Total Medicines</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-danger bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-danger mb-1"><?= $lowCount ?></h3><small>Low Stock Items</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-info bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-info mb-1"><?= formatCurrency($totalStockValue) ?></h3><small>Stock Value</small></div></div></div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>All Medicines & Stock Levels</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('medicineTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="medicineTable">
                    <thead class="table-light">
                        <tr><th>Medicine</th><th>Category</th><th>Stock</th><th>Reorder</th><th>Unit Price</th><th>Stock Value</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines as $m): ?>
                            <?php $rowClass = $m['current_stock'] <= $m['reorder_level'] ? 'table-danger' : ''; ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= htmlspecialchars($m['name']) ?></td>
                                <td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
                                <td><?= floatval($m['current_stock']) ?> <?= htmlspecialchars($m['unit']) ?></td>
                                <td><?= intval($m['reorder_level']) ?></td>
                                <td><?= formatCurrency($m['unit_price']) ?></td>
                                <td><?= formatCurrency(floatval($m['current_stock']) * floatval($m['unit_price'])) ?></td>
                                <td><?= getStatusBadge($m['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($medicines)): ?><tr><td colspan="7" class="text-center text-muted py-3">No medicines.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (!empty($lowStockItems)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock Alerts</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Medicine</th><th>Stock</th><th>Reorder Level</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $ls): ?>
                            <tr>
                                <td><?= htmlspecialchars($ls['name']) ?></td>
                                <td class="fw-bold text-danger"><?= floatval($ls['current_stock']) ?> <?= htmlspecialchars($ls['unit']) ?></td>
                                <td><?= intval($ls['reorder_level']) ?></td>
                                <td><a href="<?= APP_URL ?>/modules/pharmacy/purchases.php?medicine_id=<?= $ls['id'] ?>" class="btn btn-sm btn-outline-primary">Order</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php
    },
    'laboratory' => function() use ($labDateFrom, $labDateTo, $labTotal, $labCompleted, $labPending, $labByCategory) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="laboratory">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="lab_date_from" class="form-control" value="<?= $labDateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="lab_date_to" class="form-control" value="<?= $labDateTo ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $labTotal ?></h3><small>Total Requests</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-success bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-success mb-1"><?= $labCompleted ?></h3><small>Completed</small></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm bg-warning bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-warning mb-1"><?= $labPending ?></h3><small>Pending</small></div></div></div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Tests by Category</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('labCategoryTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="labCategoryTable">
                    <thead class="table-light"><tr><th>Category</th><th>Tests Requested</th></tr></thead>
                    <tbody>
                        <?php foreach ($labByCategory as $lc): ?>
                            <tr><td><?= htmlspecialchars($lc['category']) ?></td><td><?= $lc['count'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($labByCategory)): ?><tr><td colspan="2" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
    'admissions' => function() use ($admDateFrom, $admDateTo, $admissionsTotal, $dischargesTotal, $currentOccupancy, $totalBeds, $admissionChartData) {
        $occupancyPercent = $totalBeds > 0 ? round(($currentOccupancy / $totalBeds) * 100, 1) : 0;
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="admissions">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="adm_date_from" class="form-control" value="<?= $admDateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="adm_date_to" class="form-control" value="<?= $admDateTo ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm bg-primary bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-primary mb-1"><?= $admissionsTotal ?></h3><small>Admissions</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm bg-success bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-success mb-1"><?= $dischargesTotal ?></h3><small>Discharges</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm bg-info bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold text-info mb-1"><?= $currentOccupancy ?> / <?= $totalBeds ?? 0 ?></h3><small>Current Occupancy</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm bg-<?= $occupancyPercent > 80 ? 'danger' : 'success' ?> bg-opacity-10"><div class="card-body text-center"><h3 class="fw-bold mb-1"><?= $occupancyPercent ?>%</h3><small>Occupancy Rate</small></div></div></div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Admission Trend</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('admissionTrendTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body">
            <canvas id="admissionChart" height="100"></canvas>
            <div class="table-responsive mt-3">
                <table class="table table-sm mb-0" id="admissionTrendTable">
                    <thead class="table-light"><tr><th>Date</th><th>Admissions</th></tr></thead>
                    <tbody>
                        <?php foreach ($admissionTrend ?? [] as $at): ?>
                            <tr><td><?= $at['dt'] ?></td><td><?= $at['cnt'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($admissionTrend ?? [])): ?><tr><td colspan="2" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
    'departments' => function() use ($deptDateFrom, $deptDateTo, $deptPerformance) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="departments">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="dept_date_from" class="form-control" value="<?= $deptDateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="dept_date_to" class="form-control" value="<?= $deptDateTo ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Department Performance</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('deptTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="deptTable">
                    <thead class="table-light"><tr><th>Department</th><th>Visits</th><th>Consultations</th><th>Lab Requests</th></tr></thead>
                    <tbody>
                        <?php foreach ($deptPerformance as $dp): ?>
                            <tr><td><strong><?= htmlspecialchars($dp['name']) ?></strong> <small class="text-muted">(<?= htmlspecialchars($dp['code']) ?>)</small></td><td><?= $dp['visits'] ?></td><td><?= $dp['consultations'] ?></td><td><?= $dp['lab_requests'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($deptPerformance)): ?><tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
    'activity' => function() use ($actDateFrom, $actDateTo, $userLogins) {
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="activity">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="act_date_from" class="form-control" value="<?= $actDateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="act_date_to" class="form-control" value="<?= $actDateTo ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>User Activity Log</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV('userActivityTable')"><i class="fas fa-download me-1"></i>CSV</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="userActivityTable">
                    <thead class="table-light"><tr><th>User</th><th>Role</th><th>Logins</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($userLogins as $ul): ?>
                            <tr><td><?= htmlspecialchars($ul['user_name']) ?></td><td><?= htmlspecialchars($ul['role_name']) ?></td><td><?= $ul['logins'] ?></td><td><?= $ul['actions'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($userLogins)): ?><tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
    },
];

// Fix: $revenueDaily was used in closure but might not be defined; define it
$revenueDaily = Database::fetchAll(
    "SELECT i.invoice_date, COALESCE(SUM(i.paid_amount),0) as paid
     FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? AND i.status IN ('paid','partial')
     GROUP BY i.invoice_date ORDER BY i.invoice_date",
    [$revDateFrom, $revDateTo]
);
$revenueData = [];
foreach ($revenueDaily as $rd) {
    $revenueData['labels'][] = $rd['invoice_date'];
    $revenueData['data'][] = floatval($rd['paid']);
}

$admissionTrend = Database::fetchAll(
    "SELECT DATE(admission_date) as dt, COUNT(*) as cnt FROM admissions WHERE DATE(admission_date) BETWEEN ? AND ? GROUP BY DATE(admission_date) ORDER BY dt",
    [$admDateFrom, $admDateTo]
);
$admissionChartData = [];
foreach ($admissionTrend as $at) {
    $admissionChartData['labels'][] = $at['dt'];
    $admissionChartData['data'][] = intval($at['cnt']);
}
?>

<div class="report-sections">
    <?php foreach ($sections as $key => $section): ?>
        <div class="report-section <?= $activeTab === $key ? 'active' : '' ?>" id="report-<?= $key ?>">
            <?php $section(); ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
function exportCSV(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var rows = table.querySelectorAll('tr');
    var csv = [];
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = [];
        cols.forEach(function(col) {
            var text = col.textContent.trim().replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });
    var blob = new Blob([csv.join('\n')], {type: 'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = tableId + '_report.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($revenueData['labels']) && $activeTab === 'revenue'): ?>
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($revenueData['labels']) ?>,
            datasets: [{
                label: 'Revenue Collected',
                data: <?= json_encode($revenueData['data']) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString(); } } }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($admissionChartData['labels']) && $activeTab === 'admissions'): ?>
    new Chart(document.getElementById('admissionChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($admissionChartData['labels']) ?>,
            datasets: [{
                label: 'Admissions',
                data: <?= json_encode($admissionChartData['data']) ?>,
                backgroundColor: 'rgba(13,110,253,0.5)',
                borderColor: '#0d6efd',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
    <?php endif; ?>
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
