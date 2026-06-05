<?php
define('PAGE_TITLE', 'Assigned Patients');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$userDeptId = Auth::user()['department_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_note') {
        $admissionId = intval($_POST['admission_id'] ?? 0) ?: null;
        $patientId = intval($_POST['patient_id']);
        $visitId = intval($_POST['visit_id'] ?? 0) ?: null;
        $observation = sanitize($_POST['observation']);
        $careGiven = sanitize($_POST['care_given'] ?? '');
        $painLevel = intval($_POST['pain_level'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        $vitalSigns = [];
        foreach (['bp_systolic', 'bp_diastolic', 'heart_rate', 'temperature', 'respiratory_rate', 'oxygen_saturation', 'blood_sugar', 'weight'] as $vs) {
            if (!empty($_POST[$vs])) {
                $vitalSigns[$vs] = $_POST[$vs];
            }
        }

        if ($patientId && $observation) {
            Database::insert(
                "INSERT INTO nursing_notes (admission_id, patient_id, nurse_id, visit_id, observation, care_given, vital_signs, pain_level, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$admissionId, $patientId, $userId, $visitId, $observation, $careGiven, !empty($vitalSigns) ? json_encode($vitalSigns) : null, $painLevel, $notes]
            );
            logActivity($userId, 'nursing_note_added', 'nursing', "Nursing note added for patient #$patientId");
            set_flash('success', 'Nursing note recorded.');
        } else {
            set_flash('error', 'Observation is required.', 'warning');
        }
        redirect('modules/nursing/patients.php' . (!empty($_POST['patient_id']) ? '?patient_id=' . intval($_POST['patient_id']) : ''));
    }
}

$selectedPatientId = intval($_GET['patient_id'] ?? 0);

$wards = Database::fetchAll(
    "SELECT w.id, w.name, w.code FROM wards w WHERE w.department_id = ? OR ? IS NULL OR ? = '' ORDER BY w.name",
    [$userDeptId, $userDeptId, $userDeptId]
);
$wardIds = array_column($wards, 'id');
$wardList = $wardIds ? implode(',', $wardIds) : '0';

$admissions = Database::fetchAll(
    "SELECT a.*, b.bed_number, w.name as ward_name, w.code as ward_code,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number, p.date_of_birth, p.gender, p.photo,
            TIMESTAMPDIFF(DAY, a.admission_date, NOW()) as days_admitted,
            DATEDIFF(NOW(), a.admission_date) as days_since
     FROM admissions a
     JOIN patients p ON a.patient_id = p.id
     JOIN beds b ON a.bed_id = b.id
     JOIN wards w ON b.ward_id = w.id
     WHERE a.status = 'admitted'" . ($wardList !== '0' ? " AND b.ward_id IN ($wardList)" : '') . "
     ORDER BY w.name, b.bed_number",
    $wardIds
);

if ($selectedPatientId) {
    $selectedAdmission = Database::fetch(
        "SELECT a.*, b.bed_number, w.name as ward_name,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number
         FROM admissions a
         JOIN patients p ON a.patient_id = p.id
         JOIN beds b ON a.bed_id = b.id
         JOIN wards w ON b.ward_id = w.id
         WHERE a.patient_id = ? AND a.status = 'admitted'",
        [$selectedPatientId]
    );
    $nursingNotes = Database::fetchAll(
        "SELECT nn.*, CONCAT(u.first_name, ' ', u.last_name) as nurse_name
         FROM nursing_notes nn
         JOIN users u ON nn.nurse_id = u.id
         WHERE nn.patient_id = ?
         ORDER BY nn.created_at DESC",
        [$selectedPatientId]
    );
    $selectedPatient = Database::fetch("SELECT id, first_name, last_name, patient_number FROM patients WHERE id = ?", [$selectedPatientId]);
} else {
    $selectedAdmission = null;
    $nursingNotes = [];
    $selectedPatient = null;
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-procedures me-2 text-primary"></i>Assigned Patients</h4>
    <span class="badge bg-info fs-6 px-3 py-2"><i class="fas fa-bed me-1"></i> <?= count($admissions) ?> Admitted</span>
</div>

<?php if (empty($admissions)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-bed fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">No currently admitted patients in your assigned wards.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Ward / Bed</th>
                            <th>Admission Date</th>
                            <th>Diagnosis</th>
                            <th>Days</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admissions as $a): ?>
                            <tr class="<?= $selectedPatientId === $a['patient_id'] ? 'table-primary' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($a['photo']): ?>
                                            <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($a['photo']) ?>" class="rounded-circle me-2" width="36" height="36" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;font-size:14px"><?= strtoupper(substr($a['patient_name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="?patient_id=<?= $a['patient_id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($a['patient_name']) ?></a>
                                            <br><small class="text-muted"><?= htmlspecialchars($a['patient_number']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($a['ward_name']) ?></span>
                                    <br><small>Bed <?= htmlspecialchars($a['bed_number']) ?></small>
                                </td>
                                <td class="small"><?= formatDateTime($a['admission_date']) ?></td>
                                <td><small><?= htmlspecialchars(truncate($a['admitting_diagnosis'] ?? '-', 40)) ?></small></td>
                                <td><span class="badge bg-<?= $a['days_since'] > 7 ? 'warning' : 'info' ?>"><?= $a['days_since'] ?> days</span></td>
                                <td class="text-end">
                                    <a href="?patient_id=<?= $a['patient_id'] ?>" class="btn btn-sm <?= $selectedPatientId === $a['patient_id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                                        <i class="fas fa-notes-medical me-1"></i>Notes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($selectedPatient): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-notes-medical me-2 text-primary"></i>Nursing Notes: <?= htmlspecialchars($selectedPatient['first_name'] . ' ' . $selectedPatient['last_name']) ?></h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                <i class="fas fa-plus me-1"></i>Add Note
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($nursingNotes)): ?>
                <p class="text-muted text-center py-3 mb-0">No nursing notes recorded for this patient.</p>
            <?php else: ?>
                <?php foreach ($nursingNotes as $note): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= htmlspecialchars($note['nurse_name']) ?></strong>
                                <span class="badge bg-<?= $note['pain_level'] > 0 ? ($note['pain_level'] > 5 ? 'danger' : 'warning') : 'secondary' ?> ms-2">
                                    Pain: <?= $note['pain_level'] ?>/10
                                </span>
                            </div>
                            <small class="text-muted"><?= formatDateTime($note['created_at']) ?></small>
                        </div>
                        <?php if ($note['vital_signs']): ?>
                            <?php $vs = json_decode($note['vital_signs'], true); ?>
                            <div class="row g-2 mb-2 small">
                                <?php if (!empty($vs['bp_systolic']) && !empty($vs['bp_diastolic'])): ?>
                                    <div class="col-auto"><span class="text-muted">BP:</span> <?= htmlspecialchars($vs['bp_systolic']) ?>/<?= htmlspecialchars($vs['bp_diastolic']) ?> mmHg</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['heart_rate'])): ?>
                                    <div class="col-auto"><span class="text-muted">HR:</span> <?= htmlspecialchars($vs['heart_rate']) ?> bpm</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['temperature'])): ?>
                                    <div class="col-auto"><span class="text-muted">Temp:</span> <?= htmlspecialchars($vs['temperature']) ?> °C</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['respiratory_rate'])): ?>
                                    <div class="col-auto"><span class="text-muted">RR:</span> <?= htmlspecialchars($vs['respiratory_rate']) ?> /min</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['oxygen_saturation'])): ?>
                                    <div class="col-auto"><span class="text-muted">SpO2:</span> <?= htmlspecialchars($vs['oxygen_saturation']) ?>%</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['blood_sugar'])): ?>
                                    <div class="col-auto"><span class="text-muted">BS:</span> <?= htmlspecialchars($vs['blood_sugar']) ?> mmol/L</div>
                                <?php endif; ?>
                                <?php if (!empty($vs['weight'])): ?>
                                    <div class="col-auto"><span class="text-muted">Weight:</span> <?= htmlspecialchars($vs['weight']) ?> kg</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <p class="mb-1"><strong>Observation:</strong> <?= nl2br(htmlspecialchars($note['observation'])) ?></p>
                        <?php if ($note['care_given']): ?>
                            <p class="mb-1"><strong>Care Given:</strong> <?= nl2br(htmlspecialchars($note['care_given'])) ?></p>
                        <?php endif; ?>
                        <?php if ($note['notes']): ?>
                            <p class="mb-0 text-muted small"><em><?= nl2br(htmlspecialchars($note['notes'])) ?></em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="addNoteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <input type="hidden" name="patient_id" value="<?= $selectedPatient['id'] ?>">
                    <input type="hidden" name="admission_id" value="<?= $selectedAdmission['id'] ?? '' ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Add Nursing Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Pain Level (1-10)</label>
                                <select name="pain_level" class="form-select">
                                    <option value="0">None (0)</option>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <h6 class="fw-medium mb-2">Vital Signs</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label small">BP Systolic</label>
                                <input type="number" name="bp_systolic" class="form-control" placeholder="mmHg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">BP Diastolic</label>
                                <input type="number" name="bp_diastolic" class="form-control" placeholder="mmHg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Heart Rate</label>
                                <input type="number" name="heart_rate" class="form-control" placeholder="bpm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Temperature</label>
                                <input type="number" step="0.1" name="temperature" class="form-control" placeholder="°C">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Respiratory Rate</label>
                                <input type="number" name="respiratory_rate" class="form-control" placeholder="/min">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Oxygen Saturation</label>
                                <input type="number" name="oxygen_saturation" class="form-control" placeholder="%">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Blood Sugar</label>
                                <input type="number" step="0.1" name="blood_sugar" class="form-control" placeholder="mmol/L">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Weight</label>
                                <input type="number" step="0.1" name="weight" class="form-control" placeholder="kg">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observation <span class="text-danger">*</span></label>
                            <textarea name="observation" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Care Given</label>
                            <textarea name="care_given" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Note</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
