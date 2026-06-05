<?php
define('PAGE_TITLE', 'Purchase Records');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $medicineId = intval($_POST['medicine_id']);
    $supplier = sanitize($_POST['supplier'] ?? '');
    $invoiceNumber = sanitize($_POST['invoice_number'] ?? '');
    $quantity = floatval($_POST['quantity']);
    $unitCost = floatval($_POST['unit_cost']);
    $totalCost = $quantity * $unitCost;
    $batchNumber = sanitize($_POST['batch_number'] ?? '');
    $purchaseDate = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
    $expiryDate = sanitize($_POST['expiry_date'] ?? '') ?: null;
    $notes = sanitize($_POST['notes'] ?? '');

    if ($medicineId && $quantity > 0 && $unitCost > 0) {
        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            Database::insert(
                "INSERT INTO medicine_purchases (medicine_id, supplier, invoice_number, quantity, unit_cost, total_cost, batch_number, purchase_date, expiry_date, notes, purchased_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$medicineId, $supplier, $invoiceNumber, $quantity, $unitCost, $totalCost, $batchNumber, $purchaseDate, $expiryDate, $notes, $userId]
            );

            Database::query(
                "UPDATE medicines SET current_stock = current_stock + ?, batch_number = COALESCE(NULLIF(?, ''), batch_number), expiry_date = COALESCE(?, expiry_date) WHERE id = ?",
                [$quantity, $batchNumber, $expiryDate, $medicineId]
            );

            Database::insert(
                "INSERT INTO stock_movements (medicine_id, quantity, type, reference_type, reference_id, batch_number, expiry_date, unit_price, notes, performed_by)
                 VALUES (?, ?, 'purchase', 'medicine_purchase', LAST_INSERT_ID(), ?, ?, ?, ?, ?)",
                [$medicineId, $quantity, $batchNumber, $expiryDate, $unitCost, $notes, $userId]
            );

            $db->commit();
            logActivity($userId, 'purchase_recorded', 'pharmacy', "Purchase recorded for medicine #$medicineId");
            set_flash('success', 'Purchase recorded successfully. Stock updated.');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Failed to record purchase: ' . $e->getMessage(), 'danger');
        }
    } else {
        set_flash('error', 'Please fill in all required fields.', 'warning');
    }
    redirect('modules/pharmacy/purchases.php');
}

$filterMedicine = intval($_GET['medicine_id'] ?? 0);
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = [];
$params = [];
if ($filterMedicine) {
    $where[] = 'mp.medicine_id = ?';
    $params[] = $filterMedicine;
}
if ($dateFrom) {
    $where[] = 'mp.purchase_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'mp.purchase_date <= ?';
    $params[] = $dateTo;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$purchases = Database::fetchAll(
    "SELECT mp.*, m.name as medicine_name, m.generic_name, u.first_name, u.last_name
     FROM medicine_purchases mp
     JOIN medicines m ON mp.medicine_id = m.id
     JOIN users u ON mp.purchased_by = u.id
     $whereClause
     ORDER BY mp.purchase_date DESC, mp.created_at DESC
     LIMIT 100",
    $params
);

$medicines = Database::fetchAll(
    "SELECT id, name, generic_name FROM medicines WHERE status = 'active' ORDER BY name ASC"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-shopping-cart me-2 text-primary"></i>Purchase Records</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#purchaseModal">
        <i class="fas fa-plus me-1"></i>New Purchase
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Medicine</label>
                <select name="medicine_id" class="form-select">
                    <option value="">All Medicines</option>
                    <?php foreach ($medicines as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $filterMedicine === $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
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
                        <th>Date</th>
                        <th>Medicine</th>
                        <th>Supplier</th>
                        <th>Invoice #</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Batch</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No purchase records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $pr): ?>
                            <tr>
                                <td class="small"><?= formatDate($pr['purchase_date']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($pr['medicine_name']) ?></strong>
                                    <?php if ($pr['generic_name']): ?><br><small class="text-muted"><?= htmlspecialchars($pr['generic_name']) ?></small><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($pr['supplier'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($pr['invoice_number'] ?? '-') ?></code></td>
                                <td class="fw-medium"><?= floatval($pr['quantity']) ?></td>
                                <td><?= formatCurrency($pr['unit_cost']) ?></td>
                                <td class="fw-bold"><?= formatCurrency($pr['total_cost']) ?></td>
                                <td class="small"><?= htmlspecialchars($pr['batch_number'] ?? '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($pr['first_name'] . ' ' . $pr['last_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cart-plus me-2 text-primary"></i>New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Medicine <span class="text-danger">*</span></label>
                            <select name="medicine_id" class="form-select" required>
                                <option value="">Select Medicine</option>
                                <?php foreach ($medicines as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $filterMedicine === $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name'] . ($m['generic_name'] ? ' (' . $m['generic_name'] . ')' : '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="quantity" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="unit_cost" id="unitCost" class="form-control" required oninput="calcTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Cost</label>
                            <input type="text" id="totalCost" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcTotal() {
    var qty = document.querySelector('input[name="quantity"]').value || 0;
    var cost = document.getElementById('unitCost').value || 0;
    document.getElementById('totalCost').value = (parseFloat(qty) * parseFloat(cost)).toFixed(2);
}
document.querySelector('input[name="quantity"]')?.addEventListener('input', calcTotal);
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
