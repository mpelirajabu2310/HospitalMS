<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAuth();

$mode = 'list';

// Single user activities (for user detail modal)
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($userId > 0 && !isset($_GET['page'])) {
    $mode = 'user_detail';
}

if ($mode === 'user_detail') {
    $user = Database::fetch("SELECT u.*, r.display_name as role_display FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);
    if (!$user) {
        echo '<div class="alert alert-danger m-3">User not found.</div>';
        exit;
    }
    $activities = Database::fetchAll(
        "SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
        [$userId]
    );
    ?>
    <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded" style="background:var(--bg-body)">
        <div class="user-avatar">
            <?php if ($user['avatar']): ?>
                <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover">
            <?php else: ?>
                <div class="avatar-placeholder" style="width:48px;height:48px;font-size:16px"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <strong style="font-size:15px"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
            <small class="d-block text-muted"><?= htmlspecialchars($user['role_display']) ?> &bull; <?= count($activities) ?> activity records</small>
        </div>
    </div>
    <?php if (empty($activities)): ?>
        <div class="text-center py-4 text-muted"><i class="fas fa-inbox me-1"></i> No activities found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>Action</th><th>Module</th><th>Description</th><th>Date & Time</th><th>IP Address</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $a): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($a['action']) ?></span></td>
                        <td><?= htmlspecialchars($a['module'] ?: '-') ?></td>
                        <td><small><?= htmlspecialchars($a['description'] ?: '-') ?></small></td>
                        <td><small class="text-muted"><?= formatDateTime($a['created_at']) ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($a['ip_address'] ?: '-') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
    exit;
}

// Full activity log with pagination and filters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = sanitize($_GET['search'] ?? '');
$module = sanitize($_GET['module'] ?? '');
$filterUserId = intval($_GET['user_id'] ?? 0);

$where = [];
$params = [];

if ($search) {
    $where[] = "(ual.description LIKE ? OR ual.action LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($module) {
    $where[] = "ual.module = ?";
    $params[] = $module;
}
if ($filterUserId > 0) {
    $where[] = "ual.user_id = ?";
    $params[] = $filterUserId;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = Database::fetch("SELECT COUNT(*) as c FROM user_activity_logs ual $whereClause", $params)['c'];

$activities = Database::fetchAll(
    "SELECT ual.*, u.first_name, u.last_name, r.display_name as role_display
     FROM user_activity_logs ual
     JOIN users u ON ual.user_id = u.id
     JOIN roles r ON u.role_id = r.id
     $whereClause
     ORDER BY ual.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$totalPages = ceil($total / $limit);
$paginator = [
    'page' => $page,
    'total_pages' => $totalPages,
    'has_prev' => $page > 1,
    'has_next' => $page < $totalPages,
    'prev_page' => $page - 1,
    'next_page' => $page + 1,
    'total' => $total
];

// Build user dropdown options
$allUsers = Database::fetchAll(
    "SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u 
     JOIN user_activity_logs ual ON u.id = ual.user_id ORDER BY u.first_name"
);
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <small class="text-muted">Showing <?= count($activities) ?> of <?= $total ?> records</small>
    <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?: 1 ?></small>
</div>
<?php if (empty($activities)): ?>
    <div class="text-center py-4 text-muted"><i class="fas fa-inbox me-1"></i> No activities found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Date & Time</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $a): ?>
                <tr>
                    <td>
                        <strong style="font-size:12px"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></strong>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($a['role_display'] ?? '') ?></small></td>
                    <td><span class="badge bg-secondary" style="font-size:10px"><?= htmlspecialchars($a['action']) ?></span></td>
                    <td><small><?= htmlspecialchars($a['module'] ?: '-') ?></small></td>
                    <td><small><?= htmlspecialchars($a['description'] ?: '-') ?></small></td>
                    <td><small class="text-muted" style="white-space:nowrap"><?= formatDateTime($a['created_at']) ?></small></td>
                    <td><small class="text-muted" style="font-size:10px"><?= htmlspecialchars($a['ip_address'] ?: '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center gap-1 mt-2">
    <?php if ($paginator['has_prev']): ?>
        <button class="btn btn-sm btn-outline-primary" onclick="loadActivityLogs(<?= $paginator['prev_page'] ?>)"><i class="fas fa-chevron-left"></i></button>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <button class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-primary' ?>" onclick="loadActivityLogs(<?= $i ?>)"><?= $i ?></button>
    <?php endfor; ?>
    <?php if ($paginator['has_next']): ?>
        <button class="btn btn-sm btn-outline-primary" onclick="loadActivityLogs(<?= $paginator['next_page'] ?>)"><i class="fas fa-chevron-right"></i></button>
    <?php endif; ?>
</div>
<?php endif; ?>
