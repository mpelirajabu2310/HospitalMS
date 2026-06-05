<?php
define('PAGE_TITLE', 'Search Patients');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$search = trim($_GET['q'] ?? '');
$results = [];
if ($search) {
    $term = "%$search%";
    $results = Database::fetchAll(
        "SELECT * FROM patients WHERE patient_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR id_number LIKE ? ORDER BY first_name LIMIT 50",
        [$term, $term, $term, $term, $term]
    );
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-search me-2 text-primary"></i>Search Patients</h4>
    <a href="<?= APP_URL ?>/modules/patients/register.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Register Patient</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-10">
                <label class="form-label fw-medium small">Search by Patient Number, Name, Phone, or ID Number</label>
                <input type="text" name="q" class="form-control form-control-lg" placeholder="Type to search..." value="<?= htmlspecialchars($search) ?>" autofocus>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($search): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <strong><?= count($results) ?></strong> result(s) for "<em><?= htmlspecialchars($search) ?></em>"
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Patient #</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>ID Number</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No patients matching your search.</td></tr>
                        <?php else: ?>
                            <?php foreach ($results as $p): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($p['patient_number']) ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-medium">
                                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($p['phone']) ?></td>
                                    <td><?= htmlspecialchars($p['id_number'] ?? '-') ?></td>
                                    <td><?= ucfirst($p['gender']) ?></td>
                                    <td><?= getStatusBadge($p['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View Profile"><i class="fas fa-eye"></i></a>
                                        <a href="<?= APP_URL ?>/modules/reception/checkin.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="New Visit"><i class="fas fa-plus-circle"></i></a>
                                        <a href="<?= APP_URL ?>/modules/reception/appointments.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Book Appointment"><i class="fas fa-calendar-plus"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
