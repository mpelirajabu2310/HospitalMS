<?php
define('PAGE_TITLE', 'Discharges');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'discharge_patient') {
        $admissionId = intval($_POST['admission_id']);
        $dischargeType = sanitize($_POST['discharge_type'] ?? 'recovered');
        $dischargeSummary = sanitize($_POST['discharge_summary'] ?? '');
        $dischargeCondition = sanitize($_POST['discharge_condition'] ?? '');
        $followUpInstructions = sanitize($_POST['follow_up_instructions'] ?? '');
        $followUpDate = sanitize($_POST['follow_up_date'] ?? '') ?: null;

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            $admission = Database::fetch(
                "SELECT a.*, p.first_name, p.last_name, p.id as patient_id
                 FROM admissions a
                 JOIN patients p ON a.patient_id = p.id
                 WHERE a.id = ? AND a.status = 'admitted'
                 FOR UPDATE",
                [$admissionId]
            );

            if (!$admission) {
                throw new Exception('Admission not found or patient already discharged.');
            }

            Database::insert(
                "INSERT INTO discharges (admission_id, discharge_date, discharge_type, discharge_summary, discharge_condition, follow_up_instructions, follow_up_date, discharged_by)
                 VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)",
                [$admissionId, $dischargeType, $dischargeSummary, $dischargeCondition, $followUpInstructions, $followUpDate, $userId]
            );

            Database::query("UPDATE admissions SET status = 'discharged' WHERE id = ?", [$admissionId]);

            Database::query("UPDATE beds SET status = 'available' WHERE id = ?", [$admission['bed_id']]);

            Database::insert(
                "INSERT INTO medical_records (patient_id, record_type, record_date, description, created_by)
                 VALUES (?, 'discharge', CURDATE(), ?, ?)",
                [$admission['patient_id'], "Discharged: $dischargeType - $dischargeSummary", $userId]
            );

            $existingInvoice = Database::fetch(
                "SELECT id FROM invoices WHERE admission_id = ? AND status NOT IN ('cancelled','refunded')",
                [$admissionId]
            );

            if (!$existingInvoice) {
                $bedDays = max(1, ceil((time() - strtotime($admission['admission_date'])) / 86400));
                $bed = Database::fetch("SELECT price_per_day, bed_number, ward_id FROM beds WHERE id = ?", [$admission['bed_id']]);
                $ward = Database::fetch("SELECT name FROM wards WHERE id = ?", [$bed['ward_id']]);
                $roomCharge = $bedDays * $bed['price_per_day'];

                if ($roomCharge > 0) {
                    $invoiceNumber = generateInvoiceNumber();
                    $invoiceId = Database::insert(
                        "INSERT INTO invoices (invoice_number, patient_id, admission_id, invoice_date, subtotal, total, status, notes, created_by)
                         VALUES (?, ?, ?, CURDATE(), ?, ?, 'pending', ?, ?)",
                        [$invoiceNumber, $admission['patient_id'], $admissionId, $roomCharge, $roomCharge, "Auto-generated for admission discharge", $userId]
                    );

                    Database::insert(
                        "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total)
                         VALUES (?, ?, ?, ?, ?)",
                        [$invoiceId, "Ward: {$ward['name']} - Bed: {$bed['bed_number']} ({$bedDays} day(s))", 1, $roomCharge, $roomCharge]
                    );
                }
            }

            $db->commit();
            logActivity($userId, 'patient_discharged', 'admission', "Patient #{$admission['patient_id']} discharged ($dischargeType)");
            set_flash('success', 'Patient discharged successfully.');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Discharge failed: ' . $e->getMessage(), 'danger');
        }
        redirect('modules/admission/discharges.php');
    }
}

$singleAdmissionId = intval($_GET['admission_id'] ?? 0);

$activeAdmissions = Database::fetchAll(
    "SELECT a.*, p.first_name, p.last_name, p.patient_number, p.phone,
            w.name as ward_name, w.code as ward_code,
            b.bed_number, b.bed_type, b.price_per_day,
            u.first_name as d_first, u.last_name as d_last,
            DATEDIFF(NOW(), a.admission_date) as days_admitted,
            (SELECT COUNT(*) FROM discharges WHERE admission_id = a.id) as has_discharge
     FROM admissions a
     JOIN patients p ON a.patient_id = p.id
     JOIN beds b ON a.bed_id = b.id
     JOIN wards w ON b.ward_id = w.id
     JOIN users u ON a.admitting_doctor_id = u.id
     WHERE a.status = 'admitted'
     ORDER BY a.admission_date ASC"
);

$dischargedPatients = Database::fetchAll(
    "SELECT d.*, a.patient_id, p.first_name, p.last_name, p.patient_number,
            w.name as ward_name, b.bed_number,
            u.first_name as d_first, u.last_name as d_last,
            u2.first_name as dis_first, u2.last_name as dis_last
     FROM discharges d
     JOIN admissions a ON d.admission_id = a.id
     JOIN patients p ON a.patient_id = p.id
     JOIN beds b ON a.bed_id = b.id
     JOIN wards w ON b.ward_id = w.id
     JOIN users u ON a.admitting_doctor_id = u.id
     JOIN users u2 ON d.discharged_by = u2.id
     ORDER BY d.discharge_date DESC
     LIMIT 50"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-sign-out-alt me-2 text-primary"></i>Discharges</h4>
</div>

<ul class="nav nav-tabs mb-4" id="dischargeTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#activeTab">
            <i class="fas fa-procedures me-1"></i>Ready for Discharge (<?= count($activeAdmissions) ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#historyTab">
            <i class="fas fa-history me-1"></i>Discharge History
        </a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="activeTab">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Admitted</th>
                                <th>Days</th>
                                <th>Ward</th>
                                <th>Bed</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeAdmissions)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No patients ready for discharge.</td></tr>
                            <?php else: ?>
                                <?php foreach ($activeAdmissions as $a): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $a['patient_id'] ?>" class="text-decoration-none fw-medium">
                                                <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                                            </a>
                                            <br><small class="text-muted"><?= htmlspecialchars($a['patient_number']) ?></small>
                                        </td>
                                        <td class="small"><?= formatDate($a['admission_date']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $a['days_admitted'] ?>d</span></td>
                                        <td><?= htmlspecialchars($a['ward_name']) ?></td>
                                        <td><code><?= htmlspecialchars($a['bed_number']) ?></code></td>
                                        <td class="small"><?= htmlspecialchars($a['d_first'] . ' ' . $a['d_last']) ?></td>
                                        <td class="small text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                            <?= htmlspecialchars($a['admitting_diagnosis'] ?? '-') ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-success"
                                                data-bs-toggle="modal" data-bs-target="#dischargeModal"
                                                data-id="<?= $a['id'] ?>"
                                                data-patient="<?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>"
                                                data-patient-number="<?= htmlspecialchars($a['patient_number']) ?>"
                                                data-ward="<?= htmlspecialchars($a['ward_name']) ?>"
                                                data-bed="<?= htmlspecialchars($a['bed_number']) ?>"
                                                data-doctor="<?= htmlspecialchars($a['d_first'] . ' ' . $a['d_last']) ?>"
                                                data-diagnosis="<?= htmlspecialchars($a['admitting_diagnosis'] ?? '') ?>"
                                                data-days="<?= $a['days_admitted'] ?>"
                                                data-admitted="<?= formatDate($a['admission_date']) ?>">
                                                <i class="fas fa-sign-out-alt me-1"></i>Discharge
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="historyTab">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Discharge Date</th>
                                <th>Type</th>
                                <th>Condition</th>
                                <th>Ward</th>
                                <th>Discharged By</th>
                                <th>Follow-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dischargedPatients)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No discharge records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($dischargedPatients as $d): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $d['patient_id'] ?>" class="text-decoration-none fw-medium">
                                                <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
                                            </a>
                                            <br><small class="text-muted"><?= htmlspecialchars($d['patient_number']) ?></small>
                                        </td>
                                        <td class="small"><?= formatDateTime($d['discharge_date']) ?></td>
                                        <td><?= getStatusBadge(str_replace('_', ' ', $d['discharge_type'])) ?></td>
                                        <td class="small"><?= htmlspecialchars($d['discharge_condition'] ?? '-') ?></td>
                                        <td class="small"><?= htmlspecialchars($d['ward_name'] ?? '-') ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($d['dis_first'] . ' ' . $d['dis_last']) ?></td>
                                        <td class="small"><?= $d['follow_up_date'] ? formatDate($d['follow_up_date']) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dischargeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="discharge_patient">
                <input type="hidden" name="admission_id" id="dischargeAdmissionId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2 text-success"></i>Discharge Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <div class="row small">
                                <div class="col-md-3"><span class="text-muted">Patient:</span><br><strong id="disPatient"></strong></div>
                                <div class="col-md-3"><span class="text-muted">Ward / Bed:</span><br><strong id="disWardBed"></strong></div>
                                <div class="col-md-3"><span class="text-muted">Doctor:</span><br><strong id="disDoctor"></strong></div>
                                <div class="col-md-3"><span class="text-muted">Days Admitted:</span><br><strong id="disDays"></strong></div>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-muted">Diagnosis:</span> <span id="disDiagnosis"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discharge Type <span class="text-danger">*</span></label>
                            <select name="discharge_type" class="form-select" required>
                                <option value="recovered">Recovered</option>
                                <option value="referred">Referred</option>
                                <option value="absconded">Absconded</option>
                                <option value="deceased">Deceased</option>
                                <option value="against_medical_advice">Against Medical Advice</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discharge Condition</label>
                            <input type="text" name="discharge_condition" class="form-control" placeholder="e.g. Stable, Critical...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discharge Summary <span class="text-danger">*</span></label>
                        <textarea name="discharge_summary" class="form-control" rows="4" required placeholder="Summary of treatment, outcome, and recommendations..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Follow-up Instructions</label>
                            <textarea name="follow_up_instructions" class="form-control" rows="3" placeholder="Any follow-up care instructions..."></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control">
                            <div class="form-text small text-muted">Schedule follow-up appointment</div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        On discharge: bed will be marked available, a medical record will be created,
                        and a final invoice will be generated automatically if not already existing.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirm Discharge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dischargeModal = document.getElementById('dischargeModal');
    dischargeModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('dischargeAdmissionId').value = btn.getAttribute('data-id');
        document.getElementById('disPatient').textContent = btn.getAttribute('data-patient') + ' (' + btn.getAttribute('data-patient-number') + ')';
        document.getElementById('disWardBed').textContent = btn.getAttribute('data-ward') + ' - ' + btn.getAttribute('data-bed');
        document.getElementById('disDoctor').textContent = btn.getAttribute('data-doctor');
        document.getElementById('disDays').textContent = btn.getAttribute('data-days') + ' days (since ' + btn.getAttribute('data-admitted') + ')';
        document.getElementById('disDiagnosis').textContent = btn.getAttribute('data-diagnosis') || 'N/A';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
