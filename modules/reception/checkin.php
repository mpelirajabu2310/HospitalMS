<?php
define('PAGE_TITLE', 'Patient Check-In');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$selectedPatientId = intval($_GET['patient_id'] ?? 0);
$appointmentId = intval($_GET['appointment_id'] ?? 0);

$patient = null;
$appointment = null;

if ($selectedPatientId) {
    $patient = Database::fetch("SELECT * FROM patients WHERE id = ?", [$selectedPatientId]);
}
if ($appointmentId) {
    $appointment = Database::fetch(
        "SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
                CONCAT(u.first_name, ' ', u.last_name) as doctor_name
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         LEFT JOIN users u ON a.doctor_id = u.id
         WHERE a.id = ?",
        [$appointmentId]
    );
    if ($appointment && !$patient) {
        $patient = Database::fetch("SELECT * FROM patients WHERE id = ?", [$appointment['patient_id']]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $patientId = intval($_POST['patient_id'] ?? 0);
    $apptId = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $visitType = sanitize($_POST['visit_type'] ?? 'outpatient');
    $bp = sanitize($_POST['blood_pressure'] ?? '');
    $hr = sanitize($_POST['heart_rate'] ?? '');
    $temp = sanitize($_POST['temperature'] ?? '');
    $weight = sanitize($_POST['weight'] ?? '');
    $height = sanitize($_POST['height'] ?? '');
    $triageNotes = sanitize($_POST['triage_notes'] ?? '');
    $chiefComplaint = sanitize($_POST['chief_complaint'] ?? '');
    $referredTo = !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null;

    if (!$patientId) {
        set_flash('error', 'Please select a patient.', 'error');
    } else {
        $visitNumber = generateVisitNumber();
        $visitId = Database::insert(
            "INSERT INTO visits (patient_id, appointment_id, visit_number, visit_date, visit_time, type, status, chief_complaint, triage_notes, blood_pressure, heart_rate, temperature, weight, height, referred_to, checked_in_by)
             VALUES (?, ?, ?, ?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$patientId, $apptId, $visitNumber, date('Y-m-d'), date('H:i:s'), $visitType, $chiefComplaint ?: null, $triageNotes ?: null, $bp ?: null, $hr ?: null, $temp ?: null, $weight ?: null, $height ?: null, $referredTo, $userId]
        );

        if ($apptId) {
            Database::query("UPDATE appointments SET status = 'checked_in' WHERE id = ?", [$apptId]);
        }

        if ($referredTo) {
            createNotification($referredTo, 'visit', 'New Patient Check-In', "Patient has been checked in and assigned to you.", '/modules/doctor/consultations.php?visit_id=' . $visitId, 'visit', $visitId);
        }

        logActivity($userId, 'checkin_patient', 'reception', "Checked in patient #$patientId, visit $visitNumber");
        set_flash('success', 'Patient checked in successfully. Visit #: ' . $visitNumber, 'success');
        redirect('/modules/reception/queue.php');
    }
}

$doctors = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'doctor' AND u.status = 'active' ORDER BY u.first_name");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2 text-primary"></i>Patient Check-In</h4>
    <a href="<?= APP_URL ?>/modules/reception/queue.php" class="btn btn-outline-secondary"><i class="fas fa-list-ol me-1"></i> View Queue</a>
</div>

<?php if (!$patient): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-medium mb-3"><i class="fas fa-search me-2 text-primary"></i>Search Patient</h6>
        <div class="row g-2">
            <div class="col-md-10">
                <select class="form-select form-select-lg patient-search" id="patientSearch">
                    <option value="">Search patient by name, number, or phone...</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <a href="<?= APP_URL ?>/modules/patients/register.php" class="btn btn-outline-primary"><i class="fas fa-plus"></i> New Patient</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($appointment): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-info border-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Scheduled Appointment:</strong> <?= htmlspecialchars($appointment['patient_name']) ?> with Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                    on <?= formatDate($appointment['appointment_date']) ?> at <?= date('H:i', strtotime($appointment['appointment_time'])) ?>
                    <span class="ms-2"><?= getStatusBadge($appointment['status']) ?></span>
                </div>
                <span class="badge bg-info"><?= ucfirst($appointment['type']) ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <?php if ($patient): ?>
                <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
                <?php if ($appointment): ?>
                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <?php if ($patient['photo']): ?>
                                <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($patient['photo']) ?>" class="rounded-circle me-3" width="48" height="48" style="object-fit:cover">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:20px"><?= strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($patient['patient_number']) ?> | <?= htmlspecialchars($patient['phone']) ?></small>
                            </div>
                            <div class="ms-auto">
                                <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-12">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-clinic-medical me-2"></i>Visit Information</h6>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Visit Type <span class="text-danger">*</span></label>
                        <select name="visit_type" class="form-select" required>
                            <option value="outpatient" selected>Outpatient</option>
                            <option value="inpatient">Inpatient</option>
                            <option value="emergency">Emergency</option>
                            <option value="followup">Follow-up</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Assign Doctor</label>
                        <select name="doctor_id" class="form-select">
                            <option value="">Queue (No doctor assigned)</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($appointment && $appointment['doctor_id'] == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Chief Complaint</label>
                        <textarea name="chief_complaint" class="form-control" rows="2" placeholder="Main reason for visit"><?= htmlspecialchars($_POST['chief_complaint'] ?? ($appointment['reason'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <h6 class="text-primary border-bottom pb-2 mt-2"><i class="fas fa-heartbeat me-2"></i>Vital Signs</h6>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80" value="<?= htmlspecialchars($_POST['blood_pressure'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Heart Rate</label>
                        <input type="text" name="heart_rate" class="form-control" placeholder="bpm" value="<?= htmlspecialchars($_POST['heart_rate'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Temperature</label>
                        <input type="text" name="temperature" class="form-control" placeholder="°C" value="<?= htmlspecialchars($_POST['temperature'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Weight (kg)</label>
                        <input type="text" name="weight" class="form-control" value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Height (cm)</label>
                        <input type="text" name="height" class="form-control" value="<?= htmlspecialchars($_POST['height'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Triage Notes</label>
                        <textarea name="triage_notes" class="form-control" rows="2" placeholder="Any observations from triage"><?= htmlspecialchars($_POST['triage_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check-circle me-1"></i> Check In Patient</button>
                    <a href="<?= APP_URL ?>/modules/reception/checkin.php" class="btn btn-secondary btn-lg">Reset</a>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Search and select a patient above to check them in.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.patient-search').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search patient by name, number, or phone...',
        allowClear: true,
        ajax: {
            url: '<?= APP_URL ?>/api/patients.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data }; },
            cache: true
        },
        minimumInputLength: 2
    }).on('select2:select', function(e) {
        window.location.href = '<?= APP_URL ?>/modules/reception/checkin.php?patient_id=' + e.params.data.id;
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
