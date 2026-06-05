<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeArray($array) {
    return array_map('sanitize', $array);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $phone);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generatePatientNumber() {
    $year = date('Y');
    $prefix = 'HMS';
    $last = Database::fetch("SELECT patient_number FROM patients ORDER BY id DESC LIMIT 1");
    if ($last) {
        $num = intval(substr($last['patient_number'], -6)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . $year . str_pad($num, 6, '0', STR_PAD_LEFT);
}

function generateVisitNumber() {
    $prefix = 'VIS';
    $last = Database::fetch("SELECT visit_number FROM visits ORDER BY id DESC LIMIT 1");
    if ($last) {
        $num = intval(substr($last['visit_number'], -6)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . date('Ymd') . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateInvoiceNumber() {
    $prefix = 'INV';
    $last = Database::fetch("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    if ($last) {
        $num = intval(substr($last['invoice_number'], -6)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . date('Ymd') . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateReceiptNumber() {
    return 'RCP' . date('YmdHis') . rand(100, 999);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function csrf_token() {
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

function verify_csrf($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function redirect($path) {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function redirectTo($url) {
    header('Location: ' . $url);
    exit;
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function set_flash($key, $message, $type = 'info') {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function get_flash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

function has_flash($key) {
    return isset($_SESSION['flash'][$key]);
}

function display_flash($key) {
    $flash = get_flash($key);
    if ($flash) {
        $icons = [
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle'
        ];
        $icon = $icons[$flash['type']] ?? 'fa-info-circle';
        return '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show">
            <i class="fas ' . $icon . '"></i> ' . htmlspecialchars($flash['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
    return '';
}

function formatDate($date, $format = 'd M Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd M Y H:i') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

function formatCurrency($amount) {
    $currency = getSetting('currency', 'TZS');
    $currencies_no_decimals = ['TShs', 'TZS', 'KES', 'UGX', 'RWF', 'GHS', 'ZAR'];
    if (in_array($currency, $currencies_no_decimals)) {
        return $currency . ' ' . number_format($amount, 0);
    }
    return $currency . ' ' . number_format($amount, 2);
}

function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    foreach ($intervals as $seconds => $label) {
        $interval = floor($diff / $seconds);
        if ($interval >= 1) {
            return $interval . ' ' . $label . ($interval > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function truncate($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function uploadFile($file, $directory = 'uploads', $allowed_types = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = $allowed_types ?? ALLOWED_EXTENSIONS;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $upload_path = UPLOAD_PATH . '/' . $directory;
    if (!is_dir($upload_path)) mkdir($upload_path, 0775, true);
    if (move_uploaded_file($file['tmp_name'], $upload_path . '/' . $filename)) {
        return $directory . '/' . $filename;
    }
    return null;
}

function paginate($total, $page = 1, $limit = null) {
    $limit = $limit ?? PAGINATION_LIMIT;
    $total_pages = ceil($total / $limit);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $limit;
    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset,
        'total' => $total,
        'total_pages' => $total_pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages,
        'prev_page' => $page - 1,
        'next_page' => $page + 1
    ];
}

function getSetting($key, $default = null) {
    $setting = Database::fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
    return $setting ? $setting['value'] : $default;
}

function setSetting($key, $value) {
    Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
        [$key, $value, $value]
    );
}

function logActivity($userId, $action, $module = null, $description = null, $metadata = null) {
    Database::query(
        "INSERT INTO user_activity_logs (user_id, action, module, description, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $metadata ? json_encode($metadata) : null
        ]
    );
}

function auditLog($userId, $action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
    Database::query(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]
    );
}

function createNotification($userId, $type, $title, $message, $link = null, $referenceType = null, $referenceId = null) {
    Database::query(
        "INSERT INTO notifications (user_id, type, title, message, link, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $link, $referenceType, $referenceId]
    );
}

function getUnreadNotifications($userId, $limit = 10) {
    return Database::fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?",
        [$userId, $limit]
    );
}

function getNotificationCount($userId) {
    return Database::fetch(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['count'];
}

function getDepartmentUsers($departmentId) {
    return Database::fetchAll("SELECT id, first_name, last_name FROM users WHERE department_id = ? AND status = 'active'", [$departmentId]);
}

function getRoleName($roleId) {
    $role = Database::fetch("SELECT display_name FROM roles WHERE id = ?", [$roleId]);
    return $role ? $role['display_name'] : 'Unknown';
}

function getDepartmentName($deptId) {
    $dept = Database::fetch("SELECT name FROM departments WHERE id = ?", [$deptId]);
    return $dept ? $dept['name'] : 'Unknown';
}

function getPatientName($patientId) {
    $patient = Database::fetch("SELECT first_name, last_name FROM patients WHERE id = ?", [$patientId]);
    return $patient ? $patient['first_name'] . ' ' . $patient['last_name'] : 'Unknown';
}

function getUserName($userId) {
    $user = Database::fetch("SELECT first_name, last_name FROM users WHERE id = ?", [$userId]);
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown';
}

function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . APP_NAME . " <noreply@" . parse_url(APP_URL, PHP_URL_HOST) . ">\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $sent = @mail($to, $subject, $htmlBody, $headers);
    if (!$sent) {
        error_log("HMS Email: Failed to send to $to - subject: $subject");
    }
    return $sent;
}

function getPasswordResetEmailBody($resetLink, $userName, $expireMinutes = 30) {
    $appName = APP_NAME;
    $logoUrl = APP_URL . '/assets/img/favicon.svg';
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.wrapper{padding:40px 20px;max-width:600px;margin:0 auto}
.card{background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#0d6efd,#0a58ca);padding:40px 32px;text-align:center}
.header img{width:56px;height:56px;margin-bottom:12px}
.header h1{color:#fff;font-size:22px;margin:0;font-weight:700}
.body{padding:32px;color:#1a1f36}
.body h2{font-size:18px;margin:0 0 16px;color:#1a1f36}
.body p{font-size:14px;line-height:1.7;color:#6c757d;margin:0 0 16px}
.btn-wrap{text-align:center;margin:28px 0}
.btn{display:inline-block;background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;text-decoration:none;
padding:14px 40px;border-radius:12px;font-size:15px;font-weight:600}
.btn:hover{background:#0a58ca}
.footer{background:#f8f9fa;padding:24px 32px;text-align:center}
.footer p{font-size:12px;color:#8b8fa3;margin:3px 0}
.footer .expiry{color:#dc3545;font-weight:600}
</style></head>
<body><div class="wrapper">
<div class="card">
<div class="header"><img src="$logoUrl" alt="Logo"><h1>$appName</h1></div>
<div class="body">
<h2>Password Reset Request</h2>
<p>Hello <strong>$userName</strong>,</p>
<p>We received a request to reset your password for your $appName account. Click the button below to set a new password.</p>
<div class="btn-wrap"><a href="$resetLink" class="btn" target="_blank">Reset Password</a></div>
<p>If the button doesn't work, copy and paste this link into your browser:</p>
<p style="font-size:12px;word-break:break-all;color:#0d6efd">$resetLink</p>
<p class="expiry">This link expires in $expireMinutes minutes.</p>
</div>
<div class="footer">
<p>$appName &bull; All Rights Reserved &copy; $year</p>
<p>If you didn't request this password reset, please ignore this email or contact support.</p>
<p>This is an automated message, please do not reply.</p>
</div>
</div></div></body></html>
HTML;
}

function getStatusBadge($status, $type = 'default') {
    $colors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
        'scheduled' => 'info',
        'confirmed' => 'primary',
        'checked_in' => 'info',
        'in_progress' => 'warning',
        'waiting' => 'warning',
        'admitted' => 'info',
        'discharged' => 'success',
        'paid' => 'success',
        'partial' => 'warning',
        'overdue' => 'danger',
        'available' => 'success',
        'occupied' => 'danger',
        'reserved' => 'warning',
        'urgent' => 'danger',
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'info',
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span>';
}
