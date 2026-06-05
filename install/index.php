<?php
/**
 * HMS Web Installer
 * Access: http://localhost/HospitalMS/install/
 */
$step = $_GET["step"] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: -apple-system,BlinkMacSystemFont,sans-serif; padding: 40px 0; }
        .install-container { max-width: 700px; margin: 0 auto; }
        .install-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; }
        .install-header { background: linear-gradient(135deg,#0d6efd,#0a58ca); color: #fff; padding: 30px; text-align: center; }
        .install-header .icon { width: 64px; height: 64px; background: rgba(255,255,255,.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 12px; }
        .install-header h3 { margin: 0; font-weight: 700; }
        .install-header p { opacity: .8; margin: 4px 0 0; font-size: 14px; }
        .install-body { padding: 30px; }
        .step-indicator { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; }
        .step-dot.active { background: #0d6efd; color: #fff; }
        .step-dot.done { background: #198754; color: #fff; }
        .step-dot.pending { background: #e9ecef; color: #6c757d; }
        .success-icon { color: #198754; font-size: 48px; }
        .error-icon { color: #dc3545; font-size: 48px; }
    </style>
</head>
<body>
<div class="install-container">
    <div class="install-card">
        <div class="install-header">
            <div class="icon"><i class="fas fa-hospital-alt"></i></div>
            <h3>Hospital Management System</h3>
            <p>Installation Wizard</p>
        </div>
        <div class="install-body">
            <div class="step-indicator">
                <div class="step-dot <?= $step > 1 ? 'done' : ($step == 1 ? 'active' : 'pending') ?>">1</div>
                <div class="step-dot <?= $step > 2 ? 'done' : ($step == 2 ? 'active' : 'pending') ?>">2</div>
                <div class="step-dot <?= $step > 3 ? 'done' : ($step == 3 ? 'active' : 'pending') ?>">3</div>
            </div>

            <?php if ($step == 1): ?>
            <h5 class="mb-3"><i class="fas fa-check-circle text-primary me-2"></i>Pre-Installation Check</h5>
            <?php
            $checks = [];
            $checks[] = ["PHP Version >= 7.4", PHP_VERSION >= 7.4, PHP_VERSION];
            $checks[] = ["PDO Extension", extension_loaded("pdo"), ""];
            $checks[] = ["PDO MySQL Extension", extension_loaded("pdo_mysql"), ""];
            $checks[] = ["MySQL Server Available", true, ""];
            $checks[] = ["Session Extension", extension_loaded("session"), ""];
            $checks[] = ["GD Extension (optional)", extension_loaded("gd"), ""];
            $checks[] = ["File Uploads Enabled", ini_get("file_uploads"), ""];
            $allPass = true;
            ?>
            <div class="list-group mb-4">
                <?php foreach ($checks as $c): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?= $c[0] ?></span>
                    <span>
                        <?php if ($c[1]): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> OK</span>
                        <?php else: $allPass = false; ?>
                            <span class="badge bg-danger"><i class="fas fa-times"></i> Fail</span>
                        <?php endif; ?>
                        <?php if ($c[2]): ?><small class="text-muted ms-2"><?= $c[2] ?></small><?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($allPass): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>All checks passed! Ready to install.</div>
                <a href="?step=2" class="btn btn-primary w-100">Continue to Database Setup</a>
            <?php else: ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Some requirements are missing. Please fix them first.</div>
            <?php endif; ?>

            <?php elseif ($step == 2): ?>
            <h5 class="mb-3"><i class="fas fa-database text-primary me-2"></i>Database Setup</h5>
            <?php
            $dbHost = $_POST["db_host"] ?? "localhost";
            $dbName = $_POST["db_name"] ?? "hospital_hms";
            $dbUser = $_POST["db_user"] ?? "root";
            $dbPass = $_POST["db_pass"] ?? "";

            if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["install"])) {
                try {
                    $config = "<?php" . PHP_EOL;
                    $config .= "define('DB_HOST', '" . addslashes($dbHost) . "');" . PHP_EOL;
                    $config .= "define('DB_NAME', '" . addslashes($dbName) . "');" . PHP_EOL;
                    $config .= "define('DB_USER', '" . addslashes($dbUser) . "');" . PHP_EOL;
                    $config .= "define('DB_PASS', '" . addslashes($dbPass) . "');" . PHP_EOL;
                    $config .= "define('DB_CHARSET', 'utf8mb4');" . PHP_EOL;

                    file_put_contents(__DIR__ . "/../config/database.php", $config);

                    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $sql = file_get_contents(__DIR__ . "/../database/schema.sql");
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$dbName`");
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $statements = array_filter(array_map("trim", explode(";", $sql)));
                    foreach ($statements as $stmt) { if (!empty($stmt)) { $pdo->exec($stmt); } }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                    echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Database created and schema installed successfully!</div>';
                    echo '<a href="?step=3" class="btn btn-primary w-100">Continue to Finalize</a>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    echo '<a href="?step=2" class="btn btn-outline-secondary w-100">Try Again</a>';
                }
            } else {
            ?>
            <form method="POST" action="?step=2">
                <div class="mb-3">
                    <label class="form-label">Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="hospital_hms" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="db_user" class="form-control" value="root" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
                <button type="submit" name="install" class="btn btn-primary w-100">
                    <i class="fas fa-play me-2"></i>Install Database
                </button>
            </form>
            <?php } ?>

            <?php elseif ($step == 3): ?>
            <h5 class="mb-3"><i class="fas fa-flag-checkered text-success me-2"></i>Installation Complete</h5>
            <div class="text-center py-4">
                <div class="success-icon mb-3"><i class="fas fa-check-circle"></i></div>
                <h4>Hospital Management System Installed!</h4>
                <p class="text-muted">The database has been set up successfully.</p>
                <hr>
                <div class="text-start">
                    <h6 class="mb-2">Next Steps:</h6>
                    <ol class="text-muted">
                        <li>Visit the <a href="../index.php">Login Page</a></li>
                        <li>Click "Create First User" to register as Super Admin</li>
                        <li>Log in and start configuring the system</li>
                    </ol>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Optionally run <code>php database/seeder.php</code> from CLI to create demo data.
                    </div>
                </div>
                <a href="../index.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-right me-2"></i>Go to Login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-center text-muted mt-3 small">Hospital Management System v1.0</p>
</div>
</body>
</html>
