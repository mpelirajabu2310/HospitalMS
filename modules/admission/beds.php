<?php
define('PAGE_TITLE', 'Beds');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_bed', 'edit_bed'])) {
        $bedId = intval($_POST['bed_id'] ?? 0);
        $wardId = intval($_POST['ward_id']);
        $bedNumber = sanitize($_POST['bed_number']);
        $bedType = sanitize($_POST['bed_type'] ?? 'standard');
        $pricePerDay = floatval($_POST['price_per_day'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'available');
        $notes = sanitize($_POST['notes'] ?? '');

        if ($action === 'add_bed') {
            $existing = Database::fetch("SELECT id FROM beds WHERE ward_id = ? AND bed_number = ?", [$wardId, $bedNumber]);
            if ($existing) {
                set_flash('error', 'Bed number already exists in this ward.', 'warning');
                redirect('modules/admission/beds.php');
            }
            Database::insert(
                "INSERT INTO beds (ward_id, bed_number, bed_type, price_per_day, status, notes) VALUES (?, ?, ?, ?, ?, ?)",
                [$wardId, $bedNumber, $bedType, $pricePerDay, $status, $notes]
            );
            logActivity($userId, 'bed_added', 'admission', "Added bed: $bedNumber");
            set_flash('success', 'Bed added successfully.');
        } else {
            Database::query(
                "UPDATE beds SET ward_id = ?, bed_number = ?, bed_type = ?, price_per_day = ?, status = ?, notes = ? WHERE id = ?",
                [$wardId, $bedNumber, $bedType, $pricePerDay, $status, $notes, $bedId]
            );
            logActivity($userId, 'bed_updated', 'admission', "Updated bed: $bedNumber");
            set_flash('success', 'Bed updated successfully.');
        }
    } elseif ($action === 'quick_status') {
        $bedId = intval($_POST['bed_id']);
        $newStatus = sanitize($_POST['new_status']);
        Database::query("UPDATE beds SET status = ? WHERE id = ?", [$newStatus, $bedId]);
        logActivity($userId, 'bed_status_changed', 'admission', "Bed #$bedId changed to $newStatus");
        set_flash('success', 'Bed status updated.');
    }
    redirect('modules/admission/beds.php');
}

$wardFilter = intval($_GET['ward_id'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

$where = [];
$params = [];
if ($wardFilter) {
    $where[] = 'b.ward_id = ?';
    $params[] = $wardFilter;
}
if ($statusFilter) {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$beds = Database::fetchAll(
    "SELECT b.*, w.name as ward_name, w.code as ward_code, w.type as ward_type
     FROM beds b
     JOIN wards w ON b.ward_id = w.id
     $whereClause
     ORDER BY w.name ASC, b.bed_number ASC",
    $params
);

$wards = Database::fetchAll("SELECT id, name, code FROM wards WHERE status = 'active' ORDER BY name ASC");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-bed me-2 text-primary"></i>Beds</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bedModal">
        <i class="fas fa-plus me-1"></i>Add Bed
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Ward</label>
                <select name="ward_id" class="form-select">
                    <option value="">All Wards</option>
                    <?php foreach ($wards as $w): ?>
                        <option value="<?= $w['id'] ?>" <?= $wardFilter === $w['id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="occupied" <?= $statusFilter === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                    <option value="reserved" <?= $statusFilter === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                    <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="cleaning" <?= $statusFilter === 'cleaning' ? 'selected' : '' ?>>Cleaning</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
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
                        <th>Bed #</th>
                        <th>Ward</th>
                        <th>Type</th>
                        <th>Price / Day</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($beds)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No beds found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($beds as $b): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($b['bed_number']) ?></code></td>
                                <td><?= htmlspecialchars($b['ward_name']) ?> <small class="text-muted">(<?= htmlspecialchars($b['ward_code'] ?? '') ?>)</small></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($b['bed_type']) ?></span></td>
                                <td class="fw-medium"><?= formatCurrency($b['price_per_day']) ?></td>
                                <td><?= getStatusBadge($b['status']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($b['notes'] ?? '-') ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#bedModal"
                                        data-id="<?= $b['id'] ?>"
                                        data-ward-id="<?= $b['ward_id'] ?>"
                                        data-bed-number="<?= htmlspecialchars($b['bed_number']) ?>"
                                        data-bed-type="<?= $b['bed_type'] ?>"
                                        data-price-per-day="<?= $b['price_per_day'] ?>"
                                        data-status="<?= $b['status'] ?>"
                                        data-notes="<?= htmlspecialchars($b['notes'] ?? '') ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($b['status'] !== 'available'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="quick_status">
                                            <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                            <input type="hidden" name="new_status" value="available">
                                            <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Mark Available">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($b['status'] !== 'maintenance'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="quick_status">
                                            <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                            <input type="hidden" name="new_status" value="maintenance">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Mark Maintenance">
                                                <i class="fas fa-tools"></i>
                                            </button>
                                        </form>
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

<div class="modal fade" id="bedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="bedAction" value="add_bed">
                <input type="hidden" name="bed_id" id="bedId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="bedModalTitle">Add Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ward <span class="text-danger">*</span></label>
                        <select name="ward_id" id="bedWardId" class="form-select" required>
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bed Number <span class="text-danger">*</span></label>
                            <input type="text" name="bed_number" id="bedNumber" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bed Type</label>
                            <select name="bed_type" id="bedType" class="form-select">
                                <option value="standard">Standard</option>
                                <option value="electric">Electric</option>
                                <option value="icu">ICU</option>
                                <option value="maternity">Maternity</option>
                                <option value="pediatric">Pediatric</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price Per Day</label>
                            <input type="number" step="0.01" name="price_per_day" id="bedPrice" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="bedStatus" class="form-select">
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="cleaning">Cleaning</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="bedNotes" class="form-control" rows="2"></textarea>
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
    var bedModal = document.getElementById('bedModal');
    bedModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('bedAction').value = id ? 'edit_bed' : 'add_bed';
        document.getElementById('bedId').value = id || '0';
        document.getElementById('bedModalTitle').textContent = id ? 'Edit Bed' : 'Add Bed';
        document.getElementById('bedWardId').value = btn.getAttribute('data-ward-id') || '';
        document.getElementById('bedNumber').value = btn.getAttribute('data-bed-number') || '';
        document.getElementById('bedType').value = btn.getAttribute('data-bed-type') || 'standard';
        document.getElementById('bedPrice').value = btn.getAttribute('data-price-per-day') || '0';
        document.getElementById('bedStatus').value = btn.getAttribute('data-status') || 'available';
        document.getElementById('bedNotes').value = btn.getAttribute('data-notes') || '';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
