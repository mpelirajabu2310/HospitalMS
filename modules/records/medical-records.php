<?php
define('PAGE_TITLE', 'Medical Records');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_record') {
        $patientId = intval($_POST['patient_id']);
        $recordType = sanitize($_POST['record_type']);
        $recordDate = sanitize($_POST['record_date'] ?? date('Y-m-d'));
        $description = sanitize($_POST['description'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $attachment = null;

        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachment = uploadFile($_FILES['attachment'], 'medical_records');
        }

        if ($patientId && $recordType) {
            Database::insert(
                "INSERT INTO medical_records (patient_id, record_type, record_date, description, attachment_path, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$patientId, $recordType, $recordDate, $description, $attachment, $notes, $userId]
            );
            logActivity($userId, 'medical_record_added', 'records', "Medical record added for patient #$patientId");
            set_flash('success', 'Medical record added successfully.');
        } else {
            set_flash('error', 'Patient and record type are required.', 'warning');
        }
        redirect('modules/records/medical-records.php' . (!empty($_POST['patient_id']) ? '?patient_id=' . intval($_POST['patient_id']) : ''));
    }

    if ($action === 'delete_record') {
        $recordId = intval($_POST['record_id']);
        $record = Database::fetch("SELECT * FROM medical_records WHERE id = ?", [$recordId]);
        if ($record) {
            if ($record['attachment_path']) {
                $filePath = UPLOAD_PATH . '/' . $record['attachment_path'];
                if (file_exists($filePath)) unlink($filePath);
            }
            Database::query("DELETE FROM medical_records WHERE id = ?", [$recordId]);
            logActivity($userId, 'medical_record_deleted', 'records', "Medical record #$recordId deleted");
            set_flash('success', 'Medical record deleted.');
        }
        redirect('modules/records/medical-records.php' . (!empty($_POST['patient_id']) ? '?patient_id=' . intval($_POST['patient_id']) : ''));
    }
}

$selectedPatientId = intval($_GET['patient_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$filterType = sanitize($_GET['record_type'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$foundPatients = [];
if ($search) {
    $term = "%$search%";
    $foundPatients = Database::fetchAll(
        "SELECT id, patient_number, first_name, last_name, date_of_birth, gender, phone
         FROM patients
         WHERE patient_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
         ORDER BY last_name ASC LIMIT 20",
        [$term, $term, $term, $term]
    );
}

$records = [];
$patientInfo = null;
if ($selectedPatientId) {
    $patientInfo = Database::fetch("SELECT * FROM patients WHERE id = ?", [$selectedPatientId]);

    $where = ["mr.patient_id = ?"];
    $params = [$selectedPatientId];

    if ($filterType) {
        $where[] = "mr.record_type = ?";
        $params[] = $filterType;
    }
    if ($dateFrom) {
        $where[] = "mr.record_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "mr.record_date <= ?";
        $params[] = $dateTo;
    }

    $records = Database::fetchAll(
        "SELECT mr.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
         FROM medical_records mr
         JOIN users u ON mr.created_by = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY mr.record_date DESC, mr.created_at DESC",
        $params
    );
}

$recordTypes = ['consultation', 'lab_result', 'prescription', 'admission', 'discharge', 'referral', 'vaccination', 'allergy', 'surgery', 'imaging', 'other'];
$patients = Database::fetchAll("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name ASC LIMIT 200");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-folder-open me-2 text-primary"></i>Medical Records</h4>
    <?php if ($selectedPatientId): ?>
        <div>
            <button type="button" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                <i class="fas fa-plus me-1"></i>Add Record
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printSummary()">
                <i class="fas fa-print me-1"></i>Print Summary
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium small">Search Patient</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Name, Patient #, Phone..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <?php if ($selectedPatientId): ?>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">Record Type</label>
                    <select name="record_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <?php foreach ($recordTypes as $rt): ?>
                            <option value="<?= $rt ?>" <?= $filterType === $rt ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $rt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i></button>
                </div>
                <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($search && !empty($foundPatients)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-2">
            <strong>Patient Search Results</strong>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($foundPatients as $p): ?>
                <a href="?patient_id=<?= $p['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($p['patient_number']) ?> | <?= ucfirst($p['gender']) ?> | <?= date('d M Y', strtotime($p['date_of_birth'])) ?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($p['phone']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($search && empty($foundPatients)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-4">
            <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
            <p class="text-muted mb-0">No patients found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($patientInfo): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div>
                    <h5 class="mb-1"><?= htmlspecialchars($patientInfo['first_name'] . ' ' . $patientInfo['last_name']) ?></h5>
                    <small class="text-muted">
                        <?= htmlspecialchars($patientInfo['patient_number']) ?> |
                        <?= ucfirst($patientInfo['gender']) ?> |
                        <?= date('d M Y', strtotime($patientInfo['date_of_birth'])) ?>
                        (<?= date_diff(date_create($patientInfo['date_of_birth']), date_create('today'))->y ?> yrs) |
                        <?= htmlspecialchars($patientInfo['phone']) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="recordsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Attachment</th>
                            <th>Added By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No records found for this patient.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td class="small"><?= formatDate($r['record_date']) ?></td>
                                    <td><span class="badge bg-info"><?= ucwords(str_replace('_', ' ', $r['record_type'])) ?></span></td>
                                    <td><?= htmlspecialchars(truncate($r['description'] ?? '-', 80)) ?></td>
                                    <td>
                                        <?php if ($r['attachment_path']): ?>
                                            <a href="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($r['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-download"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($r['created_by_name']) ?></small></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-info me-1" title="View Details"
                                            data-bs-toggle="modal" data-bs-target="#viewRecordModal"
                                            data-type="<?= ucwords(str_replace('_', ' ', $r['record_type'])) ?>"
                                            data-date="<?= formatDate($r['record_date']) ?>"
                                            data-description="<?= htmlspecialchars($r['description'] ?? '') ?>"
                                            data-notes="<?= htmlspecialchars($r['notes'] ?? '') ?>"
                                            data-attachment="<?= htmlspecialchars($r['attachment_path'] ?? '') ?>"
                                            data-created-by="<?= htmlspecialchars($r['created_by_name']) ?>"
                                            data-created-at="<?= formatDateTime($r['created_at']) ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this record?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_record">
                                            <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-medical me-2 text-primary"></i>Record Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="recordDetailsBody">
                    <div class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_record">
                    <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Add Medical Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Record Type <span class="text-danger">*</span></label>
                            <select name="record_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach ($recordTypes as $rt): ?>
                                    <option value="<?= $rt ?>"><?= ucwords(str_replace('_', ' ', $rt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Record Date</label>
                            <input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachment (PDF, Image, DOC)</label>
                            <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <small class="text-muted">Max 5MB</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="printArea" style="display:none">
        <div style="padding:20px;font-family:system-ui">
            <h3 style="text-align:center;margin-bottom:5px"><?= APP_NAME ?></h3>
            <p style="text-align:center;color:var(--text-muted);margin-top:0">Patient Medical Records Summary</p>
            <hr>
            <p><strong>Patient:</strong> <?= htmlspecialchars($patientInfo['first_name'] . ' ' . $patientInfo['last_name']) ?></p>
            <p><strong>Patient #:</strong> <?= htmlspecialchars($patientInfo['patient_number']) ?></p>
            <p><strong>DOB:</strong> <?= formatDate($patientInfo['date_of_birth']) ?> | <strong>Gender:</strong> <?= ucfirst($patientInfo['gender']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($patientInfo['phone']) ?> | <strong>Blood Group:</strong> <?= $patientInfo['blood_group'] ?? 'N/A' ?></p>
            <hr>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#f0f0f0">
                        <th style="padding:8px;border:1px solid var(--border-color);text-align:left">Date</th>
                        <th style="padding:8px;border:1px solid var(--border-color);text-align:left">Type</th>
                        <th style="padding:8px;border:1px solid var(--border-color);text-align:left">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td style="padding:6px;border:1px solid var(--border-color)"><?= formatDate($r['record_date']) ?></td>
                            <td style="padding:6px;border:1px solid var(--border-color)"><?= ucwords(str_replace('_', ' ', $r['record_type'])) ?></td>
                            <td style="padding:6px;border:1px solid var(--border-color)"><?= htmlspecialchars($r['description'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="3" style="padding:8px;border:1px solid var(--border-color);text-align:center;color:var(--text-muted)">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="text-align:center;color:var(--text-muted);margin-top:20px;font-size:12px">Generated on <?= date('d M Y H:i') ?></p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var viewModal = document.getElementById('viewRecordModal');
        viewModal.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            var html = '<div class="mb-3"><label class="fw-medium small text-muted">Record Type</label><p class="mb-0"><span class="badge bg-info fs-6">' + btn.getAttribute('data-type') + '</span></p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Date</label><p class="mb-0">' + btn.getAttribute('data-date') + '</p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Description</label><p class="mb-0">' + (btn.getAttribute('data-description') || '-') + '</p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Notes</label><p class="mb-0">' + (btn.getAttribute('data-notes') || '-') + '</p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Attachment</label><p class="mb-0">' + (btn.getAttribute('data-attachment') ? '<a href="<?= APP_URL ?>/assets/uploads/' + btn.getAttribute('data-attachment') + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-download"></i> View Attachment</a>' : '-') + '</p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Added By</label><p class="mb-0">' + btn.getAttribute('data-created-by') + '</p></div>' +
                '<div class="mb-3"><label class="fw-medium small text-muted">Created At</label><p class="mb-0">' + btn.getAttribute('data-created-at') + '</p></div>';
            document.getElementById('recordDetailsBody').innerHTML = html;
        });
    });

    function printSummary() {
        var printContents = document.getElementById('printArea').innerHTML;
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>Patient Medical Records</title></head><body>' + printContents + '</body></html>');
        win.document.close();
        win.focus();
        win.print();
    }
    </script>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
