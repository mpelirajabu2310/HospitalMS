<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

date_default_timezone_set(TIMEZONE);

if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

$db = Database::getInstance();
$pdo = $db->getConnection();
