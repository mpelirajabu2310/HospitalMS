<?php
define('PAGE_TITLE', 'Queue Management');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $visitId = intval($_POST['visit_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'send_to_doctor') {
        $doctorId = intval($_POST['doctor_id'] ?? 0);
        $visit = Database::fetch("SELECT patient_id FROM visits WHERE id = ?", [$visitId]);
        if ($visit && $doctorId) {
            Database::query("UPDATE visits SET status = 'in_consultation', referred_to = ? WHERE id = ?", [$doctorId, $visitId]);
            createNotification($doctorId, 'visit', 'Patient Ready', 'A patient has been sent for consultation.', '/modules/doctor/consultations.php', 'visit', $visitId);
            logActivity($userId, 'send_to_doctor', 'reception', "Sent visit #$visitId to doctor #$doctorId");
            set_flash('success', 'Patient sent to doctor.', 'success');
        }
    } elseif ($action === 'mark_completed') {
        Database::query("UPDATE visits SET status = 'completed', checked_out_at = NOW() WHERE id = ?", [$visitId]);
        logActivity($userId, 'complete_visit', 'reception', "Completed visit #$visitId");
        set_flash('success', 'Visit marked as completed.', 'success');
    } elseif ($action === 'send_to_lab') {
        Database::query("UPDATE visits SET status = 'in_laboratory' WHERE id = ?", [$visitId]);
        set_flash('success', 'Patient sent to laboratory.', 'success');
    } elseif ($action === 'send_to_pharmacy') {
        Database::query("UPDATE visits SET status = 'in_pharmacy' WHERE id = ?", [$visitId]);
        set_flash('success', 'Patient sent to pharmacy.', 'success');
    }
    redirect('/modules/reception/queue.php');
}

$today = date('Y-m-d');
$queue = Database::fetchAll(
    "SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name
     FROM visits v
     JOIN patients p ON v.patient_id = p.id
     LEFT JOIN users u ON v.referred_to = u.id
     WHERE v.visit_date = ? AND v.status NOT IN ('completed', 'cancelled')
     ORDER BY FIELD(v.status, 'waiting', 'in_consultation', 'in_laboratory', 'in_pharmacy', 'admitted'), v.created_at ASC",
    [$today]
);

$waitingCount = 0;
$consultationCount = 0;
$labCount = 0;
$pharmacyCount = 0;
foreach ($queue as $v) {
    if ($v['status'] === 'waiting') $waitingCount++;
    elseif ($v['status'] === 'in_consultation') $consultationCount++;
    elseif ($v['status'] === 'in_laboratory') $labCount++;
    elseif ($v['status'] === 'in_pharmacy') $pharmacyCount++;
}

$queueNumber = 0;

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-list-ol me-2 text-primary"></i>Queue Management</h4>
    <span class="text-muted small"><i class="fas fa-sync-alt me-1"></i> Auto-refreshes every 30s</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $waitingCount ?></h3><small>Waiting</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $consultationCount ?></h3><small>In Consultation</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-secondary text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $labCount ?></h3><small>In Lab</small></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center py-3"><h3 class="mb-0"><?= $pharmacyCount ?></h3><small>In Pharmacy</small></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Visit #</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Waiting</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($queue)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No patients in queue today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($queue as $v): $queueNumber++; ?>
                            <tr>
                                <td class="fw-medium"><?= $queueNumber ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $v['patient_id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($v['patient_name']) ?></a>
                                    <br><small class="text-muted"><?= htmlspecialchars($v['patient_number']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($v['visit_number']) ?></td>
                                <td><?= date('H:i', strtotime($v['visit_time'])) ?></td>
                                <td><?= ucfirst($v['type']) ?></td>
                                <td><?= getStatusBadge($v['status']) ?></td>
                                <td class="small text-muted"><?= timeAgo($v['checked_in_at']) ?></td>
                                <td class="text-end">
                                    <?php if ($v['status'] === 'waiting'): ?>
                                        <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#sendToDoctorModal" data-visit-id="<?= $v['id'] ?>" title="Send to Doctor"><i class="fas fa-user-md"></i></button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="sendToLab(<?= $v['id'] ?>)" title="Send to Lab"><i class="fas fa-flask"></i></button>
                                        <button class="btn btn-sm btn-outline-success me-1" onclick="sendToPharmacy(<?= $v['id'] ?>)" title="Send to Pharmacy"><i class="fas fa-pills"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="markCompleted(<?= $v['id'] ?>)" title="Mark Completed"><i class="fas fa-check-double"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="sendToDoctorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_to_doctor">
                <input type="hidden" name="visit_id" id="send_visit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-md me-2 text-primary"></i>Send to Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-medium">Select Doctor</label>
                    <select name="doctor_id" class="form-select" required>
                        <option value="">Choose doctor...</option>
                        <?php
                        $docs = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'doctor' AND u.status = 'active' ORDER BY u.first_name");
                        foreach ($docs as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="labForm" style="display:none"><?= csrf_field() ?><input type="hidden" name="action" value="send_to_lab"><input type="hidden" name="visit_id" id="lab_visit_id"></form>
<form method="POST" id="pharmacyForm" style="display:none"><?= csrf_field() ?><input type="hidden" name="action" value="send_to_pharmacy"><input type="hidden" name="visit_id" id="pharmacy_visit_id"></form>
<form method="POST" id="completeForm" style="display:none"><?= csrf_field() ?><input type="hidden" name="action" value="mark_completed"><input type="hidden" name="visit_id" id="complete_visit_id"></form>

<script>
document.getElementById('sendToDoctorModal')?.addEventListener('show.bs.modal', function(e) {
    document.getElementById('send_visit_id').value = e.relatedTarget.dataset.visitId;
});

function sendToLab(id) { if (confirm('Send this patient to laboratory?')) { document.getElementById('lab_visit_id').value = id; document.getElementById('labForm').submit(); } }
function sendToPharmacy(id) { if (confirm('Send this patient to pharmacy?')) { document.getElementById('pharmacy_visit_id').value = id; document.getElementById('pharmacyForm').submit(); } }
function markCompleted(id) { if (confirm('Mark this visit as completed?')) { document.getElementById('complete_visit_id').value = id; document.getElementById('completeForm').submit(); } }

setTimeout(function() { location.reload(); }, 30000);
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
