<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    json_response(['error' => 'Invalid invoice ID'], 400);
}

$invoice = Database::fetch(
    "SELECT i.*, p.first_name, p.last_name, p.patient_number 
     FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = ?",
    [$id]
);

if (!$invoice) {
    json_response(['error' => 'Invoice not found'], 404);
}

$items = Database::fetchAll(
    "SELECT description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ?",
    [$id]
);

$payments = Database::fetchAll(
    "SELECT pm.amount, pm.payment_method, pm.payment_date, pm.receipt_number
     FROM payments pm WHERE pm.invoice_id = ? ORDER BY pm.created_at ASC",
    [$id]
);

json_response([
    'id' => $invoice['id'],
    'invoice_number' => $invoice['invoice_number'],
    'patient_name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
    'patient_number' => $invoice['patient_number'],
    'invoice_date' => formatDate($invoice['invoice_date']),
    'subtotal' => formatCurrency($invoice['subtotal']),
    'discount' => formatCurrency($invoice['discount']),
    'tax' => formatCurrency($invoice['tax']),
    'total' => formatCurrency($invoice['total']),
    'paid_amount' => formatCurrency($invoice['paid_amount']),
    'balance' => formatCurrency($invoice['balance']),
    'status' => $invoice['status'],
    'status_badge' => getStatusBadge($invoice['status']),
    'items' => array_map(function($it) {
        return [
            'description' => $it['description'],
            'quantity' => $it['quantity'],
            'unit_price' => formatCurrency($it['unit_price']),
            'total' => formatCurrency($it['total']),
        ];
    }, $items),
    'payments' => array_map(function($pm) {
        return [
            'amount' => formatCurrency($pm['amount']),
            'method' => $pm['payment_method'],
            'date' => formatDateTime($pm['payment_date']),
            'receipt' => $pm['receipt_number'],
        ];
    }, $payments),
]);
