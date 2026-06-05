<?php
class Auth {
    public static function login($email, $password) {
        $user = Database::fetch(
            "SELECT u.*, r.name as role_name, r.display_name as role_display, 
             d.name as department_name, d.code as department_code
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             LEFT JOIN departments d ON u.department_id = d.id 
             WHERE (u.email = ? OR u.username = ?) AND u.status = 'active'",
            [$email, $email]
        );

        if (!$user || !verifyPassword($password, $user['password'])) {
            return false;
        }

        self::createSession($user);
        self::updateLastLogin($user['id']);

        logActivity($user['id'], 'login', 'auth', 'User logged in');
        auditLog($user['id'], 'login', 'users', $user['id']);

        return $user;
    }

    public static function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'role_display' => $user['role_display'],
            'department_id' => $user['department_id'],
            'department_name' => $user['department_name'],
            'department_code' => $user['department_code'],
            'avatar' => $user['avatar'],
        ];
        $_SESSION['logged_in'] = true;
        session_regenerate_id(true);
    }

    public static function updateLastLogin($userId) {
        Database::query(
            "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $userId]
        );
    }

    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', 'auth', 'User logged out');
            auditLog($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
        }
        $_SESSION = [];
        session_destroy();
    }

    public static function check() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function user() {
        return $_SESSION['user'] ?? null;
    }

    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role() {
        return $_SESSION['user']['role_name'] ?? null;
    }

    public static function requireAuth() {
        if (!self::check()) {
            redirect('modules/auth/login.php');
        }
    }

    public static function requireRole($roles) {
        if (!self::check()) {
            redirect('modules/auth/login.php');
        }
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array(self::role(), $roles)) {
            redirect('index.php');
        }
    }

    public static function requirePermission($permission) {
        if (!self::check()) {
            redirect('modules/auth/login.php');
        }
        if (!self::hasPermission($permission)) {
            set_flash('error', 'You do not have permission to perform this action.', 'warning');
            redirect('index.php');
        }
    }

    public static function hasPermission($permission) {
        if (self::role() === 'super_admin') return true;
        if (!self::check()) return false;

        $cacheKey = 'perms_' . self::id();
        $permissions = $_SESSION[$cacheKey] ?? null;

        if ($permissions === null) {
            $perms = Database::fetchAll(
                "SELECT p.name FROM permissions p 
                 JOIN role_permissions rp ON p.id = rp.permission_id 
                 WHERE rp.role_id = ?",
                [self::user()['role_id']]
            );
            $permissions = array_column($perms, 'name');
            $_SESSION[$cacheKey] = $permissions;
        }

        return in_array($permission, $permissions);
    }

    public static function userHasRole($roleName) {
        return self::role() === $roleName;
    }

    public static function isSuperAdmin() {
        return self::role() === 'super_admin';
    }

    public static function isAdmin() {
        return in_array(self::role(), ['super_admin', 'admin']);
    }

    public static function canManageUsers() {
        return self::isSuperAdmin() || self::hasPermission('manage_users');
    }

    public static function registerFirstUser($data) {
        $count = Database::fetch("SELECT COUNT(*) as count FROM users");
        if ($count['count'] > 0) {
            return false;
        }

        $roleId = Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['super_admin', 'Super Admin', 'System Super Administrator', 1]
        );

        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['admin', 'Administrator', 'System Administrator', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['doctor', 'Doctor', 'Medical Doctor', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['nurse', 'Nurse', 'Registered Nurse', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['receptionist', 'Receptionist', 'Front Desk Receptionist', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['pharmacist', 'Pharmacist', 'Pharmacist', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['lab_technician', 'Lab Technician', 'Laboratory Technician', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['cashier', 'Cashier', 'Billing/Cashier Officer', 1]
        );
        Database::insert(
            "INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)",
            ['records_officer', 'Records Officer', 'Medical Records Officer', 1]
        );

        $permissions = [
            ['manage_users', 'Manage Users', 'admin'],
            ['manage_roles', 'Manage Roles', 'admin'],
            ['manage_permissions', 'Manage Permissions', 'admin'],
            ['manage_departments', 'Manage Departments', 'admin'],
            ['manage_settings', 'Manage Settings', 'admin'],
            ['view_reports', 'View Reports', 'reports'],
            ['manage_patients', 'Manage Patients', 'patients'],
            ['view_patients', 'View Patients', 'patients'],
            ['manage_appointments', 'Manage Appointments', 'appointments'],
            ['manage_visits', 'Manage Visits', 'visits'],
            ['manage_consultations', 'Manage Consultations', 'consultations'],
            ['manage_prescriptions', 'Manage Prescriptions', 'prescriptions'],
            ['manage_medicines', 'Manage Medicines', 'pharmacy'],
            ['dispense_medicines', 'Dispense Medicines', 'pharmacy'],
            ['manage_lab_tests', 'Manage Lab Tests', 'laboratory'],
            ['perform_lab_tests', 'Perform Lab Tests', 'laboratory'],
            ['manage_invoices', 'Manage Invoices', 'billing'],
            ['process_payments', 'Process Payments', 'billing'],
            ['manage_admissions', 'Manage Admissions', 'admissions'],
            ['manage_wards', 'Manage Wards', 'wards'],
            ['manage_beds', 'Manage Beds', 'wards'],
            ['manage_referrals', 'Manage Referrals', 'referrals'],
            ['view_audit_logs', 'View Audit Logs', 'admin'],
            ['manage_nursing', 'Manage Nursing', 'nursing'],
            ['manage_medical_records', 'Manage Medical Records', 'records'],
        ];

        foreach ($permissions as $perm) {
            Database::insert(
                "INSERT INTO permissions (name, display_name, module) VALUES (?, ?, ?)",
                $perm
            );
        }

        $allPerms = Database::fetchAll("SELECT id FROM permissions");
        foreach ($allPerms as $perm) {
            Database::query(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$roleId, $perm['id']]
            );
        }

        $userId = Database::insert(
            "INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            [
                $data['username'],
                $data['email'],
                hashPassword($data['password']),
                $data['first_name'],
                $data['last_name'],
                $data['phone'] ?? '',
                $roleId
            ]
        );

        logActivity($userId, 'first_registration', 'auth', 'First user registered as Super Admin');
        return $userId;
    }

    public static function registerUser($data) {
        return Database::insert(
            "INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['username'],
                $data['email'],
                hashPassword($data['password']),
                $data['first_name'],
                $data['last_name'],
                $data['phone'] ?? '',
                $data['role_id'],
                $data['department_id'] ?? null,
                $data['status'] ?? 'active'
            ]
        );
    }

    public static function updatePassword($userId, $currentPassword, $newPassword) {
        $user = Database::fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        if (!$user || !verifyPassword($currentPassword, $user['password'])) {
            return false;
        }
        Database::query("UPDATE users SET password = ? WHERE id = ?", [hashPassword($newPassword), $userId]);
        logActivity($userId, 'password_change', 'auth', 'User changed password');
        return true;
    }

    // ============================================================
    // SECURE PASSWORD RESET (password_resets table, hashed tokens)
    // ============================================================

    public static function createPasswordReset($email) {
        $user = Database::fetch("SELECT id, email, first_name, last_name FROM users WHERE email = ? AND status = 'active'", [$email]);
        if (!$user) return false;

        self::expireOldTokens($user['id']);

        $rawToken = generateToken(32);
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        Database::insert(
            "INSERT INTO password_resets (user_id, email, token, expires_at, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$user['id'], $user['email'], $hashedToken, $expiresAt, $ip, $ua]
        );

        $resetLink = APP_URL . '/modules/auth/reset-password.php?token=' . $rawToken;
        $subject = 'Password Reset - ' . APP_NAME;
        $htmlBody = getPasswordResetEmailBody($resetLink, $user['first_name'], 30);
        sendEmail($user['email'], $subject, $htmlBody);

        logActivity($user['id'], 'password_reset_request', 'auth', 'Password reset link sent to ' . $user['email']);
        auditLog($user['id'], 'password_reset_request', 'users', $user['id'], null, ['email' => $user['email'], 'ip' => $ip]);

        return true;
    }

    public static function validateResetToken($rawToken) {
        if (empty($rawToken)) return null;
        $hashedToken = hash('sha256', $rawToken);
        $record = Database::fetch(
            "SELECT * FROM password_resets WHERE token = ? AND status = 'pending' AND expires_at > NOW()",
            [$hashedToken]
        );
        return $record ?: null;
    }

    public static function resetPassword($rawToken, $password) {
        $record = self::validateResetToken($rawToken);
        if (!$record) return false;

        $userId = $record['user_id'];
        $hashedToken = $record['token'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        Database::query("UPDATE users SET password = ? WHERE id = ?", [hashPassword($password), $userId]);

        Database::query(
            "UPDATE password_resets SET status = 'used', completed_at = NOW() WHERE token = ?",
            [$hashedToken]
        );

        logActivity($userId, 'password_reset', 'auth', 'Password reset completed successfully');
        auditLog($userId, 'password_reset', 'users', $userId, null, ['ip' => $ip]);

        return true;
    }

    public static function expireOldTokens($userId) {
        Database::query(
            "UPDATE password_resets SET status = 'expired' WHERE user_id = ? AND status = 'pending'",
            [$userId]
        );
    }

    public static function sendPasswordResetLink($email) {
        return self::createPasswordReset($email);
    }

    public static function updateProfile($userId, $data) {
        $allowed = ['first_name', 'last_name', 'phone', 'avatar'];
        $updates = [];
        $params = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($updates)) return false;
        $params[] = $userId;
        Database::query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        if (isset($data['first_name'])) $_SESSION['user']['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $_SESSION['user']['last_name'] = $data['last_name'];
        if (isset($data['phone'])) $_SESSION['user']['phone'] = $data['phone'];
        if (isset($data['avatar'])) $_SESSION['user']['avatar'] = $data['avatar'];

        logActivity($userId, 'profile_update', 'auth', 'User updated profile');
        return true;
    }

    public static function hasUsers() {
        $result = Database::fetch("SELECT COUNT(*) as count FROM users");
        return $result['count'] > 0;
    }
}
