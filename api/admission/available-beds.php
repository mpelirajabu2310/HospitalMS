<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$wardId = intval($_GET['ward_id'] ?? 0);
if (!$wardId) {
    json_response(['error' => 'Ward ID required'], 400);
}

$beds = Database::fetchAll(
    "SELECT id, bed_number, bed_type, price_per_day, status, notes 
     FROM beds 
     WHERE ward_id = ? AND status = 'available'
     ORDER BY bed_number ASC",
    [$wardId]
);

if (empty($beds)) {
    json_response(['error' => 'No available beds in this ward'], 404);
}

json_response(array_map(function($b) {
    return [
        'id' => intval($b['id']),
        'bed_number' => $b['bed_number'],
        'bed_type' => $b['bed_type'],
        'price_per_day' => floatval($b['price_per_day']),
        'notes' => $b['notes'],
    ];
}, $beds));
