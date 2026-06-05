<?php
define('PAGE_TITLE', 'Stock Alerts');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$lowStock = Database::fetchAll(
    "SELECT m.*, mc.name as category_name,
            (SELECT MAX(mp.purchase_date) FROM medicine_purchases mp WHERE mp.medicine_id = m.id) as last_purchase_date
     FROM medicines m
     LEFT JOIN medicine_categories mc ON m.category_id = mc.id
     WHERE m.status = 'active' AND m.current_stock <= m.reorder_level
     ORDER BY (m.current_stock / NULLIF(m.reorder_level, 0)) ASC, m.name ASC"
);

$expiringSoon = Database::fetchAll(
    "SELECT m.*, mc.name as category_name,
            DATEDIFF(m.expiry_date, CURDATE()) as days_remaining
     FROM medicines m
     LEFT JOIN medicine_categories mc ON m.category_id = mc.id
     WHERE m.status = 'active' AND m.expiry_date IS NOT NULL
           AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY m.expiry_date ASC"
);

$expired = Database::fetchAll(
    "SELECT m.*, mc.name as category_name,
            DATEDIFF(CURDATE(), m.expiry_date) as days_expired
     FROM medicines m
     LEFT JOIN medicine_categories mc ON m.category_id = mc.id
     WHERE m.status = 'active' AND m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE()
     ORDER BY m.expiry_date ASC"
);

$lowStockCount = count($lowStock);
$expiringCount = count($expiringSoon);
$expiredCount = count($expired);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-primary"></i>Stock Alerts</h4>
</div>

<div class="row g-3 mb-4">
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
                <h3 class="mb-1 fw-bold text-warning"><?= $expiringCount ?></h3>
                <small class="text-muted">Expiring Within 30 Days</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-secondary bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-secondary"><?= $expiredCount ?></h3>
                <small class="text-muted">Already Expired</small>
            </div>
        </div>
    </div>
</div>

<?php if ($lowStockCount > 0): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-circle me-2 text-danger"></i>Low Stock Items</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Shortage</th>
                        <th>Supplier</th>
                        <th>Last Purchase</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                <?php if ($m['generic_name']): ?><br><small class="text-muted"><?= htmlspecialchars($m['generic_name']) ?></small><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
                            <td class="fw-bold text-danger"><?= floatval($m['current_stock']) ?></td>
                            <td><?= intval($m['reorder_level']) ?></td>
                            <td class="fw-bold text-danger"><?= max(0, intval($m['reorder_level']) - floatval($m['current_stock'])) ?></td>
                            <td><?= htmlspecialchars($m['supplier'] ?? '-') ?></td>
                            <td class="small"><?= $m['last_purchase_date'] ? formatDate($m['last_purchase_date']) : '-' ?></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/pharmacy/purchases.php?medicine_id=<?= $m['id'] ?>" class="btn btn-sm btn-success me-1"><i class="fas fa-cart-plus me-1"></i>Add Stock</a>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                    onclick="location.href='<?= APP_URL ?>/modules/pharmacy/inventory.php?search=<?= urlencode($m['name']) ?>'">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($expiringCount > 0): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-warning"><i class="fas fa-clock me-2 text-warning"></i>Expiring Within 30 Days</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Batch</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringSoon as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                <?php if ($m['generic_name']): ?><br><small class="text-muted"><?= htmlspecialchars($m['generic_name']) ?></small><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
                            <td><?= floatval($m['current_stock']) ?> <?= htmlspecialchars($m['unit']) ?></td>
                            <td><?= htmlspecialchars($m['batch_number'] ?? '-') ?></td>
                            <td class="fw-bold"><?= formatDate($m['expiry_date']) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $m['days_remaining'] ?> days</span></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/pharmacy/inventory.php?search=<?= urlencode($m['name']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($expiredCount > 0): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-secondary"><i class="fas fa-calendar-times me-2 text-secondary"></i>Expired Medicines</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Batch</th>
                        <th>Expired On</th>
                        <th>Days Expired</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expired as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                <?php if ($m['generic_name']): ?><br><small class="text-muted"><?= htmlspecialchars($m['generic_name']) ?></small><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['category_name'] ?? '-') ?></td>
                            <td class="fw-bold text-danger"><?= floatval($m['current_stock']) ?> <?= htmlspecialchars($m['unit']) ?></td>
                            <td><?= htmlspecialchars($m['batch_number'] ?? '-') ?></td>
                            <td class="fw-bold"><?= formatDate($m['expiry_date']) ?></td>
                            <td><span class="badge bg-danger"><?= $m['days_expired'] ?> days ago</span></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/pharmacy/inventory.php?search=<?= urlencode($m['name']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($lowStockCount === 0 && $expiringCount === 0 && $expiredCount === 0): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
        <h5>All Clear!</h5>
        <p class="text-muted mb-0">No stock alerts at this time. All medicines are well-stocked and within expiry.</p>
    </div>
</div>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
