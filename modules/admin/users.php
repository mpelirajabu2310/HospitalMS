<?php
define('PAGE_TITLE', 'Manage Users');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
if (!Auth::isSuperAdmin()) { redirect('/index.php'); }

$userId = Auth::id();
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$filterRole = intval($_GET['role_id'] ?? 0);
$filterDept = intval($_GET['department_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $roleId = intval($_POST['role_id'] ?? 0);
        $deptId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName) || !$roleId) {
            set_flash('error', 'Please fill in all required fields.', 'error');
        } elseif (!validateEmail($email)) {
            set_flash('error', 'Please enter a valid email address.', 'error');
        } elseif (Database::fetch("SELECT id FROM users WHERE username = ?", [$username])) {
            set_flash('error', 'Username already exists.', 'error');
        } elseif (Database::fetch("SELECT id FROM users WHERE email = ?", [$email])) {
            set_flash('error', 'Email already exists.', 'error');
        } else {
            $newId = Database::insert(
                "INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$username, $email, hashPassword($password), $firstName, $lastName, $phone, $roleId, $deptId, $status]
            );
            logActivity($userId, 'create_user', 'admin', "Created user: $firstName $lastName");
            auditLog($userId, 'create', 'users', $newId, null, ['username' => $username, 'email' => $email, 'role_id' => $roleId]);
            set_flash('success', 'User created successfully.', 'success');
        }
    } elseif ($action === 'edit') {
        $editId = intval($_POST['id'] ?? 0);
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $roleId = intval($_POST['role_id'] ?? 0);
        $deptId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $status = sanitize($_POST['status'] ?? 'active');

        $old = Database::fetch("SELECT * FROM users WHERE id = ?", [$editId]);
        if ($old && !empty($firstName) && !empty($lastName) && $roleId) {
            Database::query(
                "UPDATE users SET first_name = ?, last_name = ?, phone = ?, role_id = ?, department_id = ?, status = ? WHERE id = ?",
                [$firstName, $lastName, $phone, $roleId, $deptId, $status, $editId]
            );
            logActivity($userId, 'update_user', 'admin', "Updated user: $firstName $lastName");
            auditLog($userId, 'update', 'users', $editId, $old, ['first_name' => $firstName, 'last_name' => $lastName, 'role_id' => $roleId, 'department_id' => $deptId, 'status' => $status]);
            set_flash('success', 'User updated successfully.', 'success');
        } else {
            set_flash('error', 'User not found or invalid data.', 'error');
        }
    } elseif ($action === 'toggle_status') {
        $uid = intval($_POST['user_id'] ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $allowed = ['active', 'inactive', 'suspended'];
        if (in_array($newStatus, $allowed)) {
            Database::query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $uid]);
            logActivity($userId, 'toggle_user_status', 'admin', "Changed user #$uid status to $newStatus");
            set_flash('success', "User status changed to $newStatus.", 'success');
        }
    } elseif ($action === 'reset_password') {
        $uid = intval($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            set_flash('error', 'Password must be at least 6 characters.', 'error');
        } else {
            Database::query("UPDATE users SET password = ? WHERE id = ?", [hashPassword($newPass), $uid]);
            logActivity($userId, 'reset_user_password', 'admin', "Reset password for user #$uid");
            set_flash('success', 'Password reset successfully.', 'success');
        }
    }

    redirect('/modules/admin/users.php?' . http_build_query(['page' => $page, 'search' => $search, 'role_id' => $filterRole, 'department_id' => $filterDept]));
}

$where = [];
$params = [];
if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}
if ($filterRole) {
    $where[] = "u.role_id = ?";
    $params[] = $filterRole;
}
if ($filterDept) {
    $where[] = "u.department_id = ?";
    $params[] = $filterDept;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = Database::fetch("SELECT COUNT(*) as count FROM users u $whereClause", $params)['count'];
$pagination = paginate($total, $page);

$users = Database::fetchAll(
    "SELECT u.*, r.display_name as role_name, d.name as department_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     LEFT JOIN departments d ON u.department_id = d.id
     $whereClause
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['limit'], $pagination['offset']])
);

$roles = Database::fetchAll("SELECT * FROM roles ORDER BY display_name");
$departments = Database::fetchAll("SELECT * FROM departments WHERE status = 'active' ORDER BY name");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-users-cog me-2 text-primary"></i>Manage Users</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus me-1"></i> Add User
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium small">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, username, email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Role</label>
                <select name="role_id" class="form-select">
                    <option value="0">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $filterRole === $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Department</label>
                <select name="department_id" class="form-select">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filterDept === $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="fw-medium">#<?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($u['role_name']) ?></span></td>
                                <td><?= htmlspecialchars($u['department_name'] ?? '-') ?></td>
                                <td><?= getStatusBadge($u['status']) ?></td>
                                <td class="small text-muted"><?= $u['last_login_at'] ? formatDateTime($u['last_login_at']) : 'Never' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="<?= $u['id'] ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-first_name="<?= htmlspecialchars($u['first_name']) ?>"
                                        data-last_name="<?= htmlspecialchars($u['last_name']) ?>"
                                        data-phone="<?= htmlspecialchars($u['phone'] ?? '') ?>"
                                        data-role_id="<?= $u['role_id'] ?>"
                                        data-department_id="<?= $u['department_id'] ?? '' ?>"
                                        data-status="<?= $u['status'] ?>"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-<?= $u['status'] === 'active' ? 'warning' : 'success' ?> dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-toggle-<?= $u['status'] === 'active' ? 'on' : 'off' ?>"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); toggleStatus(<?= $u['id'] ?>, 'active')"><i class="fas fa-check-circle text-success me-2"></i>Active</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); toggleStatus(<?= $u['id'] ?>, 'inactive')"><i class="fas fa-pause-circle text-secondary me-2"></i>Inactive</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); toggleStatus(<?= $u['id'] ?>, 'suspended')"><i class="fas fa-ban text-danger me-2"></i>Suspended</a></li>
                                        </ul>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary ms-1" title="Reset Password" onclick="resetPassword(<?= $u['id'] ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $pagination['prev_page'] ?>&search=<?= urlencode($search) ?>&role_id=<?= $filterRole ?>&department_id=<?= $filterDept ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role_id=<?= $filterRole ?>&department_id=<?= $filterDept ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $pagination['next_page'] ?>&search=<?= urlencode($search) ?>&role_id=<?= $filterRole ?>&department_id=<?= $filterDept ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i>Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Username</label>
                            <input type="text" id="edit_username" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" id="edit_email" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="edit_role_id" class="form-select" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Department</label>
                            <select name="department_id" id="edit_department_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="toggleStatusForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggle_user_id">
    <input type="hidden" name="new_status" id="toggle_new_status">
</form>

<form method="POST" id="resetPasswordForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" id="reset_user_id">
    <input type="hidden" name="new_password" id="reset_new_password">
</form>

<script>
document.getElementById('editUserModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_username').value = btn.dataset.username;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_first_name').value = btn.dataset.first_name;
    document.getElementById('edit_last_name').value = btn.dataset.last_name;
    document.getElementById('edit_phone').value = btn.dataset.phone || '';
    document.getElementById('edit_role_id').value = btn.dataset.role_id;
    document.getElementById('edit_department_id').value = btn.dataset.department_id || '';
    document.getElementById('edit_status').value = btn.dataset.status;
});

function toggleStatus(userId, status) {
    if (confirm('Change user status to ' + status + '?')) {
        document.getElementById('toggle_user_id').value = userId;
        document.getElementById('toggle_new_status').value = status;
        document.getElementById('toggleStatusForm').submit();
    }
}

function resetPassword(userId) {
    const password = prompt('Enter new password (min 6 characters):');
    if (password && password.length >= 6) {
        document.getElementById('reset_user_id').value = userId;
        document.getElementById('reset_new_password').value = password;
        document.getElementById('resetPasswordForm').submit();
    } else if (password) {
        alert('Password must be at least 6 characters.');
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
