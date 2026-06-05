<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (Auth::check()) {
    redirect('index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email/username and password.';
    } elseif (Auth::login($email, $password)) {
        set_flash('success', 'Welcome back! You have been logged in successfully.', 'success');
        redirect('index.php');
    } else {
        $error = 'Invalid credentials or your account is inactive.';
    }
}
$theme = $_COOKIE['hms_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #084298 100%);
            padding: 24px;
        }

        .auth-card {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 32px;
            transition: background 0.3s;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .auth-header .logo-wrap {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 28px;
            color: #fff;
            box-shadow: 0 6px 20px rgba(13,110,253,0.3);
        }
        .auth-header h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px;
        }
        .auth-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .auth-form { width: 100%; }
        .auth-form .field {
            margin-bottom: 18px;
        }
        .auth-form .field:last-of-type {
            margin-bottom: 0;
        }
        .auth-form .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        .auth-form .field .input-wrap {
            position: relative;
        }
        .auth-form .field .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }
        .auth-form .field .input-wrap input {
            width: 100%;
            height: 46px;
            padding: 0 14px 0 42px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        .auth-form .field .input-wrap input:focus {
            border-color: var(--primary);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(13,110,253,0.1);
        }
        .auth-form .field .input-wrap input:focus ~ i {
            color: var(--primary);
        }
        .auth-form .field .input-wrap input::placeholder {
            color: var(--text-muted);
            font-size: 13px;
        }

        .auth-form .row-link {
            display: flex;
            justify-content: flex-end;
            margin-top: 2px;
            margin-bottom: 16px;
        }
        .auth-form .row-link a {
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
        }
        .auth-form .row-link a:hover {
            color: var(--primary);
        }

        .auth-form .btn-submit {
            width: 100%;
            height: 46px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-form .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(13,110,253,0.4);
        }
        .auth-form .btn-submit:active {
            transform: translateY(0);
        }

        .auth-footer {
            text-align: center;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid var(--border-color);
        }
        .auth-footer span {
            font-size: 13px;
            color: var(--text-muted);
        }
        .auth-footer a {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }

        .theme-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            transition: all 0.2s;
            z-index: 100;
        }
        .theme-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.1);
        }

        .alert-custom {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            border: none;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-custom.danger {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }
        .alert-custom.success {
            background: rgba(25,135,84,0.1);
            color: #198754;
        }

        @media (max-width: 480px) {
            .auth-card { padding: 24px 20px; }
            .auth-header .logo-wrap { width: 56px; height: 56px; font-size: 24px; }
            .auth-header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="auth-wrapper">
        <div class="auth-card">

            <!-- HEADER -->
            <div class="auth-header">
                <div class="logo-wrap"><i class="fas fa-hospital-alt"></i></div>
                <h1>Welcome Back</h1>
                <p>Sign in to your hospital account</p>
            </div>

            <!-- ALERTS -->
            <?php if ($error): ?>
                <div class="alert-custom danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?= display_flash('success') ?>

            <!-- FORM -->
            <form class="auth-form" method="POST" action="">
                <?= csrf_field() ?>

                <div class="field">
                    <label>Email or Username</label>
                    <div class="input-wrap">
                        <input type="text" name="email" placeholder="Enter email or username" value="<?= htmlspecialchars($email) ?>" required autofocus>
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-lock"></i>
                    </div>
                </div>

                <div class="row-link">
                    <a href="forgot-password.php"><i class="fas fa-key me-1"></i>Forgot Password?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <!-- FOOTER -->
            <?php if (!Auth::hasUsers()): ?>
                <div class="auth-footer">
                    <span>No account yet?</span>
                    <a href="register.php">Create Super Admin Account</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        const t = localStorage.getItem('hms_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
        const el = document.getElementById('themeIcon');
        if (el) el.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    })();
    function toggleTheme() {
        const h = document.documentElement;
        const cur = h.getAttribute('data-theme');
        const nxt = cur === 'dark' ? 'light' : 'dark';
        h.setAttribute('data-theme', nxt);
        localStorage.setItem('hms_theme', nxt);
        const el = document.getElementById('themeIcon');
        if (el) el.className = nxt === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    </script>
</body>
</html>
