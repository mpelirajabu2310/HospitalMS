<?php
define('PAGE_TITLE', 'Manage Roles');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
if (!Auth::isSuperAdmin()) { redirect('/index.php'); }

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $displayName = sanitize($_POST['display_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name) || empty($displayName)) {
            set_flash('error', 'Role name and display name are required.', 'error');
        } elseif (Database::fetch("SELECT id FROM roles WHERE name = ?", [$name])) {
            set_flash('error', 'Role name already exists.', 'error');
        } else {
            Database::insert("INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, 0)", [$name, $displayName, $description]);
            logActivity($userId, 'create_role', 'admin', "Created role: $displayName");
            set_flash('success', 'Role created successfully.', 'success');
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $displayName = sanitize($_POST['display_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $role = Database::fetch("SELECT * FROM roles WHERE id = ?", [$id]);

        if ($role && !empty($displayName)) {
            if ($role['is_system']) {
                Database::query("UPDATE roles SET display_name = ?, description = ? WHERE id = ?", [$displayName, $description, $id]);
            } else {
                $name = sanitize($_POST['name'] ?? $role['name']);
                Database::query("UPDATE roles SET name = ?, display_name = ?, description = ? WHERE id = ?", [$name, $displayName, $description, $id]);
            }
            logActivity($userId, 'update_role', 'admin', "Updated role: $displayName");
            set_flash('success', 'Role updated successfully.', 'success');
        } else {
            set_flash('error', 'Role not found.', 'error');
        }
    } elseif ($action === 'save_permissions') {
        $roleId = intval($_POST['role_id'] ?? 0);
        $perms = $_POST['permissions'] ?? [];

        $role = Database::fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            set_flash('error', 'Role not found.', 'error');
        } else {
            Database::query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($perms as $permId) {
                Database::query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleId, intval($permId)]);
            }
            logActivity($userId, 'update_role_permissions', 'admin', "Updated permissions for role: {$role['display_name']}");
            set_flash('success', 'Permissions saved successfully.', 'success');
        }
    }

    redirect('/modules/admin/roles.php');
}

$roles = Database::fetchAll(
    "SELECT r.*, (SELECT COUNT(*) FROM users WHERE role_id = r.id) as users_count
     FROM roles r ORDER BY r.display_name"
);
$permissions = Database::fetchAll("SELECT * FROM permissions ORDER BY module, display_name");
$permissionsByModule = [];
foreach ($permissions as $p) {
    $permissionsByModule[$p['module']][] = $p;
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-user-tag me-2 text-primary"></i>Manage Roles</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
        <i class="fas fa-plus me-1"></i> Add Role
    </button>
</div>

<div class="row g-4">
    <?php foreach ($roles as $r): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><?= htmlspecialchars($r['display_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($r['name']) ?></small>
                    </div>
                    <div>
                        <?php if ($r['is_system']): ?>
                            <span class="badge bg-secondary me-2">System</span>
                        <?php endif; ?>
                        <span class="badge bg-info"><?= $r['users_count'] ?> users</span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?= htmlspecialchars($r['description'] ?? 'No description') ?></p>

                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRoleModal"
                            data-id="<?= $r['id'] ?>"
                            data-name="<?= htmlspecialchars($r['name']) ?>"
                            data-display_name="<?= htmlspecialchars($r['display_name']) ?>"
                            data-description="<?= htmlspecialchars($r['description'] ?? '') ?>"
                            data-is_system="<?= $r['is_system'] ?>">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#permsModal-<?= $r['id'] ?>">
                            <i class="fas fa-shield-alt me-1"></i> Permissions
                        </button>
                    </div>

                    <div class="small text-muted">
                        <strong>Assigned permissions:</strong>
                        <?php
                        $rolePerms = Database::fetchAll(
                            "SELECT p.display_name FROM permissions p
                             JOIN role_permissions rp ON p.id = rp.permission_id
                             WHERE rp.role_id = ? ORDER BY p.module",
                            [$r['id']]
                        );
                        $names = array_column($rolePerms, 'display_name');
                        echo $names ? implode(', ', array_map('htmlspecialchars', $names)) : 'None';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="permsModal-<?= $r['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_permissions">
                        <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-shield-alt me-2 text-success"></i>Permissions: <?= htmlspecialchars($r['display_name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php
                            $assigned = Database::fetchAll(
                                "SELECT permission_id FROM role_permissions WHERE role_id = ?",
                                [$r['id']]
                            );
                            $assignedIds = array_column($assigned, 'permission_id');
                            ?>
                            <?php foreach ($permissionsByModule as $module => $perms): ?>
                                <div class="mb-3">
                                    <h6 class="text-primary text-capitalize border-bottom pb-2"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $module))) ?></h6>
                                    <div class="row g-2">
                                        <?php foreach ($perms as $p): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                                                        id="perm_<?= $r['id'] ?>_<?= $p['id'] ?>"
                                                        <?= in_array($p['id'], $assignedIds) ? 'checked' : '' ?>
                                                        <?= $r['is_system'] && $r['name'] === 'super_admin' ? 'disabled' : '' ?>>
                                                    <label class="form-check-label small" for="perm_<?= $r['id'] ?>_<?= $p['id'] ?>">
                                                        <?= htmlspecialchars($p['display_name']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <?php if ($r['is_system'] && $r['name'] === 'super_admin'): ?>
                                <span class="text-muted small me-2">Super Admin has all permissions</span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <?php if (!($r['is_system'] && $r['name'] === 'super_admin')): ?>
                                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Permissions</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2 text-primary"></i>Add Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. accountant" required>
                        <small class="text-muted">System identifier (lowercase, no spaces)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Display Name <span class="text-danger">*</span></label>
                        <input type="text" name="display_name" class="form-control" placeholder="e.g. Accountant" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this role"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_role_id">
                <input type="hidden" name="is_system" id="edit_role_is_system">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="edit_name_group">
                        <label class="form-label fw-medium">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_role_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Display Name <span class="text-danger">*</span></label>
                        <input type="text" name="display_name" id="edit_role_display_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" id="edit_role_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editRoleModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_role_id').value = btn.dataset.id;
    document.getElementById('edit_role_name').value = btn.dataset.name;
    document.getElementById('edit_role_display_name').value = btn.dataset.display_name;
    document.getElementById('edit_role_description').value = btn.dataset.description;
    document.getElementById('edit_role_is_system').value = btn.dataset.is_system;

    const nameGroup = document.getElementById('edit_name_group');
    if (btn.dataset.is_system === '1') {
        nameGroup.style.display = 'none';
    } else {
        nameGroup.style.display = 'block';
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
