<?php
define("PAGE_TITLE", "My Profile");
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'first_name' => sanitize($_POST['first_name'] ?? $user['first_name']),
            'last_name' => sanitize($_POST['last_name'] ?? $user['last_name']),
            'phone' => sanitize($_POST['phone'] ?? $user['phone']),
        ];

        if (empty($data['first_name']) || empty($data['last_name'])) {
            $error = 'First name and last name are required.';
        } elseif (!empty($data['phone']) && !validatePhone($data['phone'])) {
            $error = 'Please enter a valid phone number.';
        } else {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $avatar = uploadFile($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif']);
                if ($avatar) {
                    $data['avatar'] = $avatar;
                    if ($user['avatar'] && file_exists(UPLOAD_PATH . '/' . $user['avatar'])) {
                        unlink(UPLOAD_PATH . '/' . $user['avatar']);
                    }
                } else {
                    $error = 'Failed to upload avatar. Allowed types: jpg, jpeg, png, gif.';
                }
            }

            if (!$error && Auth::updateProfile($userId, $data)) {
                $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
                $success = 'Profile updated successfully.';
            } elseif (!$error) {
                $error = 'No changes were made.';
            }
        }
    }
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>My Profile</h4>
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

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body p-4">
                <div class="mb-3">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle" width="120" height="120" style="object-fit:cover" alt="Avatar">
                    <?php else: ?>
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary text-white" style="width:120px;height:120px;font-size:48px;">
                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <p class="text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                    <?= ucfirst($user['status']) ?>
                </span>
            </div>
            <div class="card-footer border-0 pt-0 text-start px-4 pb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Role</span>
                    <span class="small fw-medium"><?= htmlspecialchars(Auth::user()['role_display'] ?? getRoleName($user['role_id'])) ?></span>
                </div>
                <?php if ($user['department_id']): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Department</span>
                        <span class="small fw-medium"><?= htmlspecialchars(getDepartmentName($user['department_id'])) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Phone</span>
                    <span class="small fw-medium"><?= htmlspecialchars($user['phone'] ?: '-') ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Member Since</span>
                    <span class="small fw-medium"><?= formatDate($user['created_at']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Profile</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Profile Avatar</label>
                            <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif">
                            <small class="text-muted">Allowed: jpg, jpeg, png, gif</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
