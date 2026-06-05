<?php
define('PAGE_TITLE', 'Billing Items');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_item', 'edit_item'])) {
        $itemId = intval($_POST['item_id'] ?? 0);
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category']);
        $code = sanitize($_POST['code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $status = sanitize($_POST['status'] ?? 'active');

        if ($action === 'add_item') {
            Database::insert(
                "INSERT INTO billing_items (name, category, code, description, price, status) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $category, $code, $description, $price, $status]
            );
            logActivity($userId, 'billing_item_added', 'billing', "Added billing item: $name");
            set_flash('success', 'Billing item added successfully.');
        } else {
            Database::query(
                "UPDATE billing_items SET name = ?, category = ?, code = ?, description = ?, price = ?, status = ? WHERE id = ?",
                [$name, $category, $code, $description, $price, $status, $itemId]
            );
            logActivity($userId, 'billing_item_updated', 'billing', "Updated billing item: $name");
            set_flash('success', 'Billing item updated successfully.');
        }
    } elseif ($action === 'delete_item') {
        $itemId = intval($_POST['item_id']);
        Database::query("DELETE FROM billing_items WHERE id = ?", [$itemId]);
        logActivity($userId, 'billing_item_deleted', 'billing', "Deleted billing item #$itemId");
        set_flash('success', 'Billing item deleted.');
    }
    redirect('modules/billing/items.php');
}

$categoryFilter = sanitize($_GET['category'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
if ($categoryFilter) {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
}
if ($statusFilter) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(name LIKE ? OR code LIKE ?)';
    $t = "%$search%";
    $params[] = $t; $params[] = $t;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$items = Database::fetchAll(
    "SELECT * FROM billing_items $whereClause ORDER BY category ASC, name ASC",
    $params
);

$categories = ['consultation', 'laboratory', 'pharmacy', 'admission', 'procedure', 'service', 'other'];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i>Billing Items</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#itemModal">
        <i class="fas fa-plus me-1"></i>Add Item
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or code..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Category</label>
                <select name="category" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
                        <th>Name</th>
                        <th>Category</th>
                        <th>Code</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No billing items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                <td><span class="badge bg-info"><?= ucfirst($item['category']) ?></span></td>
                                <td><code><?= htmlspecialchars($item['code'] ?? '-') ?></code></td>
                                <td class="fw-bold"><?= formatCurrency($item['price']) ?></td>
                                <td><?= getStatusBadge($item['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#itemModal"
                                        data-id="<?= $item['id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-category="<?= $item['category'] ?>"
                                        data-code="<?= htmlspecialchars($item['code'] ?? '') ?>"
                                        data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                        data-price="<?= $item['price'] ?>"
                                        data-status="<?= $item['status'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
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

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="itemAction" value="add_item">
                <input type="hidden" name="item_id" id="itemId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle">Add Billing Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="itemName" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="itemCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="itemCode" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" id="itemPrice" class="form-control" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="itemStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="itemDescription" class="form-control" rows="2"></textarea>
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
    var itemModal = document.getElementById('itemModal');
    itemModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('itemAction').value = id ? 'edit_item' : 'add_item';
        document.getElementById('itemId').value = id || '0';
        document.getElementById('itemModalTitle').textContent = id ? 'Edit Billing Item' : 'Add Billing Item';
        document.getElementById('itemName').value = btn.getAttribute('data-name') || '';
        document.getElementById('itemCategory').value = btn.getAttribute('data-category') || '';
        document.getElementById('itemCode').value = btn.getAttribute('data-code') || '';
        document.getElementById('itemDescription').value = btn.getAttribute('data-description') || '';
        document.getElementById('itemPrice').value = btn.getAttribute('data-price') || '';
        document.getElementById('itemStatus').value = btn.getAttribute('data-status') || 'active';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
