<?php
define('APP_NAME', 'Hospital Management System');
define('APP_URL', 'http://localhost/HospitalMS');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Africa/Nairobi');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
define('PAGINATION_LIMIT', 20);
define('SESSION_LIFETIME', 7200);
define('CSRF_TOKEN_NAME', 'hms_csrf_token');
