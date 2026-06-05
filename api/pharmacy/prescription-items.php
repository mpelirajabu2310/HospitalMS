<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'No prescription ID provided.']);
    exit;
}

$prescription = Database::fetch(
    "SELECT id FROM prescriptions WHERE id = ?",
    [$id]
);

if (!$prescription) {
    echo json_encode(['error' => 'Prescription not found.']);
    exit;
}

$items = Database::fetchAll(
    "SELECT pi.id, pi.medicine_id, pi.quantity, pi.dosage, pi.frequency, pi.duration, pi.dispensed_quantity,
            pi.status as item_status, m.name as medicine_name, m.strength, m.current_stock, m.selling_price, m.unit
     FROM prescription_items pi
     JOIN medicines m ON pi.medicine_id = m.id
     WHERE pi.prescription_id = ?
     ORDER BY pi.id ASC",
    [$id]
);

echo json_encode($items);
