<?php
define('PAGE_TITLE', 'Admissions');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'new_admission') {
        $patientId = intval($_POST['patient_id']);
        $visitId = intval($_POST['visit_id'] ?? 0) ?: null;
        $bedId = intval($_POST['bed_id']);
        $admissionType = sanitize($_POST['admission_type'] ?? 'emergency');
        $admittingDoctorId = intval($_POST['admitting_doctor_id']);
        $admittingDiagnosis = sanitize($_POST['admitting_diagnosis'] ?? '');
        $expectedDischarge = sanitize($_POST['expected_discharge_date'] ?? '') ?: null;
        $insuranceProvider = sanitize($_POST['insurance_provider'] ?? '');
        $insurancePolicyNo = sanitize($_POST['insurance_policy_no'] ?? '');
        $insuranceCoverage = floatval($_POST['insurance_coverage'] ?? 0) ?: null;

        if ($patientId && $bedId && $admittingDoctorId) {
            try {
                $db = Database::getInstance()->getConnection();
                $db->beginTransaction();

                $bed = Database::fetch("SELECT id, status FROM beds WHERE id = ? FOR UPDATE", [$bedId]);
                if (!$bed || $bed['status'] !== 'available') {
                    throw new Exception('Selected bed is not available.');
                }

                $admissionId = Database::insert(
                    "INSERT INTO admissions (patient_id, visit_id, bed_id, admission_date, admission_type, admitting_doctor_id, admitting_diagnosis, expected_discharge_date, insurance_provider, insurance_policy_no, insurance_coverage, status)
                     VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'admitted')",
                    [$patientId, $visitId, $bedId, $admissionType, $admittingDoctorId, $admittingDiagnosis, $expectedDischarge, $insuranceProvider, $insurancePolicyNo, $insuranceCoverage]
                );

                Database::query("UPDATE beds SET status = 'occupied' WHERE id = ?", [$bedId]);

                Database::query(
                    "INSERT INTO medical_records (patient_id, record_type, record_date, description, created_by)
                     VALUES (?, 'admission', CURDATE(), ?, ?)",
                    [$patientId, "Admitted: $admittingDiagnosis", $userId]
                );

                $db->commit();
                logActivity($userId, 'patient_admitted', 'admission', "Patient #$patientId admitted to bed #$bedId");
                set_flash('success', 'Patient admitted successfully.');
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('error', $e->getMessage(), 'danger');
            }
        } else {
            set_flash('error', 'Patient, bed, and admitting doctor are required.', 'warning');
        }
        redirect('modules/admission/admissions.php');
    }

    if ($action === 'transfer_bed') {
        $admissionId = intval($_POST['admission_id']);
        $newBedId = intval($_POST['new_bed_id']);
        $transferReason = sanitize($_POST['transfer_reason'] ?? '');

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            $admission = Database::fetch("SELECT id, bed_id, patient_id FROM admissions WHERE id = ? AND status = 'admitted'", [$admissionId]);
            if (!$admission) throw new Exception('Admission not found or already discharged.');

            $newBed = Database::fetch("SELECT id, status FROM beds WHERE id = ? FOR UPDATE", [$newBedId]);
            if (!$newBed || $newBed['status'] !== 'available') throw new Exception('New bed is not available.');

            Database::query("UPDATE beds SET status = 'available' WHERE id = ?", [$admission['bed_id']]);
            Database::query("UPDATE beds SET status = 'occupied' WHERE id = ?", [$newBedId]);
            Database::query("UPDATE admissions SET bed_id = ?, status = 'transferred' WHERE id = ?", [$newBedId, $admissionId]);

            $newAdmissionId = Database::insert(
                "INSERT INTO admissions (patient_id, bed_id, admission_date, admission_type, admitting_doctor_id, admitting_diagnosis, status)
                 VALUES (?, ?, NOW(), 'transfer', ?, ?, 'admitted')",
                [$admission['patient_id'], $newBedId, $userId, $transferReason]
            );

            $db->commit();
            logActivity($userId, 'patient_transferred', 'admission', "Patient #{$admission['patient_id']} transferred to bed #$newBedId");
            set_flash('success', 'Patient transferred successfully.');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', $e->getMessage(), 'danger');
        }
        redirect('modules/admission/admissions.php');
    }
}

$activeAdmissions = Database::fetchAll(
    "SELECT a.*, p.first_name, p.last_name, p.patient_number, p.phone,
            w.name as ward_name, w.code as ward_code,
            b.bed_number, b.bed_type,
            u.first_name as d_first, u.last_name as d_last,
            DATEDIFF(NOW(), a.admission_date) as days_admitted
     FROM admissions a
     JOIN patients p ON a.patient_id = p.id
     JOIN beds b ON a.bed_id = b.id
     JOIN wards w ON b.ward_id = w.id
     JOIN users u ON a.admitting_doctor_id = u.id
     WHERE a.status = 'admitted'
     ORDER BY a.admission_date DESC"
);

$activeCount = Database::fetch("SELECT COUNT(*) as c FROM admissions WHERE status = 'admitted'")['c'];
$availableBeds = Database::fetch("SELECT COUNT(*) as c FROM beds WHERE status = 'available'")['c'];
$dischargedToday = Database::fetch("SELECT COUNT(*) as c FROM discharges WHERE DATE(discharge_date) = CURDATE()")['c'];

$patients = Database::fetchAll("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name ASC LIMIT 200");
$doctors = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('doctor','admin','super_admin') AND u.status = 'active' ORDER BY u.first_name ASC");
$wards = Database::fetchAll("SELECT w.*, (SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'available') as available_beds FROM wards w WHERE w.status = 'active' ORDER BY w.name ASC");
$visits = Database::fetchAll(
    "SELECT v.id, v.visit_number, p.first_name, p.last_name
     FROM visits v JOIN patients p ON v.patient_id = p.id
     WHERE v.status NOT IN ('completed','cancelled') ORDER BY v.created_at DESC LIMIT 100"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-door-open me-2 text-primary"></i>Admissions</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#admissionModal">
        <i class="fas fa-plus me-1"></i>New Admission
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-primary"><?= $activeCount ?></h3>
                <small class="text-muted">Active Admissions</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-success"><?= $availableBeds ?></h3>
                <small class="text-muted">Available Beds</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-info"><?= $dischargedToday ?></h3>
                <small class="text-muted">Discharged Today</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Active Admissions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Admission Date</th>
                        <th>Days</th>
                        <th>Ward</th>
                        <th>Bed</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeAdmissions)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No active admissions.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activeAdmissions as $a): ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $a['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($a['patient_number']) ?></small>
                                </td>
                                <td class="small"><?= formatDateTime($a['admission_date']) ?></td>
                                <td><span class="badge bg-secondary"><?= $a['days_admitted'] ?>d</span></td>
                                <td><?= htmlspecialchars($a['ward_name']) ?></td>
                                <td><code><?= htmlspecialchars($a['bed_number']) ?></code></td>
                                <td class="small"><?= htmlspecialchars($a['d_first'] . ' ' . $a['d_last']) ?></td>
                                <td class="small text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['admitting_diagnosis'] ?? '-') ?></td>
                                <td><?= getStatusBadge($a['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-info me-1" title="View"
                                        data-bs-toggle="modal" data-bs-target="#viewAdmissionModal"
                                        data-id="<?= $a['id'] ?>"
                                        data-patient="<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>"
                                        data-ward="<?= htmlspecialchars($a['ward_name']) ?>"
                                        data-bed="<?= htmlspecialchars($a['bed_number']) ?>"
                                        data-doctor="<?= htmlspecialchars($a['d_first'] . ' ' . $a['d_last']) ?>"
                                        data-diagnosis="<?= htmlspecialchars($a['admitting_diagnosis'] ?? '') ?>"
                                        data-date="<?= formatDateTime($a['admission_date']) ?>"
                                        data-days="<?= $a['days_admitted'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" title="Transfer"
                                        data-bs-toggle="modal" data-bs-target="#transferModal"
                                        data-id="<?= $a['id'] ?>"
                                        data-patient="<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>"
                                        data-current-bed="<?= htmlspecialchars($a['ward_name'] . ' - ' . $a['bed_number']) ?>">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <a href="<?= APP_URL ?>/modules/admission/discharges.php?admission_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success" title="Discharge">
                                        <i class="fas fa-sign-out-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="admissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="new_admission">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>New Admission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" class="form-select select2-patient" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Visit (optional)</label>
                            <select name="visit_id" class="form-select select2-visit">
                                <option value="">No visit</option>
                                <?php foreach ($visits as $v): ?>
                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['visit_number'] . ' - ' . $v['first_name'] . ' ' . $v['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ward <span class="text-danger">*</span></label>
                            <select name="ward_id" id="wardSelect" class="form-select" required onchange="loadAvailableBeds(this.value)">
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $w): ?>
                                    <option value="<?= $w['id'] ?>" data-available="<?= $w['available_beds'] ?>">
                                        <?= htmlspecialchars($w['name']) ?> (<?= $w['available_beds'] ?> available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bed <span class="text-danger">*</span></label>
                            <select name="bed_id" id="bedSelect" class="form-select" required>
                                <option value="">Select Ward First</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Admission Type</label>
                            <select name="admission_type" class="form-select">
                                <option value="emergency">Emergency</option>
                                <option value="elective">Elective</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admitting Doctor <span class="text-danger">*</span></label>
                            <select name="admitting_doctor_id" class="form-select select2-doctor" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Expected Discharge</label>
                            <input type="date" name="expected_discharge_date" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-text small text-muted">Optional</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admitting Diagnosis</label>
                        <textarea name="admitting_diagnosis" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Insurance Information (optional)</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Provider</label>
                                    <input type="text" name="insurance_provider" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Policy No.</label>
                                    <input type="text" name="insurance_policy_no" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Coverage Limit</label>
                                    <input type="number" step="0.01" name="insurance_coverage" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Admit Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewAdmissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2 text-primary"></i>Admission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><strong>Patient:</strong> <span id="viewPatient"></span></div>
                <div class="mb-2"><strong>Ward:</strong> <span id="viewWard"></span></div>
                <div class="mb-2"><strong>Bed:</strong> <span id="viewBed"></span></div>
                <div class="mb-2"><strong>Doctor:</strong> <span id="viewDoctor"></span></div>
                <div class="mb-2"><strong>Diagnosis:</strong> <span id="viewDiagnosis"></span></div>
                <div class="mb-2"><strong>Admitted:</strong> <span id="viewDate"></span></div>
                <div class="mb-2"><strong>Days Admitted:</strong> <span id="viewDays"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="transfer_bed">
                <input type="hidden" name="admission_id" id="transferAdmissionId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt me-2 text-warning"></i>Transfer Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Patient:</strong> <span id="transferPatient"></span></p>
                    <p><strong>Current Bed:</strong> <span id="transferCurrentBed"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Select New Ward</label>
                        <select class="form-select" onchange="loadAvailableBedsForTransfer(this.value)">
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= $w['available_beds'] ?> available)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Bed <span class="text-danger">*</span></label>
                        <select name="new_bed_id" id="transferBedSelect" class="form-select" required>
                            <option value="">Select Ward First</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Transfer</label>
                        <textarea name="transfer_reason" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-exchange-alt me-1"></i>Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $currencySymbol = getSetting('currency', 'TZS'); ?>
<script>
var currencySymbol = '<?= $currencySymbol ?>';

function loadAvailableBeds(wardId) {
    var select = document.getElementById('bedSelect');
    if (!wardId) {
        select.innerHTML = '<option value="">Select Ward First</option>';
        return;
    }
    select.innerHTML = '<option value=""><i class="fas fa-spinner fa-spin"></i> Loading...</option>';
    fetch('<?= APP_URL ?>/api/admission/available-beds.php?ward_id=' + wardId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                select.innerHTML = '<option value="">' + data.error + '</option>';
                return;
            }
            var html = '<option value="">Select Bed</option>';
            data.forEach(function(b) {
                html += '<option value="' + b.id + '">' + b.bed_number + ' (' + b.bed_type + ') - ' + currencySymbol + ' ' + parseFloat(b.price_per_day).toFixed(2) + '/day</option>';
            });
            select.innerHTML = html || '<option value="">No available beds</option>';
        })
        .catch(function() {
            select.innerHTML = '<option value="">Failed to load beds</option>';
        });
}

function loadAvailableBedsForTransfer(wardId) {
    var select = document.getElementById('transferBedSelect');
    if (!wardId) {
        select.innerHTML = '<option value="">Select Ward First</option>';
        return;
    }
    select.innerHTML = '<option value="">Loading...</option>';
    fetch('<?= APP_URL ?>/api/admission/available-beds.php?ward_id=' + wardId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                select.innerHTML = '<option value="">' + data.error + '</option>';
                return;
            }
            var html = '<option value="">Select Bed</option>';
            data.forEach(function(b) {
                html += '<option value="' + b.id + '">' + b.bed_number + ' (' + b.bed_type + ')</option>';
            });
            select.innerHTML = html || '<option value="">No available beds</option>';
        })
        .catch(function() {
            select.innerHTML = '<option value="">Failed to load beds</option>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        $('.select2-patient').select2({theme: 'bootstrap-5', dropdownParent: $('#admissionModal'), width: '100%'});
        $('.select2-visit').select2({theme: 'bootstrap-5', dropdownParent: $('#admissionModal'), width: '100%'});
        $('.select2-doctor').select2({theme: 'bootstrap-5', dropdownParent: $('#admissionModal'), width: '100%'});
    }

    var viewModal = document.getElementById('viewAdmissionModal');
    viewModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('viewPatient').textContent = btn.getAttribute('data-patient');
        document.getElementById('viewWard').textContent = btn.getAttribute('data-ward');
        document.getElementById('viewBed').textContent = btn.getAttribute('data-bed');
        document.getElementById('viewDoctor').textContent = btn.getAttribute('data-doctor');
        document.getElementById('viewDiagnosis').textContent = btn.getAttribute('data-diagnosis') || 'N/A';
        document.getElementById('viewDate').textContent = btn.getAttribute('data-date');
        document.getElementById('viewDays').textContent = btn.getAttribute('data-days') + ' days';
    });

    var transferModal = document.getElementById('transferModal');
    transferModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('transferAdmissionId').value = btn.getAttribute('data-id');
        document.getElementById('transferPatient').textContent = btn.getAttribute('data-patient');
        document.getElementById('transferCurrentBed').textContent = btn.getAttribute('data-current-bed');
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
