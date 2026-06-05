<?php
define('PAGE_TITLE', 'Medicine Inventory');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_medicine' || $action === 'edit_medicine') {
        $medId = intval($_POST['medicine_id'] ?? 0);
        $name = sanitize($_POST['name']);
        $genericName = sanitize($_POST['generic_name'] ?? '');
        $categoryId = intval($_POST['category_id'] ?: 0) ?: null;
        $brand = sanitize($_POST['brand'] ?? '');
        $dosageForm = sanitize($_POST['dosage_form'] ?? 'tablet');
        $strength = sanitize($_POST['strength'] ?? '');
        $unit = sanitize($_POST['unit'] ?? 'tablet');
        $description = sanitize($_POST['description'] ?? '');
        $manufacturer = sanitize($_POST['manufacturer'] ?? '');
        $supplier = sanitize($_POST['supplier'] ?? '');
        $reorderLevel = intval($_POST['reorder_level'] ?? 10);
        $currentStock = floatval($_POST['current_stock'] ?? 0);
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        $sellingPrice = floatval($_POST['selling_price'] ?? 0);
        $requiresPrescription = isset($_POST['requires_prescription']) ? 1 : 0;
        $batchNumber = sanitize($_POST['batch_number'] ?? '');
        $expiryDate = sanitize($_POST['expiry_date'] ?? '') ?: null;
        $status = sanitize($_POST['status'] ?? 'active');

        if ($action === 'add_medicine') {
            Database::insert(
                "INSERT INTO medicines (name, generic_name, category_id, brand, dosage_form, strength, unit, description, manufacturer, supplier, reorder_level, current_stock, unit_price, selling_price, requires_prescription, batch_number, expiry_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $genericName, $categoryId, $brand, $dosageForm, $strength, $unit, $description, $manufacturer, $supplier, $reorderLevel, $currentStock, $unitPrice, $sellingPrice, $requiresPrescription, $batchNumber, $expiryDate, $status]
            );
            logActivity($userId, 'medicine_added', 'pharmacy', "Added medicine: $name");
            set_flash('success', 'Medicine added successfully.');
        } else {
            Database::query(
                "UPDATE medicines SET name = ?, generic_name = ?, category_id = ?, brand = ?, dosage_form = ?, strength = ?, unit = ?, description = ?, manufacturer = ?, supplier = ?, reorder_level = ?, unit_price = ?, selling_price = ?, requires_prescription = ?, batch_number = ?, expiry_date = ?, status = ? WHERE id = ?",
                [$name, $genericName, $categoryId, $brand, $dosageForm, $strength, $unit, $description, $manufacturer, $supplier, $reorderLevel, $unitPrice, $sellingPrice, $requiresPrescription, $batchNumber, $expiryDate, $status, $medId]
            );
            logActivity($userId, 'medicine_updated', 'pharmacy', "Updated medicine: $name");
            set_flash('success', 'Medicine updated successfully.');
        }
    } elseif ($action === 'adjust_stock') {
        $medId = intval($_POST['medicine_id']);
        $type = sanitize($_POST['adjust_type']);
        $quantity = floatval($_POST['quantity']);
        $notes = sanitize($_POST['notes'] ?? '');

        $medicine = Database::fetch("SELECT current_stock, name FROM medicines WHERE id = ?", [$medId]);
        if ($medicine) {
            if (in_array($type, ['sale', 'expired', 'damaged'])) {
                $quantity = -abs($quantity);
            }
            Database::query(
                "UPDATE medicines SET current_stock = current_stock + ? WHERE id = ?",
                [$quantity, $medId]
            );
            Database::insert(
                "INSERT INTO stock_movements (medicine_id, quantity, type, notes, performed_by) VALUES (?, ?, ?, ?, ?)",
                [$medId, $quantity, $type, $notes, $userId]
            );
            logActivity($userId, 'stock_adjustment', 'pharmacy', "Stock adjusted for {$medicine['name']}: {$quantity}");
            set_flash('success', 'Stock adjusted successfully.');
        }
    } elseif ($action === 'add_category' || $action === 'edit_category') {
        $catId = intval($_POST['cat_id'] ?? 0);
        $catName = sanitize($_POST['cat_name']);
        $catDesc = sanitize($_POST['cat_description'] ?? '');
        if ($action === 'add_category') {
            Database::insert("INSERT INTO medicine_categories (name, description) VALUES (?, ?)", [$catName, $catDesc]);
            set_flash('success', 'Medicine category added.');
        } else {
            Database::query("UPDATE medicine_categories SET name = ?, description = ? WHERE id = ?", [$catName, $catDesc, $catId]);
            set_flash('success', 'Medicine category updated.');
        }
    } elseif ($action === 'delete_category') {
        Database::query("DELETE FROM medicine_categories WHERE id = ?", [intval($_POST['cat_id'])]);
        set_flash('success', 'Category deleted.');
    }
    redirect('modules/pharmacy/inventory.php');
}

$search = trim($_GET['search'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$lowStockOnly = isset($_GET['low_stock']);

$where = [];
$params = [];
if ($search) {
    $where[] = "(m.name LIKE ? OR m.generic_name LIKE ? OR m.brand LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
if ($filterCategory) {
    $where[] = "m.category_id = ?";
    $params[] = $filterCategory;
}
if ($filterStatus) {
    $where[] = "m.status = ?";
    $params[] = $filterStatus;
}
if ($lowStockOnly) {
    $where[] = "m.current_stock <= m.reorder_level";
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$medicines = Database::fetchAll(
    "SELECT m.*, mc.name as category_name
     FROM medicines m
     LEFT JOIN medicine_categories mc ON m.category_id = mc.id
     $whereClause
     ORDER BY m.name ASC",
    $params
);

$categories = Database::fetchAll("SELECT * FROM medicine_categories ORDER BY name ASC");
$totalMedicines = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE status = 'active'")['c'];
$lowStockCount = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE current_stock <= reorder_level AND status = 'active'")['c'];
$expiringSoon = Database::fetch("SELECT COUNT(*) as c FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'")['c'];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-pills me-2 text-primary"></i>Medicine Inventory</h4>
    <div>
        <button type="button" class="btn btn-outline-secondary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#categoryListModal">
            <i class="fas fa-folder me-1"></i>Categories
        </button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#medicineModal">
            <i class="fas fa-plus me-1"></i>Add Medicine
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-primary"><?= $totalMedicines ?></h3>
                <small class="text-muted">Total Active Medicines</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-danger"><?= $lowStockCount ?></h3>
                <small class="text-muted">Low Stock Items</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-warning"><?= $expiringSoon ?></h3>
                <small class="text-muted">Expiring Within 30 Days</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium small">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, generic, brand..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Category</label>
                <select name="category" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterCategory == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="discontinued" <?= $filterStatus === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check pt-4">
                    <input type="checkbox" name="low_stock" class="form-check-input" id="lowStock" value="1" <?= $lowStockOnly ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="lowStock">Low Stock Only</label>
                </div>
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
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Reorder</th>
                        <th>Unit Price</th>
                        <th>Selling Price</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($medicines)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No medicines found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($medicines as $m): ?>
                            <?php
                            $rowClass = '';
                            if ($m['current_stock'] <= $m['reorder_level']) {
                                $rowClass = 'table-danger';
                            } elseif ($m['expiry_date'] && strtotime($m['expiry_date']) <= strtotime('+30 days') && $m['expiry_date'] >= date('Y-m-d')) {
                                $rowClass = 'table-warning';
                            }
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <strong><?= htmlspecialchars($m['name']) ?></strong>
                                    <?php if ($m['generic_name']): ?><br><small class="text-muted"><?= htmlspecialchars($m['generic_name']) ?></small><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
                                <td class="fw-medium"><?= floatval($m['current_stock']) ?> <?= htmlspecialchars($m['unit']) ?></td>
                                <td><?= intval($m['reorder_level']) ?></td>
                                <td><?= formatCurrency($m['unit_price']) ?></td>
                                <td><?= formatCurrency($m['selling_price']) ?></td>
                                <td class="small">
                                    <?php if ($m['expiry_date']): ?>
                                        <?= formatDate($m['expiry_date']) ?>
                                        <?php if (strtotime($m['expiry_date']) < time()): ?>
                                            <br><span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($m['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#medicineModal"
                                        data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>"
                                        data-generic-name="<?= htmlspecialchars($m['generic_name'] ?? '') ?>"
                                        data-category-id="<?= $m['category_id'] ?>" data-brand="<?= htmlspecialchars($m['brand'] ?? '') ?>"
                                        data-dosage-form="<?= $m['dosage_form'] ?>" data-strength="<?= htmlspecialchars($m['strength'] ?? '') ?>"
                                        data-unit="<?= htmlspecialchars($m['unit']) ?>"
                                        data-description="<?= htmlspecialchars($m['description'] ?? '') ?>"
                                        data-manufacturer="<?= htmlspecialchars($m['manufacturer'] ?? '') ?>"
                                        data-supplier="<?= htmlspecialchars($m['supplier'] ?? '') ?>"
                                        data-reorder-level="<?= $m['reorder_level'] ?>" data-current-stock="<?= $m['current_stock'] ?>"
                                        data-unit-price="<?= $m['unit_price'] ?>" data-selling-price="<?= $m['selling_price'] ?>"
                                        data-requires-prescription="<?= $m['requires_prescription'] ?>"
                                        data-batch-number="<?= htmlspecialchars($m['batch_number'] ?? '') ?>"
                                        data-expiry-date="<?= $m['expiry_date'] ?? '' ?>" data-status="<?= $m['status'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info me-1" title="Adjust Stock"
                                        data-bs-toggle="modal" data-bs-target="#stockModal"
                                        data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>" data-stock="<?= $m['current_stock'] ?>">
                                        <i class="fas fa-balance-scale"></i>
                                    </button>
                                    <a href="<?= APP_URL ?>/modules/pharmacy/purchases.php?medicine_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-success" title="Purchase"><i class="fas fa-cart-plus"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="medicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="medAction" value="add_medicine">
                <input type="hidden" name="medicine_id" id="medId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="medModalTitle">Add Medicine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="medName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Generic Name</label>
                            <input type="text" name="generic_name" id="medGenericName" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="medCategoryId" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" id="medBrand" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Dosage Form</label>
                            <select name="dosage_form" id="medDosageForm" class="form-select">
                                <option value="tablet">Tablet</option>
                                <option value="capsule">Capsule</option>
                                <option value="syrup">Syrup</option>
                                <option value="injection">Injection</option>
                                <option value="cream">Cream</option>
                                <option value="ointment">Ointment</option>
                                <option value="drops">Drops</option>
                                <option value="inhaler">Inhaler</option>
                                <option value="suppository">Suppository</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Strength</label>
                            <input type="text" name="strength" id="medStrength" class="form-control" placeholder="e.g. 500mg">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" name="unit" id="medUnit" class="form-control" value="tablet">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" id="medManufacturer" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" id="medSupplier" class="form-control">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" id="medReorderLevel" class="form-control" value="10">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" step="0.01" name="current_stock" id="medCurrentStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" id="medUnitPrice" class="form-control" value="0">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" name="selling_price" id="medSellingPrice" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" id="medBatchNumber" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="medExpiryDate" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="medStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="discontinued">Discontinued</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="requires_prescription" class="form-check-input" id="medRequiresRx" value="1" checked>
                                <label class="form-check-label" for="medRequiresRx">Requires Prescription</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="medDescription" class="form-control" rows="2"></textarea>
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

<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="medicine_id" id="stockMedId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong id="stockMedName"></strong></p>
                    <p class="text-muted small">Current Stock: <span id="stockCurrentStock" class="fw-bold"></span></p>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjust_type" class="form-select" required>
                            <option value="purchase">Purchase (add stock)</option>
                            <option value="adjustment">Adjustment (+/-)</option>
                            <option value="return">Return (add stock)</option>
                            <option value="expired">Expired (remove stock)</option>
                            <option value="damaged">Damaged (remove stock)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medicine Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus me-1"></i>Add Category
                </button>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($c['description'] ?? '') ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#categoryModal"
                                        data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-description="<?= htmlspecialchars($c['description'] ?? '') ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="medCatAction" value="add_category">
                <input type="hidden" name="cat_id" id="medCatId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="medCatModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="cat_name" id="medCatName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="cat_description" id="medCatDescription" class="form-control" rows="2"></textarea>
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
    var medModal = document.getElementById('medicineModal');
    medModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('medAction').value = id ? 'edit_medicine' : 'add_medicine';
        document.getElementById('medId').value = id || '0';
        document.getElementById('medModalTitle').textContent = id ? 'Edit Medicine' : 'Add Medicine';
        document.getElementById('medName').value = btn.getAttribute('data-name') || '';
        document.getElementById('medGenericName').value = btn.getAttribute('data-generic-name') || '';
        document.getElementById('medCategoryId').value = btn.getAttribute('data-category-id') || '';
        document.getElementById('medBrand').value = btn.getAttribute('data-brand') || '';
        document.getElementById('medDosageForm').value = btn.getAttribute('data-dosage-form') || 'tablet';
        document.getElementById('medStrength').value = btn.getAttribute('data-strength') || '';
        document.getElementById('medUnit').value = btn.getAttribute('data-unit') || 'tablet';
        document.getElementById('medDescription').value = btn.getAttribute('data-description') || '';
        document.getElementById('medManufacturer').value = btn.getAttribute('data-manufacturer') || '';
        document.getElementById('medSupplier').value = btn.getAttribute('data-supplier') || '';
        document.getElementById('medReorderLevel').value = btn.getAttribute('data-reorder-level') || '10';
        document.getElementById('medCurrentStock').value = btn.getAttribute('data-current-stock') || '0';
        document.getElementById('medUnitPrice').value = btn.getAttribute('data-unit-price') || '0';
        document.getElementById('medSellingPrice').value = btn.getAttribute('data-selling-price') || '0';
        document.getElementById('medRequiresRx').checked = btn.getAttribute('data-requires-prescription') === '1';
        document.getElementById('medBatchNumber').value = btn.getAttribute('data-batch-number') || '';
        document.getElementById('medExpiryDate').value = btn.getAttribute('data-expiry-date') || '';
        document.getElementById('medStatus').value = btn.getAttribute('data-status') || 'active';
    });

    var stockModal = document.getElementById('stockModal');
    stockModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('stockMedId').value = btn.getAttribute('data-id');
        document.getElementById('stockMedName').textContent = btn.getAttribute('data-name');
        document.getElementById('stockCurrentStock').textContent = btn.getAttribute('data-stock');
    });

    var catModal = document.getElementById('categoryModal');
    catModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('medCatAction').value = id ? 'edit_category' : 'add_category';
        document.getElementById('medCatId').value = id || '0';
        document.getElementById('medCatName').value = btn.getAttribute('data-name') || '';
        document.getElementById('medCatDescription').value = btn.getAttribute('data-description') || '';
        document.getElementById('medCatModalTitle').textContent = id ? 'Edit Category' : 'Add Category';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
