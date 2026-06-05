<?php
define('PAGE_TITLE', 'Appointments');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? '';
$filterDoctor = intval($_GET['doctor_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $patientId = intval($_POST['patient_id'] ?? 0);
    $doctorId = intval($_POST['doctor_id'] ?? 0);
    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $appDate = sanitize($_POST['appointment_date'] ?? '');
    $appTime = sanitize($_POST['appointment_time'] ?? '');
    $type = sanitize($_POST['type'] ?? 'consultation');
    $reason = sanitize($_POST['reason'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (!$patientId || !$doctorId || !$appDate || !$appTime) {
        set_flash('error', 'Patient, doctor, date, and time are required.', 'error');
    } else {
        Database::insert(
            "INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, type, reason, notes, created_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')",
            [$patientId, $doctorId, $departmentId, $appDate, $appTime, $type, $reason ?: null, $notes ?: null, $userId]
        );
        logActivity($userId, 'create_appointment', 'reception', "Booked appointment for patient #$patientId with doctor #$doctorId on $appDate");
        createNotification($doctorId, 'appointment', 'New Appointment', "You have a new $type appointment scheduled for $appDate at $appTime.", '/modules/doctor/appointments.php', 'appointment', $patientId);
        set_flash('success', 'Appointment booked successfully.', 'success');
    }
    redirect('/modules/reception/appointments.php?' . http_build_query(['date' => $filterDate, 'status' => $filterStatus, 'doctor_id' => $filterDoctor]));
}

$where = [];
$params = [];
$where[] = "a.appointment_date = ?";
$params[] = $filterDate;
if ($filterStatus) {
    $where[] = "a.status = ?";
    $params[] = $filterStatus;
}
if ($filterDoctor) {
    $where[] = "a.doctor_id = ?";
    $params[] = $filterDoctor;
}
$whereClause = 'WHERE ' . implode(' AND ', $where);

$appointments = Database::fetchAll(
    "SELECT a.*, 
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_number,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     LEFT JOIN users u ON a.doctor_id = u.id
     $whereClause
     ORDER BY a.appointment_time ASC",
    $params
);

$todayCount = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ?", [date('Y-m-d')])['c'];
$scheduledCount = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ? AND status = 'scheduled'", [date('Y-m-d')])['c'];
$checkedInCount = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ? AND status = 'checked_in'", [date('Y-m-d')])['c'];
$completedCount = Database::fetch("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ? AND status = 'completed'", [date('Y-m-d')])['c'];

$doctors = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'doctor' AND u.status = 'active' ORDER BY u.first_name");
$departments = Database::fetchAll("SELECT * FROM departments WHERE status = 'active' ORDER BY name");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Appointments</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal"><i class="fas fa-plus me-1"></i> Book Appointment</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $todayCount ?></h3><small>Today's Appointments</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $scheduledCount ?></h3><small>Scheduled</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $checkedInCount ?></h3><small>Checked In</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-secondary text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $completedCount ?></h3><small>Completed</small></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium small">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="scheduled" <?= $filterStatus === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="checked_in" <?= $filterStatus === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="no_show" <?= $filterStatus === 'no_show' ? 'selected' : '' ?>>No Show</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Doctor</label>
                <select name="doctor_id" class="form-select">
                    <option value="0">All Doctors</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDoctor === $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Filter</button>
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
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No appointments found for this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($a['appointment_time'])) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $a['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($a['patient_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($a['patient_number']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($a['doctor_name'] ?? '-') ?></td>
                                <td><?= ucfirst($a['type']) ?></td>
                                <td><?= htmlspecialchars(truncate($a['reason'] ?? '-', 30)) ?></td>
                                <td><?= getStatusBadge($a['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($a['status'] === 'scheduled'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Confirm this appointment?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-primary me-1" title="Confirm"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($a['status'] === 'scheduled' || $a['status'] === 'confirmed'): ?>
                                        <a href="<?= APP_URL ?>/modules/reception/checkin.php?appointment_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Check In"><i class="fas fa-sign-in-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if ($a['status'] !== 'cancelled' && $a['status'] !== 'completed'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this appointment?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                                        </form>
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

<div class="modal fade" id="bookAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2 text-primary"></i>Book Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" class="form-select patient-select" required>
                                <option value="">Search patient...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Doctor <span class="text-danger">*</span></label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Date <span class="text-danger">*</span></label>
                            <input type="date" name="appointment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Time <span class="text-danger">*</span></label>
                            <input type="time" name="appointment_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Type</label>
                            <select name="type" class="form-select">
                                <option value="checkup">Checkup</option>
                                <option value="followup">Follow-up</option>
                                <option value="emergency">Emergency</option>
                                <option value="consultation" selected>Consultation</option>
                                <option value="routine">Routine</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-medium">Reason</label>
                            <textarea name="reason" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-medium">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Book Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.patient-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search patient by name, number, or phone...',
        allowClear: true,
        dropdownParent: $('#bookAppointmentModal'),
        ajax: {
            url: '<?= APP_URL ?>/api/patients.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data }; },
            cache: true
        },
        minimumInputLength: 2
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
