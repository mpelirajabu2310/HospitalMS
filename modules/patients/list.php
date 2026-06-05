<?php
define('PAGE_TITLE', 'All Patients');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$filterGender = sanitize($_GET['gender'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$filterRegion = intval($_GET['region_id'] ?? 0);
$filterDistrict = intval($_GET['district_id'] ?? 0);
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = [];
$params = [];
if ($search) {
    $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_number LIKE ? OR p.phone LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($filterGender) {
    $where[] = "p.gender = ?";
    $params[] = $filterGender;
}
if ($filterStatus) {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}
if ($filterRegion) {
    $where[] = "p.region_id = ?";
    $params[] = $filterRegion;
}
if ($filterDistrict) {
    $where[] = "p.district_id = ?";
    $params[] = $filterDistrict;
}
if ($dateFrom) {
    $where[] = "p.registration_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "p.registration_date <= ?";
    $params[] = $dateTo;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = Database::fetch("SELECT COUNT(*) as count FROM patients p $whereClause", $params)['count'];
$pagination = paginate($total, $page);

$patients = Database::fetchAll(
    "SELECT p.* FROM patients p $whereClause ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['limit'], $pagination['offset']])
);

$regions = Database::fetchAll("SELECT id, name FROM regions WHERE status = 'active' ORDER BY name");
$districts = $filterRegion ? Database::fetchAll("SELECT id, name FROM districts WHERE region_id = ? AND status = 'active' ORDER BY name", [$filterRegion]) : [];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>All Patients</h4>
    <a href="<?= APP_URL ?>/modules/patients/register.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Register Patient</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, number, phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="male" <?= $filterGender === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $filterGender === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $filterGender === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="deceased" <?= $filterStatus === 'deceased' ? 'selected' : '' ?>>Deceased</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">Region</label>
                    <select name="region_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $filterRegion === $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">District</label>
                    <select name="district_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Districts</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $filterDistrict === $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient #</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No patients found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($p['patient_number']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($p['photo']): ?>
                                            <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($p['photo']) ?>" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:14px"><?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                        <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></a>
                                    </div>
                                </td>
                                <td><?= ucfirst($p['gender']) ?></td>
                                <td><?= formatDate($p['date_of_birth']) ?></td>
                                <td><?= htmlspecialchars($p['phone']) ?></td>
                                <td><?= getStatusBadge($p['status']) ?></td>
                                <td class="small text-muted"><?= formatDate($p['registration_date']) ?></td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="<?= APP_URL ?>/modules/patients/register.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?= APP_URL ?>/modules/reception/checkin.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="New Visit"><i class="fas fa-plus-circle"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $pagination['prev_page'] ?>&search=<?= urlencode($search) ?>&gender=<?= urlencode($filterGender) ?>&status=<?= urlencode($filterStatus) ?>&region_id=<?= $filterRegion ?>&district_id=<?= $filterDistrict ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Previous</a>
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&gender=<?= urlencode($filterGender) ?>&status=<?= urlencode($filterStatus) ?>&region_id=<?= $filterRegion ?>&district_id=<?= $filterDistrict ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
                        <a class="page-link" href="?page=<?= $pagination['next_page'] ?>&search=<?= urlencode($search) ?>&gender=<?= urlencode($filterGender) ?>&status=<?= urlencode($filterStatus) ?>&region_id=<?= $filterRegion ?>&district_id=<?= $filterDistrict ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
