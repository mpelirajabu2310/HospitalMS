<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (Auth::check()) {
    redirect('index.php');
}

$appName = APP_NAME;
$appUrl = APP_URL;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            Auth::createPasswordReset($email);
            $success = 'If an account with that email exists, a password reset link has been sent. Please check your inbox.';
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
    <title>Forgot Password - <?= $appName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= $appUrl ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .auth-wrapper {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #084298 100%);
            padding: 20px;
        }
        .auth-card {
            background: var(--bg-card); border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.2);
            padding: 40px 36px; width: 100%; max-width: 420px;
            transition: background 0.3s;
        }
        .auth-logo {
            display: flex; align-items: center; gap: 14px; margin-bottom: 28px;
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
        .auth-card .form-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .auth-card .input-group-field { position: relative; margin-bottom: 18px; }
        .auth-card .input-group-field .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); z-index: 10; font-size: 15px;
        }
        .auth-card .input-group-field .form-control {
            height: 48px; padding-left: 42px; border-radius: 12px;
            border: 2px solid var(--border-color); background: var(--bg-body);
            color: var(--text-primary); font-size: 14px; transition: all 0.2s;
        }
        .auth-card .input-group-field .form-control:focus {
            border-color: var(--primary); box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
            background: var(--bg-card);
        }
        .auth-card .btn-primary {
            height: 48px; border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            border: none; font-weight: 600; font-size: 15px;
            width: 100%; transition: all 0.2s;
        }
        .auth-card .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(13,110,253,0.4); }
        .alert { border-radius: 12px; font-size: 13px; border: none; padding: 12px 16px; }
        .back-link { text-align: center; margin-top: 18px; }
        .back-link a { font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px; }
            .auth-logo { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="logo-icon"><i class="fas fa-hospital-alt"></i></div>
                <div class="logo-text">
                    <h3>Forgot Password</h3>
                    <p><?= $appName ?></p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
                </div>
                <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <p style="font-size:13px;color:var(--text-secondary);margin:0 0 20px;line-height:1.6">
                    Enter your registered email address and we'll send you a link to reset your password.
                </p>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="input-group-field">
                        <label class="form-label">Email Address</label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane me-1"></i> Send Reset Link</button>
                </form>
                <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a></div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
