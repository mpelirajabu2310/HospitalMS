<?php
define('PAGE_TITLE', 'Manage Departments');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
if (!Auth::isSuperAdmin()) { redirect('/index.php'); }

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $headUserId = !empty($_POST['head_user_id']) ? intval($_POST['head_user_id']) : null;
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($name) || empty($code)) {
            set_flash('error', 'Name and code are required.', 'error');
        } elseif (Database::fetch("SELECT id FROM departments WHERE code = ?", [$code])) {
            set_flash('error', 'Department code already exists.', 'error');
        } else {
            Database::insert(
                "INSERT INTO departments (name, code, description, head_user_id, status) VALUES (?, ?, ?, ?, ?)",
                [$name, $code, $description, $headUserId, $status]
            );
            logActivity($userId, 'create_department', 'admin', "Created department: $name");
            set_flash('success', 'Department created successfully.', 'success');
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $headUserId = !empty($_POST['head_user_id']) ? intval($_POST['head_user_id']) : null;
        $status = sanitize($_POST['status'] ?? 'active');

        $old = Database::fetch("SELECT * FROM departments WHERE id = ?", [$id]);
        if ($old && !empty($name) && !empty($code)) {
            Database::query(
                "UPDATE departments SET name = ?, code = ?, description = ?, head_user_id = ?, status = ? WHERE id = ?",
                [$name, $code, $description, $headUserId, $status, $id]
            );
            logActivity($userId, 'update_department', 'admin', "Updated department: $name");
            set_flash('success', 'Department updated successfully.', 'success');
        } else {
            set_flash('error', 'Department not found.', 'error');
        }
    }

    redirect('/modules/admin/departments.php');
}

$departments = Database::fetchAll(
    "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as head_name
     FROM departments d
     LEFT JOIN users u ON d.head_user_id = u.id
     ORDER BY d.name"
);
$users = Database::fetchAll("SELECT id, first_name, last_name, email FROM users WHERE status = 'active' ORDER BY first_name, last_name");

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>Manage Departments</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
        <i class="fas fa-plus me-1"></i> Add Department
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Head</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No departments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($d['name']) ?></td>
                                <td><code><?= htmlspecialchars($d['code']) ?></code></td>
                                <td class="text-muted small"><?= htmlspecialchars($d['description'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['head_name'] ?? '-') ?></td>
                                <td><?= getStatusBadge($d['status']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDeptModal"
                                        data-id="<?= $d['id'] ?>"
                                        data-name="<?= htmlspecialchars($d['name']) ?>"
                                        data-code="<?= htmlspecialchars($d['code']) ?>"
                                        data-description="<?= htmlspecialchars($d['description'] ?? '') ?>"
                                        data-head_user_id="<?= $d['head_user_id'] ?? '' ?>"
                                        data-status="<?= $d['status'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2 text-primary"></i>Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Cardiology" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. CARD" required>
                        <small class="text-muted">Short unique identifier</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Department Head</label>
                        <select name="head_user_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_dept_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_dept_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="edit_dept_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" id="edit_dept_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Department Head</label>
                        <select name="head_user_id" id="edit_dept_head_user_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select name="status" id="edit_dept_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editDeptModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_dept_id').value = btn.dataset.id;
    document.getElementById('edit_dept_name').value = btn.dataset.name;
    document.getElementById('edit_dept_code').value = btn.dataset.code;
    document.getElementById('edit_dept_description').value = btn.dataset.description || '';
    document.getElementById('edit_dept_head_user_id').value = btn.dataset.head_user_id || '';
    document.getElementById('edit_dept_status').value = btn.dataset.status;
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
