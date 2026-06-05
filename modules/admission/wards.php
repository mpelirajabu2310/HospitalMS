<?php
define('PAGE_TITLE', 'Wards');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_ward', 'edit_ward'])) {
        $wardId = intval($_POST['ward_id'] ?? 0);
        $name = sanitize($_POST['name']);
        $code = sanitize($_POST['code'] ?? '');
        $floor = sanitize($_POST['floor'] ?? '');
        $departmentId = intval($_POST['department_id'] ?? 0) ?: null;
        $type = sanitize($_POST['type'] ?? 'general');
        $totalBeds = intval($_POST['total_beds'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');

        if ($action === 'add_ward') {
            Database::insert(
                "INSERT INTO wards (name, code, floor, department_id, type, total_beds, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $code, $floor, $departmentId, $type, $totalBeds, $description, $status]
            );
            logActivity($userId, 'ward_added', 'admission', "Added ward: $name");
            set_flash('success', 'Ward added successfully.');
        } else {
            Database::query(
                "UPDATE wards SET name = ?, code = ?, floor = ?, department_id = ?, type = ?, total_beds = ?, description = ?, status = ? WHERE id = ?",
                [$name, $code, $floor, $departmentId, $type, $totalBeds, $description, $status, $wardId]
            );
            logActivity($userId, 'ward_updated', 'admission', "Updated ward: $name");
            set_flash('success', 'Ward updated successfully.');
        }
    } elseif ($action === 'delete_ward') {
        $wardId = intval($_POST['ward_id']);
        $bedCount = Database::fetch("SELECT COUNT(*) as c FROM beds WHERE ward_id = ?", [$wardId])['c'];
        if ($bedCount > 0) {
            set_flash('error', 'Cannot delete ward with existing beds. Remove beds first.', 'warning');
        } else {
            Database::query("DELETE FROM wards WHERE id = ?", [$wardId]);
            logActivity($userId, 'ward_deleted', 'admission', "Deleted ward #$wardId");
            set_flash('success', 'Ward deleted.');
        }
    }
    redirect('modules/admission/wards.php');
}

$wards = Database::fetchAll(
    "SELECT w.*, d.name as department_name,
            (SELECT COUNT(*) FROM beds WHERE ward_id = w.id) as total_beds_count,
            (SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'occupied') as occupied_beds
     FROM wards w
     LEFT JOIN departments d ON w.department_id = d.id
     ORDER BY w.name ASC"
);

$departments = Database::fetchAll("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>Wards</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#wardModal">
        <i class="fas fa-plus me-1"></i>Add Ward
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Floor</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Total Beds</th>
                        <th>Occupancy</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wards)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No wards found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($wards as $w): ?>
                            <?php
                            $occupied = intval($w['occupied_beds']);
                            $total = intval($w['total_beds_count']);
                            $available = $total - $occupied;
                            $pct = $total > 0 ? round(($occupied / $total) * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($w['code'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars($w['floor'] ?? '-') ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($w['type']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($w['department_name'] ?? '-') ?></td>
                                <td class="text-center fw-bold"><?= $total ?></td>
                                <td style="min-width:120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar bg-<?= $pct >= 90 ? 'danger' : ($pct >= 60 ? 'warning' : 'success') ?>" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $occupied ?>/<?= $total ?></small>
                                    </div>
                                </td>
                                <td class="fw-bold <?= $available > 0 ? 'text-success' : 'text-danger' ?>"><?= $available ?></td>
                                <td><?= getStatusBadge($w['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#wardModal"
                                        data-id="<?= $w['id'] ?>"
                                        data-name="<?= htmlspecialchars($w['name']) ?>"
                                        data-code="<?= htmlspecialchars($w['code'] ?? '') ?>"
                                        data-floor="<?= htmlspecialchars($w['floor'] ?? '') ?>"
                                        data-department-id="<?= $w['department_id'] ?>"
                                        data-type="<?= $w['type'] ?>"
                                        data-total-beds="<?= $w['total_beds'] ?>"
                                        data-description="<?= htmlspecialchars($w['description'] ?? '') ?>"
                                        data-status="<?= $w['status'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this ward?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_ward">
                                        <input type="hidden" name="ward_id" value="<?= $w['id'] ?>">
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

<div class="modal fade" id="wardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="wardAction" value="add_ward">
                <input type="hidden" name="ward_id" id="wardId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="wardModalTitle">Add Ward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="wardName" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="wardCode" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Floor</label>
                            <input type="text" name="floor" id="wardFloor" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" id="wardType" class="form-select">
                                <option value="general">General</option>
                                <option value="private">Private</option>
                                <option value="icu">ICU</option>
                                <option value="maternity">Maternity</option>
                                <option value="pediatric">Pediatric</option>
                                <option value="psychiatric">Psychiatric</option>
                                <option value="isolation">Isolation</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" id="wardDepartmentId" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Beds <span class="text-danger">*</span></label>
                            <input type="number" name="total_beds" id="wardTotalBeds" class="form-control" required min="1">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="wardStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="wardDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var wardModal = document.getElementById('wardModal');
    wardModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('wardAction').value = id ? 'edit_ward' : 'add_ward';
        document.getElementById('wardId').value = id || '0';
        document.getElementById('wardModalTitle').textContent = id ? 'Edit Ward' : 'Add Ward';
        document.getElementById('wardName').value = btn.getAttribute('data-name') || '';
        document.getElementById('wardCode').value = btn.getAttribute('data-code') || '';
        document.getElementById('wardFloor').value = btn.getAttribute('data-floor') || '';
        document.getElementById('wardDepartmentId').value = btn.getAttribute('data-department-id') || '';
        document.getElementById('wardType').value = btn.getAttribute('data-type') || 'general';
        document.getElementById('wardTotalBeds').value = btn.getAttribute('data-total-beds') || '0';
        document.getElementById('wardDescription').value = btn.getAttribute('data-description') || '';
        document.getElementById('wardStatus').value = btn.getAttribute('data-status') || 'active';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
