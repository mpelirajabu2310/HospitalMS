<?php
define('PAGE_TITLE', 'Payments');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $invoiceId = intval($_POST['invoice_id']);
        $amount = floatval($_POST['amount']);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
        $transactionId = sanitize($_POST['transaction_id'] ?? '');
        $referenceNumber = sanitize($_POST['reference_number'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        $invoice = Database::fetch("SELECT i.*, p.first_name, p.last_name, p.id as patient_id FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = ?", [$invoiceId]);
        if (!$invoice) {
            set_flash('error', 'Invoice not found.', 'danger');
            redirect('modules/billing/payments.php');
        }

        if ($amount <= 0 || $amount > $invoice['balance']) {
            set_flash('error', 'Amount must be between 0.01 and ' . formatCurrency($invoice['balance']), 'warning');
            redirect('modules/billing/payments.php');
        }

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            $receiptNumber = generateReceiptNumber();
            Database::insert(
                "INSERT INTO payments (invoice_id, patient_id, amount, payment_method, payment_date, transaction_id, reference_number, receipt_number, notes, received_by)
                 VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)",
                [$invoiceId, $invoice['patient_id'], $amount, $paymentMethod, $transactionId, $referenceNumber, $receiptNumber, $notes, $userId]
            );

            $newPaid = $invoice['paid_amount'] + $amount;
            if ($newPaid >= $invoice['total']) {
                $newStatus = 'paid';
            } elseif ($newPaid > 0) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'pending';
            }
            Database::query(
                "UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?",
                [$newPaid, $newStatus, $invoiceId]
            );

            $db->commit();
            logActivity($userId, 'payment_recorded', 'billing', "Payment of " . formatCurrency($amount) . " recorded for invoice #{$invoice['invoice_number']}");
            set_flash('success', "Payment of " . formatCurrency($amount) . " received. Receipt #$receiptNumber");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Payment failed: ' . $e->getMessage(), 'danger');
        }
        redirect('modules/billing/payments.php');
    }
}

$methodFilter = sanitize($_GET['payment_method'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = [];
$params = [];
if ($methodFilter) {
    $where[] = 'pm.payment_method = ?';
    $params[] = $methodFilter;
}
if ($dateFrom) {
    $where[] = 'DATE(pm.payment_date) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(pm.payment_date) <= ?';
    $params[] = $dateTo;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$payments = Database::fetchAll(
    "SELECT pm.*, p.first_name, p.last_name, p.patient_number, i.invoice_number,
            u.first_name as u_first, u.last_name as u_last
     FROM payments pm
     JOIN invoices i ON pm.invoice_id = i.id
     JOIN patients p ON pm.patient_id = p.id
     JOIN users u ON pm.received_by = u.id
     $whereClause
     ORDER BY pm.created_at DESC
     LIMIT 100",
    $params
);

$pendingInvoices = Database::fetchAll(
    "SELECT i.id, i.invoice_number, i.total, i.paid_amount, i.balance,
            p.first_name, p.last_name, p.patient_number
     FROM invoices i JOIN patients p ON i.patient_id = p.id
     WHERE i.status IN ('pending', 'partial') ORDER BY i.created_at DESC LIMIT 50"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Payments</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPaymentModal">
        <i class="fas fa-plus me-1"></i>Record Payment
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Payment Method</label>
                <select name="payment_method" class="form-select">
                    <option value="">All</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="insurance" <?= $methodFilter === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                    <option value="mobile_money" <?= $methodFilter === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                    <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
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
                        <th>Receipt #</th>
                        <th>Patient</th>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Received By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No payments recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pm): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($pm['receipt_number']) ?></code></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $pm['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($pm['first_name'] . ' ' . $pm['last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($pm['patient_number']) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($pm['invoice_number']) ?></code></td>
                                <td class="fw-bold text-success"><?= formatCurrency($pm['amount']) ?></td>
                                <td><?= getStatusBadge(ucwords(str_replace('_', ' ', $pm['payment_method']))) ?></td>
                                <td class="small"><?= formatDateTime($pm['payment_date']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($pm['u_first'] . ' ' . $pm['u_last']) ?></td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/modules/billing/receipts.php?payment_id=<?= $pm['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank" title="Print Receipt">
                                        <i class="fas fa-print"></i>
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

<div class="modal fade" id="newPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="record_payment">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Invoice <span class="text-danger">*</span></label>
                        <select name="invoice_id" class="form-select select2-invoice" required onchange="loadInvoiceDetails(this.value)">
                            <option value="">-- Select Invoice --</option>
                            <?php foreach ($pendingInvoices as $inv): ?>
                                <option value="<?= $inv['id'] ?>"
                                    data-invoice="<?= htmlspecialchars($inv['invoice_number']) ?>"
                                    data-patient="<?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?>"
                                    data-total="<?= $inv['total'] ?>"
                                    data-paid="<?= $inv['paid_amount'] ?>"
                                    data-balance="<?= $inv['balance'] ?>">
                                    <?= htmlspecialchars($inv['invoice_number'] . ' - ' . $inv['first_name'] . ' ' . $inv['last_name'] . ' (Balance: ' . formatCurrency($inv['balance']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="invoiceDetails" class="card mb-3 d-none">
                        <div class="card-body py-2">
                            <div class="row small">
                                <div class="col-md-3"><span class="text-muted">Invoice:</span> <strong id="detInvoice"></strong></div>
                                <div class="col-md-3"><span class="text-muted">Patient:</span> <strong id="detPatient"></strong></div>
                                <div class="col-md-2"><span class="text-muted">Total:</span> <strong id="detTotal"></strong></div>
                                <div class="col-md-2"><span class="text-muted">Paid:</span> <strong id="detPaid"></strong></div>
                                <div class="col-md-2"><span class="text-muted">Balance:</span> <strong id="detBalance" class="text-danger"></strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" id="pmtAmount" class="form-control" required min="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="datetime-local" name="payment_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" name="reference_number" class="form-control">
                        </div>
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
function loadInvoiceDetails(invoiceId) {
    var sel = document.querySelector('.select2-invoice');
    if (!invoiceId) {
        document.getElementById('invoiceDetails').classList.add('d-none');
        return;
    }
    var opt = sel.querySelector('option[value="' + invoiceId + '"]');
    if (opt) {
        document.getElementById('detInvoice').textContent = opt.getAttribute('data-invoice');
        document.getElementById('detPatient').textContent = opt.getAttribute('data-patient');
        document.getElementById('detTotal').textContent = parseFloat(opt.getAttribute('data-total')).toFixed(2);
        document.getElementById('detPaid').textContent = parseFloat(opt.getAttribute('data-paid')).toFixed(2);
        var bal = parseFloat(opt.getAttribute('data-balance'));
        document.getElementById('detBalance').textContent = bal.toFixed(2);
        document.getElementById('pmtAmount').max = bal;
        document.getElementById('pmtAmount').value = bal.toFixed(2);
        document.getElementById('invoiceDetails').classList.remove('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        $('.select2-invoice').select2({theme: 'bootstrap-5', dropdownParent: $('#newPaymentModal'), width: '100%'});
    }
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
