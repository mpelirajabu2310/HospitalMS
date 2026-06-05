<?php
define('PAGE_TITLE', 'Referrals');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$userDeptId = Auth::user()['department_id'];
$activeTab = $_GET['tab'] ?? 'sent';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $referralId = intval($_POST['referral_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $referral = Database::fetch("SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name FROM referrals r JOIN patients p ON r.patient_id = p.id WHERE r.id = ?", [$referralId]);

    if ($referral) {
        if ($action === 'accept') {
            Database::query("UPDATE referrals SET status = 'accepted', responded_at = NOW() WHERE id = ?", [$referralId]);
            createNotification($referral['referred_by'], 'referral_response', 'Referral Accepted', "Your referral for {$referral['patient_name']} has been accepted.", '/modules/doctor/referrals.php', 'referral', $referralId);
            logActivity($userId, 'accept_referral', 'doctor', "Accepted referral #$referralId");
            set_flash('success', 'Referral accepted.', 'success');
        } elseif ($action === 'reject') {
            $responseNotes = sanitize($_POST['rejection_reason'] ?? '');
            if (empty($responseNotes)) {
                set_flash('error', 'Please provide a reason for rejection.', 'error');
            } else {
                Database::query("UPDATE referrals SET status = 'rejected', response_notes = ?, responded_at = NOW() WHERE id = ?", [$responseNotes, $referralId]);
                createNotification($referral['referred_by'], 'referral_response', 'Referral Rejected', "Your referral for {$referral['patient_name']} has been rejected. Reason: $responseNotes", '/modules/doctor/referrals.php', 'referral', $referralId);
                logActivity($userId, 'reject_referral', 'doctor', "Rejected referral #$referralId");
                set_flash('success', 'Referral rejected.', 'success');
            }
        }
    }
    redirect('/modules/doctor/referrals.php?tab=' . $activeTab);
}

$sentReferrals = Database::fetchAll(
    "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            d.name as to_department_name,
            CONCAT(u.first_name, ' ', u.last_name) as to_doctor_name
     FROM referrals r
     JOIN patients p ON r.patient_id = p.id
     JOIN departments d ON r.referred_to_department = d.id
     LEFT JOIN users u ON r.referred_to_user = u.id
     WHERE r.referred_by = ?
     ORDER BY r.created_at DESC",
    [$userId]
);

$receivedReferrals = Database::fetchAll(
    "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            d.name as from_department_name,
            CONCAT(u.first_name, ' ', u.last_name) as from_doctor_name,
            rd.name as to_department_name
     FROM referrals r
     JOIN patients p ON r.patient_id = p.id
     JOIN departments d ON r.referred_from_department = d.id
     JOIN departments rd ON r.referred_to_department = rd.id
     LEFT JOIN users u ON r.referred_by = u.id
     WHERE (r.referred_to_user = ? OR (r.referred_to_user IS NULL AND r.referred_to_department = ?))
     ORDER BY r.created_at DESC",
    [$userId, $userDeptId]
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-share-alt me-2 text-primary"></i>Referrals</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'sent' ? 'active' : '' ?>" href="?tab=sent">
            <i class="fas fa-paper-plane me-1"></i> Sent
            <span class="badge bg-secondary ms-1"><?= count($sentReferrals) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'received' ? 'active' : '' ?>" href="?tab=received">
            <i class="fas fa-inbox me-1"></i> Received
            <span class="badge bg-secondary ms-1"><?= count($receivedReferrals) ?></span>
        </a>
    </li>
</ul>

<?php if ($activeTab === 'sent'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>To Department</th>
                        <th>To Doctor</th>
                        <th>Reason</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sentReferrals)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No sent referrals.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sentReferrals as $r): ?>
                            <tr>
                                <td><?= formatDateTime($r['created_at'], 'd M Y H:i') ?></td>
                                <td class="fw-medium">
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $r['patient_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['patient_name']) ?></a>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['patient_number']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['to_department_name']) ?></td>
                                <td><?= htmlspecialchars($r['to_doctor_name'] ?? '-') ?></td>
                                <td><span title="<?= htmlspecialchars($r['referral_reason']) ?>"><?= htmlspecialchars(truncate($r['referral_reason'], 50)) ?></span></td>
                                <td><?= getStatusBadge($r['priority']) ?></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars(truncate($r['response_notes'] ?? '-', 40)) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'received'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>From</th>
                        <th>Reason</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receivedReferrals)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No received referrals.</td></tr>
                    <?php else: ?>
                        <?php foreach ($receivedReferrals as $r): ?>
                            <tr>
                                <td><?= formatDateTime($r['created_at'], 'd M Y H:i') ?></td>
                                <td class="fw-medium">
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $r['patient_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['patient_name']) ?></a>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['patient_number']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($r['from_department_name']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['from_doctor_name'] ?? '') ?></small>
                                </td>
                                <td><span title="<?= htmlspecialchars($r['referral_reason']) ?>"><?= htmlspecialchars(truncate($r['referral_reason'], 50)) ?></span></td>
                                <td><?= getStatusBadge($r['priority']) ?></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#acceptModal" data-referral-id="<?= $r['id'] ?>">
                                            <i class="fas fa-check me-1"></i> Accept
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-referral-id="<?= $r['id'] ?>">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= htmlspecialchars(truncate($r['response_notes'] ?? '-', 30)) ?></span>
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

<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="referral_id" id="acceptReferralId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>Accept Referral</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to accept this referral?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Accept</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="referral_id" id="rejectReferralId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>Reject Referral</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Please provide a reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('acceptModal')?.addEventListener('show.bs.modal', function(e) {
    document.getElementById('acceptReferralId').value = e.relatedTarget.dataset.referralId;
});
document.getElementById('rejectModal')?.addEventListener('show.bs.modal', function(e) {
    document.getElementById('rejectReferralId').value = e.relatedTarget.dataset.referralId;
});
</script>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
