<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$results = [];

if (strlen($q) >= 2) {
    $term = "%$q%";
    $patients = Database::fetchAll(
        "SELECT id, first_name, last_name, patient_number, phone FROM patients
         WHERE first_name LIKE ? OR last_name LIKE ? OR patient_number LIKE ? OR phone LIKE ? OR id_number LIKE ?
         ORDER BY first_name LIMIT 20",
        [$term, $term, $term, $term, $term]
    );

    foreach ($patients as $p) {
        $results[] = [
            'id' => $p['id'],
            'text' => $p['first_name'] . ' ' . $p['last_name'] . ' - ' . $p['patient_number'] . ' - ' . $p['phone']
        ];
    }
}

echo json_encode($results);
