<?php
define('PAGE_TITLE', 'Notifications');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notifId = intval($_POST['notification_id'] ?? 0);
        if ($notifId) {
            Database::query(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
                [$notifId, $userId]
            );
        }
        $redirect = sanitize($_POST['redirect'] ?? '');
        if ($redirect) {
            redirectTo($redirect);
        }
        redirect('modules/notifications/index.php');
    }

    if ($action === 'mark_all_read') {
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        set_flash('success', 'All notifications marked as read.');
        redirect('modules/notifications/index.php');
    }

    if ($action === 'delete') {
        $notifId = intval($_POST['notification_id'] ?? 0);
        Database::query("DELETE FROM notifications WHERE id = ? AND user_id = ?", [$notifId, $userId]);
        set_flash('success', 'Notification deleted.');
        redirect('modules/notifications/index.php');
    }

    if ($action === 'delete_all') {
        Database::query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
        set_flash('success', 'All notifications deleted.');
        redirect('modules/notifications/index.php');
    }
}

$filter = sanitize($_GET['filter'] ?? 'all');
$typeFilter = sanitize($_GET['type'] ?? '');

$where = ["user_id = ?"];
$params = [$userId];

if ($filter === 'unread') {
    $where[] = "is_read = 0";
}
if ($typeFilter) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}

$totalCount = Database::fetch(
    "SELECT COUNT(*) as c FROM notifications WHERE " . implode(' AND ', $where),
    $params
)['c'];

$pagination = paginate($totalCount, $page);
$params[] = $pagination['limit'];
$params[] = $pagination['offset'];

$notifications = Database::fetchAll(
    "SELECT * FROM notifications WHERE " . implode(' AND ', array_slice($where, 0, -2)) . " ORDER BY created_at DESC LIMIT ? OFFSET ?",
    $params
);

$unreadCount = getNotificationCount($userId);
$types = Database::fetchAll(
    "SELECT DISTINCT type FROM notifications WHERE user_id = ? ORDER BY type",
    [$userId]
);

$typeIcons = [
    'task_assigned' => 'fa-tasks',
    'task_updated' => 'fa-check-circle',
    'new_invoice' => 'fa-file-invoice',
    'new_patient' => 'fa-user-plus',
    'new_appointment' => 'fa-calendar-plus',
    'appointment_reminder' => 'fa-bell',
    'lab_result' => 'fa-flask',
    'prescription' => 'fa-prescription',
    'message' => 'fa-envelope',
    'referral' => 'fa-share-alt',
    'system' => 'fa-cog',
    'info' => 'fa-info-circle',
    'warning' => 'fa-exclamation-triangle',
];

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="fas fa-bell me-2 text-primary"></i>Notifications
        <?php if ($unreadCount > 0): ?>
            <span class="badge bg-danger ms-2"><?= $unreadCount ?> Unread</span>
        <?php endif; ?>
    </h4>
    <div>
        <?php if ($unreadCount > 0): ?>
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-success btn-sm me-1"><i class="fas fa-check-double me-1"></i>Mark All Read</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($notifications)): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete all notifications?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete All</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small">Filter</label>
                <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Notifications</option>
                    <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small">Type</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t['type']) ?>" <?= $typeFilter === $t['type'] ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t['type'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <?php if (empty($notifications)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">No notifications found.</p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $n): ?>
                <?php
                $icon = $typeIcons[$n['type']] ?? 'fa-bell';
                $bgClass = $n['is_read'] ? '' : 'list-group-item-primary';
                ?>
                <div class="list-group-item list-group-item-action <?= $bgClass ?>">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1">
                                <i class="fas <?= $icon ?> fa-fw text-<?= $n['is_read'] ? 'muted' : 'primary' ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="<?= $n['is_read'] ? '' : 'text-primary' ?>"><?= htmlspecialchars($n['title']) ?></strong>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge bg-primary rounded-pill" style="width:8px;height:8px;padding:0"></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 text-muted small"><?= htmlspecialchars($n['message']) ?></p>
                                <small class="text-muted"><i class="far fa-clock me-1"></i><?= timeAgo($n['created_at']) ?></small>
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0 ms-3">
                            <?php if (!$n['is_read']): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <?php if ($n['link']): ?>
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($n['link']) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark Read">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($n['link']): ?>
                                <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $pagination['prev_page'] ?>&filter=<?= $filter ?>&type=<?= $typeFilter ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&type=<?= $typeFilter ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $pagination['next_page'] ?>&filter=<?= $filter ?>&type=<?= $typeFilter ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
