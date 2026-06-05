<?php
define('PAGE_TITLE', 'Receipts');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$paymentId = intval($_GET['payment_id'] ?? 0);
$invoiceId = intval($_GET['invoice_id'] ?? 0);

if ($paymentId) {
    $payment = Database::fetch(
        "SELECT pm.*, p.first_name, p.last_name, p.patient_number, p.phone, p.address_line1, p.city,
                i.invoice_number, i.invoice_date, i.total as invoice_total, i.paid_amount, i.discount, i.tax, i.subtotal,
                u.first_name as u_first, u.last_name as u_last
         FROM payments pm
         JOIN patients p ON pm.patient_id = p.id
         JOIN invoices i ON pm.invoice_id = i.id
         JOIN users u ON pm.received_by = u.id
         WHERE pm.id = ?",
        [$paymentId]
    );
    $items = Database::fetchAll(
        "SELECT description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ?",
        [$payment['invoice_id']]
    );
    $hospitalName = getSetting('hospital_name', 'Hospital Management System');
    $hospitalAddress = getSetting('hospital_address', '');
    $hospitalPhone = getSetting('hospital_phone', '');
    $hospitalEmail = getSetting('hospital_email', '');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - <?= htmlspecialchars($payment['receipt_number']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
        <style>
            @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none !important; } }
            .receipt-box { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #dee2e6; border-radius: 8px; }
            .receipt-header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #0d6efd; }
            .receipt-header h3 { color: #0d6efd; font-weight: 700; }
            .receipt-title { text-align: center; margin-bottom: 20px; }
            .receipt-title h4 { border-bottom: 1px dashed #adb5bd; padding-bottom: 8px; }
        </style>
    </head>
    <body>
        <div class="text-center no-print my-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
            <a href="<?= APP_URL ?>/modules/billing/payments.php" class="btn btn-secondary">Back</a>
        </div>
        <div class="receipt-box">
            <div class="receipt-header">
                <h3><?= htmlspecialchars($hospitalName) ?></h3>
                <p class="mb-0"><?= nl2br(htmlspecialchars($hospitalAddress)) ?></p>
                <p class="mb-0">Phone: <?= htmlspecialchars($hospitalPhone) ?> | Email: <?= htmlspecialchars($hospitalEmail) ?></p>
            </div>
            <div class="receipt-title">
                <h4><i class="fas fa-receipt me-2"></i>OFFICIAL RECEIPT</h4>
                <p class="mb-0"><strong>Receipt #:</strong> <?= htmlspecialchars($payment['receipt_number']) ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= formatDateTime($payment['payment_date']) ?></p>
            </div>
            <div class="row mb-3">
                <div class="col-sm-6">
                    <strong>Patient:</strong> <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?><br>
                    <strong>Patient #:</strong> <?= htmlspecialchars($payment['patient_number']) ?><br>
                    <strong>Phone:</strong> <?= htmlspecialchars($payment['phone'] ?? '-') ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <strong>Invoice #:</strong> <?= htmlspecialchars($payment['invoice_number']) ?><br>
                    <strong>Invoice Date:</strong> <?= formatDate($payment['invoice_date']) ?>
                </div>
            </div>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['description']) ?></td>
                            <td class="text-center"><?= intval($it['quantity']) ?></td>
                            <td class="text-end"><?= formatCurrency($it['unit_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($it['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="row">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></p>
                    <?php if ($payment['transaction_id']): ?>
                        <p class="mb-1"><strong>Transaction ID:</strong> <?= htmlspecialchars($payment['transaction_id']) ?></p>
                    <?php endif; ?>
                    <?php if ($payment['reference_number']): ?>
                        <p class="mb-1"><strong>Reference:</strong> <?= htmlspecialchars($payment['reference_number']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <p class="mb-1"><strong>Subtotal:</strong> <?= formatCurrency($payment['subtotal']) ?></p>
                    <?php if (floatval($payment['discount']) > 0): ?>
                        <p class="mb-1"><strong>Discount:</strong> -<?= formatCurrency($payment['discount']) ?></p>
                    <?php endif; ?>
                    <?php if (floatval($payment['tax']) > 0): ?>
                        <p class="mb-1"><strong>Tax:</strong> <?= formatCurrency($payment['tax']) ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><strong>Total:</strong> <?= formatCurrency($payment['invoice_total']) ?></p>
                    <p class="mb-1"><strong>Paid:</strong> <?= formatCurrency($payment['amount']) ?></p>
                    <p class="mb-1"><strong>Balance:</strong> <?= formatCurrency($payment['invoice_total'] - $payment['paid_amount']) ?></p>
                </div>
            </div>
            <div class="text-center mt-4 pt-3 border-top text-muted small">
                <p class="mb-0">Thank you for choosing <?= htmlspecialchars($hospitalName) ?></p>
                <p class="mb-0">Generated on: <?= date('d M Y H:i:s') ?> | Received by: <?= htmlspecialchars($payment['u_first'] . ' ' . $payment['u_last']) ?></p>
            </div>
        </div>
        <div class="text-center no-print my-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
            <a href="<?= APP_URL ?>/modules/billing/payments.php" class="btn btn-secondary">Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($invoiceId) {
    $invoice = Database::fetch(
        "SELECT i.*, p.first_name, p.last_name, p.patient_number, p.phone, p.address_line1, p.city
         FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = ?",
        [$invoiceId]
    );
    if (!$invoice) { echo 'Invoice not found.'; exit; }
    $items = Database::fetchAll("SELECT description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
    $payments = Database::fetchAll(
        "SELECT pm.*, u.first_name as u_first, u.last_name as u_last FROM payments pm JOIN users u ON pm.received_by = u.id WHERE pm.invoice_id = ? ORDER BY pm.created_at ASC",
        [$invoiceId]
    );
    $hospitalName = getSetting('hospital_name', 'Hospital Management System');
    $hospitalAddress = getSetting('hospital_address', '');
    $hospitalPhone = getSetting('hospital_phone', '');
    $hospitalEmail = getSetting('hospital_email', '');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
        <style>
            @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none !important; } }
            .receipt-box { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #dee2e6; border-radius: 8px; }
            .receipt-header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #0d6efd; }
            .receipt-header h3 { color: #0d6efd; font-weight: 700; }
        </style>
    </head>
    <body>
        <div class="text-center no-print my-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
            <a href="<?= APP_URL ?>/modules/billing/invoices.php" class="btn btn-secondary">Back</a>
        </div>
        <div class="receipt-box">
            <div class="receipt-header">
                <h3><?= htmlspecialchars($hospitalName) ?></h3>
                <p class="mb-0"><?= nl2br(htmlspecialchars($hospitalAddress)) ?></p>
                <p class="mb-0">Phone: <?= htmlspecialchars($hospitalPhone) ?> | Email: <?= htmlspecialchars($hospitalEmail) ?></p>
            </div>
            <div class="receipt-title text-center mb-3">
                <h4><i class="fas fa-file-invoice me-2"></i>INVOICE</h4>
                <p class="mb-0"><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= formatDate($invoice['invoice_date']) ?></p>
                <p class="mb-0"><strong>Status:</strong> <?= getStatusBadge($invoice['status']) ?></p>
            </div>
            <div class="row mb-3">
                <div class="col-sm-6">
                    <strong>Patient:</strong> <?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?><br>
                    <strong>Patient #:</strong> <?= htmlspecialchars($invoice['patient_number']) ?><br>
                    <strong>Phone:</strong> <?= htmlspecialchars($invoice['phone'] ?? '-') ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <?php if ($invoice['due_date']): ?>
                        <strong>Due Date:</strong> <?= formatDate($invoice['due_date']) ?><br>
                    <?php endif; ?>
                </div>
            </div>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['description']) ?></td>
                            <td class="text-center"><?= intval($it['quantity']) ?></td>
                            <td class="text-end"><?= formatCurrency($it['unit_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($it['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="row">
                <div class="col-sm-6">
                    <?php if (!empty($payments)): ?>
                        <h6>Payment History</h6>
                        <table class="table table-sm table-borderless">
                            <?php foreach ($payments as $pmt): ?>
                                <tr>
                                    <td class="small"><?= formatDate($pmt['payment_date']) ?></td>
                                    <td class="small"><?= htmlspecialchars($pmt['receipt_number']) ?></td>
                                    <td class="text-end"><?= formatCurrency($pmt['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <p class="mb-1"><strong>Subtotal:</strong> <?= formatCurrency($invoice['subtotal']) ?></p>
                    <?php if (floatval($invoice['discount']) > 0): ?>
                        <p class="mb-1"><strong>Discount:</strong> -<?= formatCurrency($invoice['discount']) ?></p>
                    <?php endif; ?>
                    <?php if (floatval($invoice['tax']) > 0): ?>
                        <p class="mb-1"><strong>Tax:</strong> <?= formatCurrency($invoice['tax']) ?></p>
                    <?php endif; ?>
                    <p class="mb-1 fw-bold"><strong>Total:</strong> <?= formatCurrency($invoice['total']) ?></p>
                    <p class="mb-1"><strong>Paid:</strong> <?= formatCurrency($invoice['paid_amount']) ?></p>
                    <p class="mb-1 fw-bold <?= $invoice['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><strong>Balance:</strong> <?= formatCurrency($invoice['balance']) ?></p>
                </div>
            </div>
            <div class="text-center mt-4 pt-3 border-top text-muted small">
                <p class="mb-0">Thank you for choosing <?= htmlspecialchars($hospitalName) ?></p>
                <p class="mb-0">Generated on: <?= date('d M Y H:i:s') ?></p>
            </div>
        </div>
        <div class="text-center no-print my-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
            <a href="<?= APP_URL ?>/modules/billing/invoices.php" class="btn btn-secondary">Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$paymentsList = Database::fetchAll(
    "SELECT pm.id, pm.receipt_number, pm.amount, pm.payment_method, pm.payment_date,
            p.first_name, p.last_name, p.patient_number, i.invoice_number
     FROM payments pm
     JOIN patients p ON pm.patient_id = p.id
     JOIN invoices i ON pm.invoice_id = i.id
     ORDER BY pm.created_at DESC LIMIT 100"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-receipt me-2 text-primary"></i>Receipts</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>All Receipts</h5>
    </div>
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
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentsList)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No receipts found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paymentsList as $r): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($r['receipt_number']) ?></code></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $r['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['patient_number']) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($r['invoice_number']) ?></code></td>
                                <td class="fw-bold text-success"><?= formatCurrency($r['amount']) ?></td>
                                <td><?= getStatusBadge(ucwords(str_replace('_', ' ', $r['payment_method']))) ?></td>
                                <td class="small"><?= formatDateTime($r['payment_date']) ?></td>
                                <td class="text-end">
                                    <a href="<?= APP_URL ?>/modules/billing/receipts.php?payment_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                        <i class="fas fa-print me-1"></i>Print
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
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
