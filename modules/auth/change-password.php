<?php
define("PAGE_TITLE", "Change Password");
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (Auth::updatePassword($userId, $current, $new)) {
            $success = 'Password changed successfully.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-key me-2 text-primary"></i>Change Password</h4>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
