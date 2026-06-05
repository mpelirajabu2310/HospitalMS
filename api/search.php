<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'department_doctors') {
    $deptId = intval($_GET['department_id'] ?? 0);
    $users = Database::fetchAll(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as text
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.department_id = ? AND u.status = 'active' AND r.name = 'doctor'
         ORDER BY u.first_name",
        [$deptId]
    );
    echo json_encode($users);
    exit;
}

if ($action === 'prescription_details') {
    $prescriptionId = intval($_GET['id'] ?? 0);
    $prescription = Database::fetch(
        "SELECT p.*, CONCAT(pt.first_name, ' ', pt.last_name) as patient_name, pt.patient_number
         FROM prescriptions p
         JOIN patients pt ON p.patient_id = pt.id
         WHERE p.id = ?",
        [$prescriptionId]
    );
    if (!$prescription) {
        echo json_encode(['error' => 'Prescription not found.']);
        exit;
    }
    $items = Database::fetchAll(
        "SELECT pi.*, m.name as medicine_name, m.strength
         FROM prescription_items pi
         JOIN medicines m ON pi.medicine_id = m.id
         WHERE pi.prescription_id = ?",
        [$prescriptionId]
    );
    ob_start();
    $badge = getStatusBadge($prescription['status']);
    ob_end_clean();
    echo json_encode([
        'patient_name' => $prescription['patient_name'],
        'patient_number' => $prescription['patient_number'],
        'prescription_date' => formatDate($prescription['prescription_date']),
        'status_badge' => $badge,
        'notes' => $prescription['notes'],
        'items' => $items
    ]);
    exit;
}

$q = trim($_GET['q'] ?? '');
$results = [];

if (strlen($q) >= 2) {
    $term = "%$q%";

    $patients = Database::fetchAll(
        "SELECT id, first_name, last_name, patient_number FROM patients
         WHERE first_name LIKE ? OR last_name LIKE ? OR patient_number LIKE ? OR phone LIKE ?
         ORDER BY first_name LIMIT 5",
        [$term, $term, $term, $term]
    );
    foreach ($patients as $p) {
        $results[] = [
            'url' => APP_URL . '/modules/patients/profile.php?id=' . $p['id'],
            'label' => $p['first_name'] . ' ' . $p['last_name'],
            'sub' => $p['patient_number'],
            'icon' => 'fa-user'
        ];
    }

    $appointments = Database::fetchAll(
        "SELECT a.id, a.appointment_date, a.appointment_time, CONCAT(p.first_name, ' ', p.last_name) as patient_name, a.status
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR a.status LIKE ?
         ORDER BY a.appointment_date DESC LIMIT 5",
        [$term, $term, $term]
    );
    foreach ($appointments as $a) {
        $results[] = [
            'url' => APP_URL . '/modules/reception/appointments.php?date=' . $a['appointment_date'],
            'label' => $a['patient_name'] . ' - ' . $a['appointment_date'],
            'sub' => 'Appointment: ' . ucfirst($a['status']),
            'icon' => 'fa-calendar-check'
        ];
    }

    $users = Database::fetchAll(
        "SELECT id, first_name, last_name, username FROM users
         WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ?
         ORDER BY first_name LIMIT 5",
        [$term, $term, $term]
    );
    foreach ($users as $u) {
        $results[] = [
            'url' => APP_URL . '/modules/admin/users.php',
            'label' => $u['first_name'] . ' ' . $u['last_name'],
            'sub' => $u['username'],
            'icon' => 'fa-user-md'
        ];
    }
}

echo json_encode($results);
