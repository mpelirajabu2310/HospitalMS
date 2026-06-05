<?php
define('PAGE_TITLE', 'Lab Test Management');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category' || $action === 'edit_category') {
        $catId = intval($_POST['category_id'] ?? 0);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description'] ?? '');

        if ($action === 'add_category') {
            Database::insert(
                "INSERT INTO lab_test_categories (name, description) VALUES (?, ?)",
                [$name, $description]
            );
            logActivity($userId, 'category_added', 'laboratory', "Added lab test category: $name");
            set_flash('success', 'Category added successfully.');
        } else {
            Database::query(
                "UPDATE lab_test_categories SET name = ?, description = ? WHERE id = ?",
                [$name, $description, $catId]
            );
            logActivity($userId, 'category_updated', 'laboratory', "Updated lab test category: $name");
            set_flash('success', 'Category updated successfully.');
        }
    } elseif ($action === 'delete_category') {
        $catId = intval($_POST['category_id'] ?? 0);
        Database::query("DELETE FROM lab_test_categories WHERE id = ?", [$catId]);
        logActivity($userId, 'category_deleted', 'laboratory', "Deleted lab test category #$catId");
        set_flash('success', 'Category deleted successfully.');
    } elseif ($action === 'add_test' || $action === 'edit_test') {
        $testId = intval($_POST['test_id'] ?? 0);
        $name = sanitize($_POST['name']);
        $categoryId = intval($_POST['category_id']);
        $code = sanitize($_POST['code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $specimenType = sanitize($_POST['specimen_type'] ?? '');
        $referenceRange = sanitize($_POST['reference_range'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $turnaroundHours = intval($_POST['turnaround_hours'] ?? 24);
        $status = sanitize($_POST['status'] ?? 'active');

        if ($action === 'add_test') {
            Database::insert(
                "INSERT INTO lab_tests (name, category_id, code, description, specimen_type, reference_range, price, turnaround_hours, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $categoryId, $code, $description, $specimenType, $referenceRange, $price, $turnaroundHours, $status]
            );
            logActivity($userId, 'test_added', 'laboratory', "Added lab test: $name");
            set_flash('success', 'Lab test added successfully.');
        } else {
            Database::query(
                "UPDATE lab_tests SET name = ?, category_id = ?, code = ?, description = ?, specimen_type = ?, reference_range = ?, price = ?, turnaround_hours = ?, status = ? WHERE id = ?",
                [$name, $categoryId, $code, $description, $specimenType, $referenceRange, $price, $turnaroundHours, $status, $testId]
            );
            logActivity($userId, 'test_updated', 'laboratory', "Updated lab test: $name");
            set_flash('success', 'Lab test updated successfully.');
        }
    } elseif ($action === 'delete_test') {
        $testId = intval($_POST['test_id'] ?? 0);
        Database::query("DELETE FROM lab_tests WHERE id = ?", [$testId]);
        logActivity($userId, 'test_deleted', 'laboratory', "Deleted lab test #$testId");
        set_flash('success', 'Lab test deleted successfully.');
    }
    redirect('modules/laboratory/categories.php');
}

$categories = Database::fetchAll(
    "SELECT c.*, (SELECT COUNT(*) FROM lab_tests WHERE category_id = c.id) as tests_count
     FROM lab_test_categories c ORDER BY c.name ASC"
);

$selectedCategoryId = intval($_GET['category_id'] ?? ($categories[0]['id'] ?? 0));
$tests = [];
if ($selectedCategoryId) {
    $tests = Database::fetchAll(
        "SELECT * FROM lab_tests WHERE category_id = ? ORDER BY name ASC",
        [$selectedCategoryId]
    );
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-flask me-2 text-primary"></i>Lab Test Management</h4>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-folder me-2 text-warning"></i>Test Categories</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus me-1"></i>Add Category
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Tests</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No categories found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $c): ?>
                                    <tr class="<?= $c['id'] === $selectedCategoryId ? 'table-primary' : '' ?>">
                                        <td>
                                            <a href="?category_id=<?= $c['id'] ?>" class="text-decoration-none fw-medium"><?= htmlspecialchars($c['name']) ?></a>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars(truncate($c['description'] ?? '', 50)) ?></td>
                                        <td><span class="badge bg-secondary"><?= $c['tests_count'] ?></span></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                                data-bs-toggle="modal" data-bs-target="#categoryModal"
                                                data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-description="<?= htmlspecialchars($c['description'] ?? '') ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-vial me-2 text-info"></i>Lab Tests
                    <?php if ($selectedCategoryId): ?>
                        <small class="text-muted">- <?= htmlspecialchars($categories[array_search($selectedCategoryId, array_column($categories, 'id'))]['name'] ?? '') ?></small>
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#testModal" data-category-id="<?= $selectedCategoryId ?>">
                    <i class="fas fa-plus me-1"></i>Add Test
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Specimen</th>
                                <th>Price</th>
                                <th>Turnaround</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tests)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Select a category or no tests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tests as $t): ?>
                                    <tr>
                                        <td class="fw-medium"><?= htmlspecialchars($t['name']) ?></td>
                                        <td><code><?= htmlspecialchars($t['code'] ?? '-') ?></code></td>
                                        <td><?= htmlspecialchars($t['specimen_type'] ?? '-') ?></td>
                                        <td><?= formatCurrency($t['price']) ?></td>
                                        <td><?= $t['turnaround_hours'] ?>h</td>
                                        <td><?= getStatusBadge($t['status']) ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit"
                                                data-bs-toggle="modal" data-bs-target="#testModal"
                                                data-id="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['name']) ?>"
                                                data-category-id="<?= $t['category_id'] ?>" data-code="<?= htmlspecialchars($t['code'] ?? '') ?>"
                                                data-description="<?= htmlspecialchars($t['description'] ?? '') ?>"
                                                data-specimen-type="<?= htmlspecialchars($t['specimen_type'] ?? '') ?>"
                                                data-reference-range="<?= htmlspecialchars($t['reference_range'] ?? '') ?>"
                                                data-price="<?= $t['price'] ?>" data-turnaround="<?= $t['turnaround_hours'] ?>"
                                                data-status="<?= $t['status'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this test?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_test">
                                                <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="catAction" value="add_category">
                <input type="hidden" name="category_id" id="catId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="catDescription" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="testAction" value="add_test">
                <input type="hidden" name="test_id" id="testId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="testModalTitle">Add Lab Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="testName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="testCategoryId" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="testCode" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Specimen Type</label>
                            <input type="text" name="specimen_type" id="testSpecimenType" class="form-control" placeholder="Blood, Urine, etc.">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" id="testPrice" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reference Range</label>
                            <textarea name="reference_range" id="testReferenceRange" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Turnaround (hours)</label>
                            <input type="number" name="turnaround_hours" id="testTurnaround" class="form-control" value="24">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="testStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="testDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var categoryModal = document.getElementById('categoryModal');
    categoryModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '0';
        var name = btn.getAttribute('data-name') || '';
        var desc = btn.getAttribute('data-description') || '';
        document.getElementById('catAction').value = id ? 'edit_category' : 'add_category';
        document.getElementById('catId').value = id || '0';
        document.getElementById('catName').value = name;
        document.getElementById('catDescription').value = desc;
        document.getElementById('catModalTitle').textContent = id ? 'Edit Category' : 'Add Category';
    });

    var testModal = document.getElementById('testModal');
    testModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id') || '';
        document.getElementById('testAction').value = id ? 'edit_test' : 'add_test';
        document.getElementById('testId').value = id || '0';
        document.getElementById('testName').value = btn.getAttribute('data-name') || '';
        var catId = btn.getAttribute('data-category-id') || '<?= $selectedCategoryId ?>';
        document.getElementById('testCategoryId').value = catId;
        document.getElementById('testCode').value = btn.getAttribute('data-code') || '';
        document.getElementById('testDescription').value = btn.getAttribute('data-description') || '';
        document.getElementById('testSpecimenType').value = btn.getAttribute('data-specimen-type') || '';
        document.getElementById('testReferenceRange').value = btn.getAttribute('data-reference-range') || '';
        document.getElementById('testPrice').value = btn.getAttribute('data-price') || '0';
        document.getElementById('testTurnaround').value = btn.getAttribute('data-turnaround') || '24';
        document.getElementById('testStatus').value = btn.getAttribute('data-status') || 'active';
        document.getElementById('testModalTitle').textContent = id ? 'Edit Lab Test' : 'Add Lab Test';
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
