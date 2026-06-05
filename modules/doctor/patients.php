<?php
define('PAGE_TITLE', 'My Patients');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$statusFilter = $_GET['status'] ?? '';

$where = "v.referred_to = ? AND v.visit_date = CURDATE() AND v.status != 'completed'";
$params = [$userId];
if ($statusFilter && $statusFilter !== 'all') {
    $where .= " AND v.status = ?";
    $params[] = $statusFilter;
}

$patients = Database::fetchAll(
    "SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_number,
            p.date_of_birth, p.gender, p.phone, p.photo,
            TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
     FROM visits v
     JOIN patients p ON v.patient_id = p.id
     WHERE $where
     ORDER BY FIELD(v.status, 'waiting', 'in_consultation', 'in_laboratory', 'in_pharmacy'), v.checked_in_at ASC",
    $params
);

$completedToday = Database::fetch(
    "SELECT COUNT(*) as count FROM consultations c
     JOIN visits v ON c.visit_id = v.id
     WHERE c.doctor_id = ? AND v.visit_date = CURDATE() AND c.status = 'completed'",
    [$userId]
)['count'];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-stethoscope me-2 text-primary"></i>My Patients</h4>
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-success fs-6 px-3 py-2">
            <i class="fas fa-check-circle me-1"></i> <?= $completedToday ?> Completed Today
        </span>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small mb-0">Filter by Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="waiting" <?= $statusFilter === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                    <option value="in_consultation" <?= $statusFilter === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                    <option value="in_laboratory" <?= $statusFilter === 'in_laboratory' ? 'selected' : '' ?>>In Laboratory</option>
                    <option value="in_pharmacy" <?= $statusFilter === 'in_pharmacy' ? 'selected' : '' ?>>In Pharmacy</option>
                    <option value="admitted" <?= $statusFilter === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="patientsTable">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Visit #</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Check-In</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Waiting</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No patients assigned today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($p['photo']): ?>
                                            <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($p['photo']) ?>" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:14px"><?= strtoupper(substr($p['patient_name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['patient_id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($p['patient_name']) ?></a>
                                            <br><small class="text-muted"><?= htmlspecialchars($p['patient_number']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-medium"><?= htmlspecialchars($p['visit_number']) ?></td>
                                <td><?= $p['age'] ?> yrs</td>
                                <td><?= ucfirst($p['gender']) ?></td>
                                <td><?= date('H:i', strtotime($p['checked_in_at'])) ?></td>
                                <td><span class="badge bg-info"><?= ucfirst($p['type']) ?></span></td>
                                <td><?= getStatusBadge($p['status']) ?></td>
                                <td class="small text-muted"><?= timeAgo($p['checked_in_at']) ?></td>
                                <td class="text-end">
                                    <?php if ($p['status'] === 'waiting' || $p['status'] === 'in_consultation'): ?>
                                        <a href="<?= APP_URL ?>/modules/doctor/consultation.php?visit_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-notes-medical me-1"></i> Consult
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['patient_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-history"></i>
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

<script>
$(document).ready(function() {
    $('#patientsTable').DataTable({
        pageLength: 25,
        order: [[7, 'asc']],
        columnDefs: [{ orderable: false, targets: [8] }]
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
