<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        Database::query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0", [$userId]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$notifications = Database::fetchAll(
    "SELECT id, type, title, message, link, reference_type, reference_id, is_read, created_at
     FROM notifications
     WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC
     LIMIT 20",
    [$userId]
);

$count = count($notifications);

header('Content-Type: application/json');
echo json_encode([
    'count' => $count,
    'notifications' => $notifications
]);
