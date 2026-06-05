<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (Auth::check()) {
    redirect('index.php');
}

$error = '';

if (Auth::hasUsers()) {
    $registrationClosed = true;
} else {
    $registrationClosed = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'username' => sanitize($_POST['username'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
        ];

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $error = 'All required fields must be filled.';
        } elseif (!validateEmail($data['email'])) {
            $error = 'Please enter a valid email address.';
        } elseif (!empty($data['phone']) && !validatePhone($data['phone'])) {
            $error = 'Please enter a valid phone number.';
        } elseif (strlen($data['password']) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($data['password'] !== $data['confirm_password']) {
            $error = 'Passwords do not match.';
        } elseif (Database::fetch("SELECT id FROM users WHERE username = ?", [$data['username']])) {
            $error = 'Username is already taken.';
        } elseif (Database::fetch("SELECT id FROM users WHERE email = ?", [$data['email']])) {
            $error = 'Email is already registered.';
        } elseif (Auth::registerFirstUser($data)) {
            set_flash('success', 'Super Admin account created successfully! Please log in.', 'success');
            redirect('login.php');
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
$theme = $_COOKIE['hms_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .auth-wrapper {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #084298 100%);
            padding: 20px;
        }
        .auth-card {
            background: var(--bg-card); border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.2);
            padding: 36px; width: 100%; max-width: 520px;
            transition: background 0.3s;
        }
        .auth-logo {
            display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
            padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
        }
        .auth-logo .logo-icon {
            width: 52px; height: 52px; flex-shrink: 0;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
            box-shadow: 0 6px 20px rgba(13,110,253,0.3);
        }
        .auth-logo .logo-text { flex: 1; }
        .auth-logo .logo-text h3 { font-weight: 700; color: var(--text-primary); font-size: 20px; margin: 0 0 2px; }
        .auth-logo .logo-text p { color: var(--text-muted); font-size: 12px; margin: 0; }
            margin: 0;
        }
        .auth-card .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .auth-card .form-control {
            height: 44px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 13px;
            transition: all 0.2s;
        }
        .auth-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
            background: var(--bg-card);
        }
        .auth-card .btn-primary {
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            border: none;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            transition: all 0.2s;
        }
        .auth-card .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(13,110,253,0.4);
        }
        .alert { border-radius: 10px; font-size: 13px; border: none; padding: 10px 14px; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <?php if ($registrationClosed): ?>
                <div class="auth-logo">
                    <div class="logo-icon"><i class="fas fa-hospital-alt"></i></div>
                    <div class="logo-text">
                        <h3>Create Super Admin</h3>
                        <p><?= APP_NAME ?></p>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-1"></i> New user registration is disabled. Contact your system administrator.
                </div>
                <a href="login.php" class="btn btn-primary mt-3">
                    <i class="fas fa-sign-in-alt me-1"></i> Back to Login
                </a>
            <?php else: ?>
                <div class="auth-logo">
                    <div class="logo-icon"><i class="fas fa-hospital-alt"></i></div>
                    <div class="logo-text">
                        <h3>Create Super Admin</h3>
                        <p><?= APP_NAME ?> &bull; First-time Setup</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?= display_flash('success') ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" placeholder="John" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" placeholder="Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="johndoe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="john@hospital.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="+254700000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fas fa-user-shield me-1"></i> Create Super Admin Account
                    </button>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Already have an account?</small>
                    <a href="login.php" class="small fw-medium text-decoration-none">Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
