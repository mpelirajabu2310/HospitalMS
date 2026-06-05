<?php
define('PAGE_TITLE', 'Patient Consultation');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$visitId = intval($_GET['visit_id'] ?? 0);

if (!$visitId) {
    include_once __DIR__ . '/../../includes/header.php';
    echo display_flash('success');
    echo display_flash('error');
    ?>
    <div class="text-center py-5">
        <i class="fas fa-stethoscope fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">Select a patient to consult</h5>
        <p class="text-muted mb-4">Choose a patient from your assigned list to begin consultation.</p>
        <a href="<?= APP_URL ?>/modules/doctor/patients.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> My Patients</a>
    </div>
    <?php
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$visit = Database::fetch(
    "SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            p.date_of_birth, p.gender, p.photo, p.phone, p.email, p.address_line1, p.city,
            TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
     FROM visits v
     JOIN patients p ON v.patient_id = p.id
     WHERE v.id = ?",
    [$visitId]
);

if (!$visit) {
    set_flash('error', 'Visit not found.', 'error');
    redirect('/modules/doctor/patients.php');
}

$consultation = Database::fetch(
    "SELECT * FROM consultations WHERE visit_id = ? AND doctor_id = ? ORDER BY id DESC LIMIT 1",
    [$visitId, $userId]
);

$existingDiagnoses = [];
$existingPrescription = null;
$existingPrescriptionItems = [];
$existingLabRequests = [];
$existingReferral = null;

if ($consultation) {
    $existingDiagnoses = Database::fetchAll("SELECT * FROM diagnoses WHERE consultation_id = ?", [$consultation['id']]);
    $existingPrescription = Database::fetch("SELECT * FROM prescriptions WHERE consultation_id = ? ORDER BY id DESC LIMIT 1", [$consultation['id']]);
    if ($existingPrescription) {
        $existingPrescriptionItems = Database::fetchAll("SELECT * FROM prescription_items WHERE prescription_id = ?", [$existingPrescription['id']]);
    }
    $existingLabRequests = Database::fetchAll("SELECT * FROM lab_requests WHERE visit_id = ?", [$visitId]);
    $existingReferral = Database::fetch("SELECT * FROM referrals WHERE visit_id = ? ORDER BY id DESC LIMIT 1", [$visitId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $subjective = sanitize($_POST['subjective'] ?? '');
    $objective = sanitize($_POST['objective'] ?? '');
    $assessment = sanitize($_POST['assessment'] ?? '');
    $plan = sanitize($_POST['plan'] ?? '');
    $status = $_POST['save_action'] === 'complete' ? 'completed' : 'in_progress';

    $diagnosisNames = $_POST['diagnosis_name'] ?? [];
    $diagnosisCodes = $_POST['diagnosis_code'] ?? [];
    $diagnosisTypes = $_POST['diagnosis_type'] ?? [];
    $diagnosisNotes = $_POST['diagnosis_notes'] ?? [];

    $medicineIds = $_POST['medicine_id'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $frequencies = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $routes = $_POST['route'] ?? [];
    $instructions = $_POST['instructions'] ?? [];

    $labTestIds = $_POST['lab_test_ids'] ?? [];
    $clinicalNotes = sanitize($_POST['clinical_notes'] ?? '');
    $labPriority = sanitize($_POST['lab_priority'] ?? 'routine');

    $referredToDept = intval($_POST['referred_to_department'] ?? 0);
    $referredToUser = intval($_POST['referred_to_user'] ?? 0);
    $referralReason = sanitize($_POST['referral_reason'] ?? '');

    try {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        if ($consultation) {
            Database::query(
                "UPDATE consultations SET subjective = ?, objective = ?, assessment = ?, plan = ?, status = ?, updated_at = NOW() WHERE id = ?",
                [$subjective, $objective, $assessment, $plan, $status, $consultation['id']]
            );
            $consultationId = $consultation['id'];
        } else {
            $consultationId = Database::insert(
                "INSERT INTO consultations (visit_id, doctor_id, patient_id, consultation_date, subjective, objective, assessment, plan, status)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)",
                [$visitId, $userId, $visit['patient_id'], $subjective, $objective, $assessment, $plan, $status]
            );
        }

        if ($status === 'completed') {
            Database::query("DELETE FROM diagnoses WHERE consultation_id = ?", [$consultationId]);
            foreach ($diagnosisNames as $i => $name) {
                $name = sanitize($name);
                if (empty($name)) continue;
                $code = sanitize($diagnosisCodes[$i] ?? '');
                $type = sanitize($diagnosisTypes[$i] ?? 'primary');
                $notes = sanitize($diagnosisNotes[$i] ?? '');
                Database::insert(
                    "INSERT INTO diagnoses (consultation_id, diagnosis_code, diagnosis_name, diagnosis_type, notes) VALUES (?, ?, ?, ?, ?)",
                    [$consultationId, $code ?: null, $name, $type, $notes ?: null]
                );
            }

            if (!empty(array_filter($medicineIds))) {
                $prescriptionData = [
                    'consultation_id' => $consultationId,
                    'patient_id' => $visit['patient_id'],
                    'doctor_id' => $userId,
                    'prescription_date' => date('Y-m-d'),
                ];
                if ($existingPrescription) {
                    Database::query("UPDATE prescriptions SET status = 'active', notes = ? WHERE id = ?", [null, $existingPrescription['id']]);
                    Database::query("DELETE FROM prescription_items WHERE prescription_id = ?", [$existingPrescription['id']]);
                    $prescriptionId = $existingPrescription['id'];
                } else {
                    $prescriptionId = Database::insert(
                        "INSERT INTO prescriptions (consultation_id, patient_id, doctor_id, prescription_date, status) VALUES (?, ?, ?, ?, 'active')",
                        array_values($prescriptionData)
                    );
                }
                foreach ($medicineIds as $i => $medId) {
                    $medId = intval($medId);
                    if (!$medId) continue;
                    $dosage = sanitize($dosages[$i] ?? '');
                    $frequency = sanitize($frequencies[$i] ?? '');
                    $duration = sanitize($durations[$i] ?? '');
                    $quantity = floatval($quantities[$i] ?? 0);
                    $route = sanitize($routes[$i] ?? 'oral');
                    $inst = sanitize($instructions[$i] ?? '');
                    Database::insert(
                        "INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, duration, quantity, route, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$prescriptionId, $medId, $dosage, $frequency, $duration, $quantity, $route, $inst ?: null]
                    );
                }
                $pharmacists = Database::fetchAll("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'pharmacist' LIMIT 1) AND status = 'active'");
                foreach ($pharmacists as $ph) {
                    createNotification($ph['id'], 'prescription', 'New Prescription', "New prescription created for patient {$visit['patient_name']}.", "/modules/pharmacy/dispensing.php?prescription_id=$prescriptionId", 'prescription', $prescriptionId);
                }
            }

            if (!empty($labTestIds)) {
                Database::query("DELETE FROM lab_requests WHERE visit_id = ? AND status = 'pending'", [$visitId]);
                foreach ($labTestIds as $ltId) {
                    $ltId = intval($ltId);
                    if (!$ltId) continue;
                    Database::insert(
                        "INSERT INTO lab_requests (visit_id, patient_id, doctor_id, lab_test_id, priority, clinical_notes, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
                        [$visitId, $visit['patient_id'], $userId, $ltId, $labPriority, $clinicalNotes ?: null]
                    );
                }
                $labTechs = Database::fetchAll("SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'lab_technician' LIMIT 1) AND status = 'active'");
                foreach ($labTechs as $lt) {
                    createNotification($lt['id'], 'lab_request', 'New Lab Request', "New lab test request for patient {$visit['patient_name']}.", "/modules/laboratory/tests.php", 'visit', $visitId);
                }
            }

            if ($referredToDept && $referralReason) {
                $referralId = Database::insert(
                    "INSERT INTO referrals (patient_id, visit_id, referred_from_department, referred_to_department, referred_by, referred_to_user, referral_reason, priority, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'routine', 'pending')",
                    [$visit['patient_id'], $visitId, Auth::user()['department_id'], $referredToDept, $userId, $referredToUser ?: null, $referralReason]
                );
                $targetUsers = Database::fetchAll("SELECT id FROM users WHERE (department_id = ? OR id = ?) AND status = 'active'", [$referredToDept, $referredToUser ?: 0]);
                foreach ($targetUsers as $tu) {
                    if ($tu['id'] != $userId) {
                        createNotification($tu['id'], 'referral', 'New Referral', "Patient {$visit['patient_name']} has been referred to your department.", "/modules/doctor/referrals.php", 'referral', $referralId);
                    }
                }
            }

            Database::query("UPDATE visits SET status = 'completed', checked_out_at = NOW() WHERE id = ?", [$visitId]);
            logActivity($userId, 'complete_consultation', 'doctor', "Completed consultation for visit #{$visit['visit_number']}");
            set_flash('success', 'Consultation completed successfully.', 'success');
        } else {
            Database::query("UPDATE visits SET status = 'in_consultation' WHERE id = ?", [$visitId]);
            logActivity($userId, 'save_draft', 'doctor', "Saved draft consultation for visit #{$visit['visit_number']}");
            set_flash('success', 'Draft saved successfully.', 'success');
        }

        $db->commit();
        redirect('/modules/doctor/consultation.php?visit_id=' . $visitId);
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Error processing consultation: ' . $e->getMessage(), 'error');
    }
}

$medicines = Database::fetchAll("SELECT id, name, generic_name, strength, dosage_form, unit FROM medicines WHERE status = 'active' ORDER BY name");
$labTests = Database::fetchAll("SELECT lt.id, lt.name, lt.code, ltc.name as category_name FROM lab_tests lt LEFT JOIN lab_test_categories ltc ON lt.category_id = ltc.id WHERE lt.status = 'active' ORDER BY ltc.name, lt.name");
$departments = Database::fetchAll("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-notes-medical me-2 text-primary"></i>Patient Consultation</h4>
    <div>
        <a href="<?= APP_URL ?>/modules/doctor/patients.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Patients</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <?php if ($visit['photo']): ?>
                        <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($visit['photo']) ?>" class="rounded-circle me-3" width="56" height="56" style="object-fit:cover">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;font-size:24px"><?= strtoupper(substr($visit['patient_name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <h6 class="mb-0"><?= htmlspecialchars($visit['patient_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($visit['patient_number']) ?></small>
                    </div>
                </div>
                <table class="table table-sm small mb-0">
                    <tr><td class="text-muted" style="width:100px">Age / Gender</td><td><?= $visit['age'] ?> yrs / <?= ucfirst($visit['gender']) ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?= htmlspecialchars($visit['phone']) ?></td></tr>
                    <tr><td class="text-muted">Visit #</td><td><?= htmlspecialchars($visit['visit_number']) ?></td></tr>
                    <tr><td class="text-muted">Visit Type</td><td><span class="badge bg-info"><?= ucfirst($visit['type']) ?></span></td></tr>
                    <tr><td class="text-muted">Status</td><td><?= getStatusBadge($visit['status']) ?></td></tr>
                    <tr><td class="text-muted">Check-In</td><td><?= formatDateTime($visit['checked_in_at']) ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($visit['blood_pressure'] || $visit['heart_rate'] || $visit['temperature'] || $visit['weight'] || $visit['height']): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2"><h6 class="mb-0"><i class="fas fa-heartbeat me-2 text-danger"></i>Vital Signs</h6></div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <?php if ($visit['blood_pressure']): ?>
                    <div class="col-4"><div class="p-2 rounded" style="background:var(--bg-body)"><small class="text-muted d-block">BP</small><strong><?= htmlspecialchars($visit['blood_pressure']) ?></strong></div></div>
                    <?php endif; ?>
                    <?php if ($visit['heart_rate']): ?>
                    <div class="col-4"><div class="p-2 rounded" style="background:var(--bg-body)"><small class="text-muted d-block">HR</small><strong><?= htmlspecialchars($visit['heart_rate']) ?> bpm</strong></div></div>
                    <?php endif; ?>
                    <?php if ($visit['temperature']): ?>
                    <div class="col-4"><div class="p-2 rounded" style="background:var(--bg-body)"><small class="text-muted d-block">Temp</small><strong><?= htmlspecialchars($visit['temperature']) ?> °C</strong></div></div>
                    <?php endif; ?>
                    <?php if ($visit['weight']): ?>
                    <div class="col-4"><div class="p-2 rounded" style="background:var(--bg-body)"><small class="text-muted d-block">Weight</small><strong><?= htmlspecialchars($visit['weight']) ?> kg</strong></div></div>
                    <?php endif; ?>
                    <?php if ($visit['height']): ?>
                    <div class="col-4"><div class="p-2 rounded" style="background:var(--bg-body)"><small class="text-muted d-block">Height</small><strong><?= htmlspecialchars($visit['height']) ?> cm</strong></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <form method="POST" id="consultationForm">
            <?= csrf_field() ?>
            <input type="hidden" name="visit_id" value="<?= $visitId ?>">

            <ul class="nav nav-tabs nav-fill mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="soap-tab" data-bs-toggle="tab" data-bs-target="#soap" type="button" role="tab"><i class="fas fa-notes-medical me-1"></i>SOAP Notes</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="diagnoses-tab" data-bs-toggle="tab" data-bs-target="#diagnoses" type="button" role="tab"><i class="fas fa-stethoscope me-1"></i>Diagnoses</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptions" type="button" role="tab"><i class="fas fa-prescription me-1"></i>Prescriptions</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab" type="button" role="tab"><i class="fas fa-flask me-1"></i>Lab Tests</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="referral-tab" data-bs-toggle="tab" data-bs-target="#referral" type="button" role="tab"><i class="fas fa-share-alt me-1"></i>Referral</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="soap" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-medium">Subjective <small class="text-muted">(Chief complaint, history of present illness)</small></label>
                                <textarea name="subjective" class="form-control" rows="5" placeholder="Patient's description of symptoms, history of present illness..."><?= htmlspecialchars($consultation['subjective'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Objective <small class="text-muted">(Exam findings, vitals, observations)</small></label>
                                <textarea name="objective" class="form-control" rows="5" placeholder="Physical examination findings, vital signs, observations..."><?= htmlspecialchars($consultation['objective'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Assessment <small class="text-muted">(Diagnosis, assessment of condition)</small></label>
                                <textarea name="assessment" class="form-control" rows="5" placeholder="Your assessment, diagnosis, differential diagnoses..."><?= htmlspecialchars($consultation['assessment'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Plan <small class="text-muted">(Treatment plan, next steps)</small></label>
                                <textarea name="plan" class="form-control" rows="5" placeholder="Treatment plan, medications, follow-up, referrals..."><?= htmlspecialchars($consultation['plan'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="diagnoses" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0"><i class="fas fa-stethoscope me-2 text-primary"></i>Diagnoses</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDiagnosisRow()"><i class="fas fa-plus me-1"></i> Add Diagnosis</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="diagnosesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:35%">Diagnosis Name</th>
                                            <th style="width:15%">Code</th>
                                            <th style="width:20%">Type</th>
                                            <th style="width:25%">Notes</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($existingDiagnoses)): ?>
                                            <?php foreach ($existingDiagnoses as $d): ?>
                                            <tr>
                                                <td><input type="text" name="diagnosis_name[]" class="form-control form-control-sm" value="<?= htmlspecialchars($d['diagnosis_name']) ?>" required></td>
                                                <td><input type="text" name="diagnosis_code[]" class="form-control form-control-sm" value="<?= htmlspecialchars($d['diagnosis_code'] ?? '') ?>"></td>
                                                <td>
                                                    <select name="diagnosis_type[]" class="form-select form-select-sm">
                                                        <option value="primary" <?= $d['diagnosis_type'] === 'primary' ? 'selected' : '' ?>>Primary</option>
                                                        <option value="secondary" <?= $d['diagnosis_type'] === 'secondary' ? 'selected' : '' ?>>Secondary</option>
                                                        <option value="differential" <?= $d['diagnosis_type'] === 'differential' ? 'selected' : '' ?>>Differential</option>
                                                        <option value="provisional" <?= $d['diagnosis_type'] === 'provisional' ? 'selected' : '' ?>>Provisional</option>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="diagnosis_notes[]" class="form-control form-control-sm" value="<?= htmlspecialchars($d['notes'] ?? '') ?>"></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td><input type="text" name="diagnosis_name[]" class="form-control form-control-sm" required></td>
                                            <td><input type="text" name="diagnosis_code[]" class="form-control form-control-sm"></td>
                                            <td>
                                                <select name="diagnosis_type[]" class="form-select form-select-sm">
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                    <option value="differential">Differential</option>
                                                    <option value="provisional">Provisional</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="diagnosis_notes[]" class="form-control form-control-sm"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="prescriptions" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0"><i class="fas fa-prescription me-2 text-primary"></i>Prescriptions</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPrescriptionRow()"><i class="fas fa-plus me-1"></i> Add Medicine</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="prescriptionsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:22%">Medicine</th>
                                            <th style="width:12%">Dosage</th>
                                            <th style="width:12%">Frequency</th>
                                            <th style="width:10%">Duration</th>
                                            <th style="width:10%">Qty</th>
                                            <th style="width:10%">Route</th>
                                            <th style="width:19%">Instructions</th>
                                            <th style="width:40px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($existingPrescriptionItems)): ?>
                                            <?php foreach ($existingPrescriptionItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <select name="medicine_id[]" class="form-select form-select-sm medicine-select" style="width:100%" required>
                                                        <option value="">Select medicine...</option>
                                                        <?php foreach ($medicines as $m): ?>
                                                        <option value="<?= $m['id'] ?>" <?= $item['medicine_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name'] . ($m['strength'] ? ' (' . $m['strength'] . ')' : '')) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="dosage[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['dosage']) ?>" required></td>
                                                <td><input type="text" name="frequency[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['frequency']) ?>" required></td>
                                                <td><input type="text" name="duration[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['duration']) ?>" required></td>
                                                <td><input type="number" name="quantity[]" class="form-control form-control-sm" step="0.01" value="<?= $item['quantity'] ?>" required></td>
                                                <td>
                                                    <select name="route[]" class="form-select form-select-sm">
                                                        <option value="oral" <?= $item['route'] === 'oral' ? 'selected' : '' ?>>Oral</option>
                                                        <option value="IV" <?= $item['route'] === 'IV' ? 'selected' : '' ?>>IV</option>
                                                        <option value="IM" <?= $item['route'] === 'IM' ? 'selected' : '' ?>>IM</option>
                                                        <option value="SC" <?= $item['route'] === 'SC' ? 'selected' : '' ?>>SC</option>
                                                        <option value="topical" <?= $item['route'] === 'topical' ? 'selected' : '' ?>>Topical</option>
                                                        <option value="inhaled" <?= $item['route'] === 'inhaled' ? 'selected' : '' ?>>Inhaled</option>
                                                        <option value="sublingual" <?= $item['route'] === 'sublingual' ? 'selected' : '' ?>>Sublingual</option>
                                                        <option value="rectal" <?= $item['route'] === 'rectal' ? 'selected' : '' ?>>Rectal</option>
                                                        <option value="other" <?= $item['route'] === 'other' ? 'selected' : '' ?>>Other</option>
                                                    </select>
                                                </td>
                                                <td><input type="text" name="instructions[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['instructions'] ?? '') ?>"></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td>
                                                <select name="medicine_id[]" class="form-select form-select-sm medicine-select" style="width:100%" required>
                                                    <option value="">Select medicine...</option>
                                                    <?php foreach ($medicines as $m): ?>
                                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name'] . ($m['strength'] ? ' (' . $m['strength'] . ')' : '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="e.g. 500mg" required></td>
                                            <td><input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="e.g. TDS" required></td>
                                            <td><input type="text" name="duration[]" class="form-control form-control-sm" placeholder="e.g. 7 days" required></td>
                                            <td><input type="number" name="quantity[]" class="form-control form-control-sm" step="0.01" required></td>
                                            <td>
                                                <select name="route[]" class="form-select form-select-sm">
                                                    <option value="oral">Oral</option>
                                                    <option value="IV">IV</option>
                                                    <option value="IM">IM</option>
                                                    <option value="SC">SC</option>
                                                    <option value="topical">Topical</option>
                                                    <option value="inhaled">Inhaled</option>
                                                    <option value="sublingual">Sublingual</option>
                                                    <option value="rectal">Rectal</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="instructions[]" class="form-control form-control-sm" placeholder="Optional notes"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="lab" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-2">
                            <h6 class="mb-0"><i class="fas fa-flask me-2 text-primary"></i>Lab Test Requests</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-medium">Select Tests</label>
                                <div class="row g-2">
                                    <?php
                                    $currentCat = '';
                                    foreach ($labTests as $lt):
                                        $checked = '';
                                        foreach ($existingLabRequests as $existing) {
                                            if ($existing['lab_test_id'] == $lt['id']) { $checked = 'checked'; break; }
                                        }
                                        if ($lt['category_name'] !== $currentCat):
                                            $currentCat = $lt['category_name'];
                                    ?>
                                    <div class="col-12"><hr class="my-1"><strong class="small text-muted"><?= htmlspecialchars($currentCat) ?></strong></div>
                                    <?php endif; ?>
                                    <div class="col-md-4 col-lg-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="lab_test_ids[]" value="<?= $lt['id'] ?>" id="lab_<?= $lt['id'] ?>" <?= $checked ?>>
                                            <label class="form-check-label small" for="lab_<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-medium">Clinical Notes</label>
                                    <textarea name="clinical_notes" class="form-control" rows="3" placeholder="Any specific clinical notes or instructions for the lab..."><?= htmlspecialchars($_POST['clinical_notes'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-medium">Priority</label>
                                    <select name="lab_priority" class="form-select">
                                        <option value="routine" <?= (($_POST['lab_priority'] ?? 'routine') === 'routine') ? 'selected' : '' ?>>Routine</option>
                                        <option value="urgent" <?= (($_POST['lab_priority'] ?? '') === 'urgent') ? 'selected' : '' ?>>Urgent</option>
                                        <option value="stat" <?= (($_POST['lab_priority'] ?? '') === 'stat') ? 'selected' : '' ?>>STAT</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="referral" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-2">
                            <h6 class="mb-0"><i class="fas fa-share-alt me-2 text-primary"></i>Refer Patient</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Refer to Department</label>
                                    <select name="referred_to_department" class="form-select" id="referDept">
                                        <option value="">Select department...</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= ($existingReferral && $existingReferral['referred_to_department'] == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Refer to Doctor <small class="text-muted">(optional)</small></label>
                                    <select name="referred_to_user" class="form-select" id="referDoctor">
                                        <option value="">Select doctor...</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">Referral Reason <span class="text-danger">*</span></label>
                                    <textarea name="referral_reason" class="form-control" rows="4" placeholder="Reason for referral, clinical summary..."><?= htmlspecialchars($existingReferral['referral_reason'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="save_action" value="draft" class="btn btn-secondary"><i class="fas fa-save me-1"></i> Save Draft</button>
                <button type="submit" name="save_action" value="complete" class="btn btn-success" onclick="return confirm('Complete this consultation? This will finalize all entries.')"><i class="fas fa-check-double me-1"></i> Complete Consultation</button>
            </div>
        </form>
    </div>
</div>

<script>
let diagIndex = <?= count($existingDiagnoses) ?: 1 ?>;
let rxIndex = <?= count($existingPrescriptionItems) ?: 1 ?>;

const medicineOptions = `<?php
$opts = '';
foreach ($medicines as $m) {
    $label = htmlspecialchars($m['name'] . ($m['strength'] ? ' (' . $m['strength'] . ')' : ''));
    $opts .= '<option value=\"' . $m['id'] . '\">' . $label . '</option>';
}
echo $opts;
?>`;

function addDiagnosisRow() {
    const html = `<tr>
        <td><input type="text" name="diagnosis_name[]" class="form-control form-control-sm" required></td>
        <td><input type="text" name="diagnosis_code[]" class="form-control form-control-sm"></td>
        <td>
            <select name="diagnosis_type[]" class="form-select form-select-sm">
                <option value="primary">Primary</option>
                <option value="secondary">Secondary</option>
                <option value="differential">Differential</option>
                <option value="provisional">Provisional</option>
            </select>
        </td>
        <td><input type="text" name="diagnosis_notes[]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
    </tr>`;
    document.querySelector('#diagnosesTable tbody').insertAdjacentHTML('beforeend', html);
}

function addPrescriptionRow() {
    const html = `<tr>
        <td>
            <select name="medicine_id[]" class="form-select form-select-sm medicine-select" style="width:100%" required>
                <option value="">Select medicine...</option>
                ${medicineOptions}
            </select>
        </td>
        <td><input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="e.g. 500mg" required></td>
        <td><input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="e.g. TDS" required></td>
        <td><input type="text" name="duration[]" class="form-control form-control-sm" placeholder="e.g. 7 days" required></td>
        <td><input type="number" name="quantity[]" class="form-control form-control-sm" step="0.01" required></td>
        <td>
            <select name="route[]" class="form-select form-select-sm">
                <option value="oral">Oral</option>
                <option value="IV">IV</option>
                <option value="IM">IM</option>
                <option value="SC">SC</option>
                <option value="topical">Topical</option>
                <option value="inhaled">Inhaled</option>
                <option value="sublingual">Sublingual</option>
                <option value="rectal">Rectal</option>
                <option value="other">Other</option>
            </select>
        </td>
        <td><input type="text" name="instructions[]" class="form-control form-control-sm" placeholder="Optional notes"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
    </tr>`;
    document.querySelector('#prescriptionsTable tbody').insertAdjacentHTML('beforeend', html);
}

document.getElementById('referDept')?.addEventListener('change', function() {
    const deptId = this.value;
    const doctorSelect = document.getElementById('referDoctor');
    doctorSelect.innerHTML = '<option value="">Loading...</option>';
    if (!deptId) {
        doctorSelect.innerHTML = '<option value="">Select department first...</option>';
        return;
    }
    fetch('<?= APP_URL ?>/api/search.php?action=department_doctors&department_id=' + deptId)
        .then(r => r.json())
        .then(data => {
            doctorSelect.innerHTML = '<option value="">Select doctor...</option>';
            data.forEach(function(u) {
                doctorSelect.innerHTML += '<option value="' + u.id + '">' + u.text + '</option>';
            });
        })
        .catch(() => {
            doctorSelect.innerHTML = '<option value="">No doctors available</option>';
        });
});

<?php if ($existingReferral && $existingReferral['referred_to_department']): ?>
document.getElementById('referDept').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
