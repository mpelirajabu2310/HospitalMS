<?php
define('PAGE_TITLE', 'Invoices');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_invoice') {
        $patientId = intval($_POST['patient_id']);
        $visitId = intval($_POST['visit_id'] ?? 0) ?: null;
        $invoiceDate = sanitize($_POST['invoice_date'] ?? date('Y-m-d'));
        $dueDate = sanitize($_POST['due_date'] ?? '') ?: null;
        $discount = floatval($_POST['discount'] ?? 0);
        $discountType = sanitize($_POST['discount_type'] ?? 'fixed');
        $taxRate = floatval($_POST['tax_rate'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        $descriptions = $_POST['item_description'] ?? [];
        $quantities = $_POST['item_quantity'] ?? [];
        $unitPrices = $_POST['item_unit_price'] ?? [];

        if ($patientId && !empty($descriptions)) {
            try {
                $db = Database::getInstance()->getConnection();
                $db->beginTransaction();

                $subtotal = 0;
                $items = [];
                foreach ($descriptions as $i => $desc) {
                    if (empty(trim($desc))) continue;
                    $qty = intval($quantities[$i] ?? 1);
                    $price = floatval($unitPrices[$i] ?? 0);
                    $total = $qty * $price;
                    $subtotal += $total;
                    $items[] = [
                        'description' => sanitize($desc),
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'total' => $total
                    ];
                }

                if ($subtotal <= 0) throw new Exception('Invoice total must be greater than zero.');

                $discountAmount = $discountType === 'percentage' ? ($subtotal * $discount / 100) : $discount;
                $taxable = $subtotal - $discountAmount;
                $tax = $taxable * $taxRate / 100;
                $total = $taxable + $tax;

                $invoiceNumber = generateInvoiceNumber();
                $invoiceId = Database::insert(
                    "INSERT INTO invoices (invoice_number, patient_id, visit_id, invoice_date, due_date, subtotal, discount, discount_type, tax, tax_rate, total, status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
                    [$invoiceNumber, $patientId, $visitId, $invoiceDate, $dueDate, $subtotal, $discount, $discountType, $tax, $taxRate, $total, $notes, $userId]
                );

                foreach ($items as $item) {
                    Database::insert(
                        "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)",
                        [$invoiceId, $item['description'], $item['quantity'], $item['unit_price'], $item['total']]
                    );
                }

                $patient = Database::fetch("SELECT first_name, last_name FROM patients WHERE id = ?", [$patientId]);
                $cashiers = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('cashier', 'admin', 'super_admin')");
                foreach ($cashiers as $cashier) {
                    createNotification(
                        $cashier['id'], 'new_invoice', 'New Invoice Created',
                        "Invoice $invoiceNumber for {$patient['first_name']} {$patient['last_name']} - " . formatCurrency($total),
                        APP_URL . '/modules/billing/invoices.php', 'invoice', $invoiceId
                    );
                }

                $db->commit();
                logActivity($userId, 'invoice_created', 'billing', "Invoice #$invoiceNumber created");
                set_flash('success', "Invoice #$invoiceNumber created successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('error', $e->getMessage(), 'danger');
            }
        } else {
            set_flash('error', 'Please select a patient and add at least one item.', 'warning');
        }
        redirect('modules/billing/invoices.php');
    }

    if ($action === 'cancel_invoice') {
        $invoiceId = intval($_POST['invoice_id']);
        $inv = Database::fetch("SELECT invoice_number, status FROM invoices WHERE id = ?", [$invoiceId]);
        if ($inv && $inv['status'] === 'pending') {
            Database::query("UPDATE invoices SET status = 'cancelled' WHERE id = ?", [$invoiceId]);
            logActivity($userId, 'invoice_cancelled', 'billing', "Invoice #{$inv['invoice_number']} cancelled");
            set_flash('success', "Invoice #{$inv['invoice_number']} cancelled.");
        } else {
            set_flash('error', 'Only pending invoices can be cancelled.', 'warning');
        }
        redirect('modules/billing/invoices.php');
    }
}

$statusFilter = sanitize($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = [];
$params = [];
if ($statusFilter) {
    $where[] = 'i.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(i.invoice_number LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_number LIKE ?)';
    $t = "%$search%";
    $params = array_merge($params, [$t, $t, $t, $t]);
}
if ($dateFrom) {
    $where[] = 'i.invoice_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'i.invoice_date <= ?';
    $params[] = $dateTo;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$invoices = Database::fetchAll(
    "SELECT i.*, p.first_name, p.last_name, p.patient_number,
            u.first_name as u_first, u.last_name as u_last
     FROM invoices i
     JOIN patients p ON i.patient_id = p.id
     JOIN users u ON i.created_by = u.id
     $whereClause
     ORDER BY i.created_at DESC
     LIMIT 100",
    $params
);

$pendingCount = Database::fetch("SELECT COUNT(*) as c FROM invoices WHERE status IN ('pending','partial','overdue')")['c'];
$paidToday = Database::fetch("SELECT COUNT(*) as c FROM invoices WHERE status = 'paid' AND DATE(updated_at) = CURDATE()")['c'];
$overdueCount = Database::fetch("SELECT COUNT(*) as c FROM invoices WHERE status = 'overdue'")['c'];
$totalRevenue = Database::fetch("SELECT COALESCE(SUM(paid_amount),0) as t FROM invoices WHERE status IN ('paid','partial')")['t'];

$patients = Database::fetchAll("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name ASC LIMIT 200");
$visits = Database::fetchAll(
    "SELECT v.id, v.visit_number, p.first_name, p.last_name
     FROM visits v JOIN patients p ON v.patient_id = p.id
     WHERE v.status NOT IN ('completed','cancelled') ORDER BY v.created_at DESC LIMIT 100"
);
$doctors = Database::fetchAll("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('doctor','admin','super_admin') AND u.status = 'active' ORDER BY u.first_name ASC");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i>Invoices</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
        <i class="fas fa-plus me-1"></i>Create Invoice
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-warning"><?= $pendingCount ?></h3>
                <small class="text-muted">Pending / Partial / Overdue</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-success"><?= $paidToday ?></h3>
                <small class="text-muted">Paid Today</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-danger"><?= $overdueCount ?></h3>
                <small class="text-muted">Overdue</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-1 fw-bold text-info"><?= formatCurrency($totalRevenue) ?></h3>
                <small class="text-muted">Total Revenue (Paid)</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Invoice #, Patient..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
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
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($inv['invoice_number']) ?></code></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $inv['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($inv['patient_number']) ?></small>
                                </td>
                                <td class="small"><?= formatDate($inv['invoice_date']) ?></td>
                                <td><?= formatCurrency($inv['subtotal']) ?></td>
                                <td class="fw-bold"><?= formatCurrency($inv['total']) ?></td>
                                <td><?= formatCurrency($inv['paid_amount']) ?></td>
                                <td class="fw-bold <?= $inv['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= formatCurrency($inv['balance']) ?></td>
                                <td><?= getStatusBadge($inv['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-info me-1" title="View"
                                        data-bs-toggle="modal" data-bs-target="#viewInvoiceModal"
                                        data-id="<?= $inv['id'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (in_array($inv['status'], ['pending', 'partial'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success me-1" title="Record Payment"
                                            data-bs-toggle="modal" data-bs-target="#paymentModal"
                                            data-invoice-id="<?= $inv['id'] ?>"
                                            data-invoice-number="<?= htmlspecialchars($inv['invoice_number']) ?>"
                                            data-patient="<?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?>"
                                            data-total="<?= $inv['total'] ?>"
                                            data-paid="<?= $inv['paid_amount'] ?>"
                                            data-balance="<?= $inv['balance'] ?>">
                                            <i class="fas fa-credit-card"></i>
                                        </button>
                                        <a href="<?= APP_URL ?>/modules/billing/receipts.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($inv['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel invoice #<?= htmlspecialchars($inv['invoice_number']) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel_invoice">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
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

<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_invoice">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" class="form-select select2-patient" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link to Visit (optional)</label>
                        <select name="visit_id" class="form-select select2-visit">
                            <option value="">No visit</option>
                            <?php foreach ($visits as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['visit_number'] . ' - ' . $v['first_name'] . ' ' . $v['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-medium mb-0">Invoice Items</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInvoiceItem()"><i class="fas fa-plus me-1"></i>Add Item</button>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm" id="invoiceItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40%">Description</th>
                                    <th style="width:15%">Qty</th>
                                    <th style="width:20%">Unit Price</th>
                                    <th style="width:15%">Total</th>
                                    <th style="width:10%"></th>
                                </tr>
                            </thead>
                            <tbody id="invoiceItemsBody">
                                <tr class="inv-item">
                                    <td><input type="text" name="item_description[]" class="form-control form-control-sm" required></td>
                                    <td><input type="number" name="item_quantity[]" class="form-control form-control-sm item-qty" value="1" min="1" oninput="calcInvRow(this)"></td>
                                    <td><input type="number" step="0.01" name="item_unit_price[]" class="form-control form-control-sm item-price" value="0" min="0" oninput="calcInvRow(this)"></td>
                                    <td><input type="text" class="form-control form-control-sm item-total" readonly></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calcInvSummary();"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-medium">Subtotal</td>
                                    <td><input type="text" id="invSubtotal" class="form-control form-control-sm" readonly></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select" onchange="calcInvSummary()">
                                <option value="fixed">Fixed</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" name="discount" class="form-control" value="0" min="0" oninput="calcInvSummary()">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="0" min="0" max="100" oninput="calcInvSummary()">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Total</label>
                            <input type="text" id="invGrandTotal" class="form-control fw-bold" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2 text-primary"></i>Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewInvoiceBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="printReceiptLink" class="btn btn-primary" target="_blank"><i class="fas fa-print me-1"></i>Print Receipt</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= APP_URL ?>/modules/billing/payments.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" id="pmtInvoiceId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card me-2 text-primary"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Invoice:</strong> <span id="pmtInvoiceNumber"></span><br>
                        <strong>Patient:</strong> <span id="pmtPatient"></span><br>
                        <strong>Total:</strong> <span id="pmtTotal"></span><br>
                        <strong>Paid:</strong> <span id="pmtPaid"></span><br>
                        <strong>Balance Due:</strong> <span id="pmtBalance"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="pmtAmount" class="form-control" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction ID</label>
                        <input type="text" name="transaction_id" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addInvoiceItem() {
    var tbody = document.getElementById('invoiceItemsBody');
    var tr = document.createElement('tr');
    tr.className = 'inv-item';
    tr.innerHTML =
        '<td><input type="text" name="item_description[]" class="form-control form-control-sm" required></td>' +
        '<td><input type="number" name="item_quantity[]" class="form-control form-control-sm item-qty" value="1" min="1" oninput="calcInvRow(this)"></td>' +
        '<td><input type="number" step="0.01" name="item_unit_price[]" class="form-control form-control-sm item-price" value="0" min="0" oninput="calcInvRow(this)"></td>' +
        '<td><input type="text" class="form-control form-control-sm item-total" readonly></td>' +
        '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'tr\').remove(); calcInvSummary();"><i class="fas fa-trash"></i></button></td>';
    tbody.appendChild(tr);
}

function calcInvRow(el) {
    var tr = el.closest('tr');
    var qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
    var price = parseFloat(tr.querySelector('.item-price').value) || 0;
    tr.querySelector('.item-total').value = (qty * price).toFixed(2);
    calcInvSummary();
}

function calcInvSummary() {
    var totals = document.querySelectorAll('.item-total');
    var subtotal = 0;
    totals.forEach(function(el) {
        subtotal += parseFloat(el.value) || 0;
    });
    document.getElementById('invSubtotal').value = subtotal.toFixed(2);
    var discount = parseFloat(document.querySelector('[name="discount"]').value) || 0;
    var discType = document.querySelector('[name="discount_type"]').value;
    var taxRate = parseFloat(document.querySelector('[name="tax_rate"]').value) || 0;
    var discAmount = discType === 'percentage' ? (subtotal * discount / 100) : discount;
    var taxable = subtotal - discAmount;
    var tax = taxable * taxRate / 100;
    var total = taxable + tax;
    document.getElementById('invGrandTotal').value = total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        $('.select2-patient').select2({theme: 'bootstrap-5', dropdownParent: $('#createInvoiceModal'), width: '100%'});
        $('.select2-visit').select2({theme: 'bootstrap-5', dropdownParent: $('#createInvoiceModal'), width: '100%'});
    }

    var viewModal = document.getElementById('viewInvoiceModal');
    viewModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id');
        var body = document.getElementById('viewInvoiceBody');
        body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</div>';
        fetch('<?= APP_URL ?>/api/billing/invoice-details.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                    return;
                }
                var html = '<div class="row mb-3"><div class="col-sm-6"><small class="text-muted">Invoice #</small><br><strong>' + data.invoice_number + '</strong></div>' +
                    '<div class="col-sm-6 text-sm-end"><small class="text-muted">Date</small><br><strong>' + data.invoice_date + '</strong></div></div>' +
                    '<div class="row mb-3"><div class="col-sm-6"><small class="text-muted">Patient</small><br><strong>' + data.patient_name + '</strong><br><small>' + data.patient_number + '</small></div>' +
                    '<div class="col-sm-6 text-sm-end"><small class="text-muted">Status</small><br>' + data.status_badge + '</div></div>';
                if (data.items && data.items.length) {
                    html += '<table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>';
                    data.items.forEach(function(it) {
                        html += '<tr><td>' + it.description + '</td><td>' + it.quantity + '</td><td>' + it.unit_price + '</td><td>' + it.total + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                if (parseFloat(data.discount) > 0) {
                    html += '<div class="row"><div class="col-sm-6"></div><div class="col-sm-6"><div class="d-flex justify-content-between"><span>Discount:</span><span>-' + data.discount + '</span></div></div></div>';
                }
                if (parseFloat(data.tax) > 0) {
                    html += '<div class="row"><div class="col-sm-6"></div><div class="col-sm-6"><div class="d-flex justify-content-between"><span>Tax:</span><span>' + data.tax + '</span></div></div></div>';
                }
                html += '<div class="row"><div class="col-sm-6"></div><div class="col-sm-6"><div class="d-flex justify-content-between fw-bold"><span>Total:</span><span>' + data.total + '</span></div>' +
                    '<div class="d-flex justify-content-between"><span>Paid:</span><span>' + data.paid_amount + '</span></div>' +
                    '<div class="d-flex justify-content-between fw-bold ' + (parseFloat(data.balance) > 0 ? 'text-danger' : 'text-success') + '"><span>Balance:</span><span>' + data.balance + '</span></div></div></div>';
                body.innerHTML = html;
                document.getElementById('printReceiptLink').href = '<?= APP_URL ?>/modules/billing/receipts.php?invoice_id=' + data.id;
            })
            .catch(function() {
                body.innerHTML = '<div class="alert alert-danger">Failed to load invoice details.</div>';
            });
    });

    var pmtModal = document.getElementById('paymentModal');
    pmtModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        document.getElementById('pmtInvoiceId').value = btn.getAttribute('data-invoice-id');
        document.getElementById('pmtInvoiceNumber').textContent = btn.getAttribute('data-invoice-number');
        document.getElementById('pmtPatient').textContent = btn.getAttribute('data-patient');
        document.getElementById('pmtTotal').textContent = parseFloat(btn.getAttribute('data-total')).toFixed(2);
        document.getElementById('pmtPaid').textContent = parseFloat(btn.getAttribute('data-paid')).toFixed(2);
        var balance = parseFloat(btn.getAttribute('data-balance'));
        document.getElementById('pmtBalance').textContent = balance.toFixed(2);
        document.getElementById('pmtAmount').max = balance;
        document.getElementById('pmtAmount').value = balance.toFixed(2);
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
