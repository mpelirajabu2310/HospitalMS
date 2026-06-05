<?php
define('PAGE_TITLE', 'Activity Logs');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
if (!Auth::isSuperAdmin()) { redirect('/index.php'); }

$tab = $_GET['tab'] ?? 'activities';
$page = max(1, intval($_GET['page'] ?? 1));
$filterUser = intval($_GET['user_id'] ?? 0);
$filterAction = trim($_GET['action'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-history me-2 text-primary"></i>Activity Logs</h4>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'activities' ? 'active' : '' ?>" href="?tab=activities&page=1">User Activities</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'audit' ? 'active' : '' ?>" href="?tab=audit&page=1">Audit Logs</a>
    </li>
</ul>

<?php
$users = Database::fetchAll("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");

if ($tab === 'activities'):
    $where = [];
    $params = [];
    if ($filterUser) { $where[] = 'l.user_id = ?'; $params[] = $filterUser; }
    if ($filterAction) { $where[] = 'l.action LIKE ?'; $params[] = "%$filterAction%"; }
    if ($dateFrom) { $where[] = 'l.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 'l.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM user_activity_logs l $whereClause", $params
    )['count'];
    $pag = paginate($total, $page);

    $logs = Database::fetchAll(
        "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
         FROM user_activity_logs l
         LEFT JOIN users u ON l.user_id = u.id
         $whereClause
         ORDER BY l.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$pag['limit'], $pag['offset']])
    );
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="activities">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">User</label>
                    <select name="user_id" class="form-select">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterUser === $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">Action</label>
                    <input type="text" name="action" class="form-control" placeholder="e.g. login, create" value="<?= htmlspecialchars($filterAction) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
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
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No activity logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($l['user_name'] ?? 'System') ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($l['action']) ?></span></td>
                                    <td><?= htmlspecialchars($l['module'] ?? '-') ?></td>
                                    <td class="small"><?= htmlspecialchars($l['description'] ?? '-') ?></td>
                                    <td class="small text-muted"><code><?= htmlspecialchars($l['ip_address'] ?? '-') ?></code></td>
                                    <td class="small text-muted"><?= formatDateTime($l['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pag['total_pages'] > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= !$pag['has_prev'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=activities&page=<?= $pag['prev_page'] ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=activities&page=<?= $i ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= !$pag['has_next'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=activities&page=<?= $pag['next_page'] ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($tab === 'audit'):
    $where = [];
    $params = [];
    if ($filterUser) { $where[] = 'l.user_id = ?'; $params[] = $filterUser; }
    if ($filterAction) { $where[] = 'l.action LIKE ?'; $params[] = "%$filterAction%"; }
    if ($dateFrom) { $where[] = 'l.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo) { $where[] = 'l.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = Database::fetch(
        "SELECT COUNT(*) as count FROM audit_logs l $whereClause", $params
    )['count'];
    $pag = paginate($total, $page);

    $logs = Database::fetchAll(
        "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
         FROM audit_logs l
         LEFT JOIN users u ON l.user_id = u.id
         $whereClause
         ORDER BY l.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$pag['limit'], $pag['offset']])
    );
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="audit">
                <div class="col-md-3">
                    <label class="form-label fw-medium small">User</label>
                    <select name="user_id" class="form-select">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterUser === $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium small">Action</label>
                    <input type="text" name="action" class="form-control" placeholder="e.g. create, update" value="<?= htmlspecialchars($filterAction) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium small">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
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
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Entity ID</th>
                            <th>Changes</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No audit logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($l['user_name'] ?? 'System') ?></td>
                                    <td><span class="badge bg-<?= $l['action'] === 'create' ? 'success' : ($l['action'] === 'update' ? 'warning' : ($l['action'] === 'delete' ? 'danger' : 'info')) ?>"><?= htmlspecialchars($l['action']) ?></span></td>
                                    <td><?= htmlspecialchars($l['entity_type'] ?? '-') ?></td>
                                    <td><?= $l['entity_id'] ? '#' . $l['entity_id'] : '-' ?></td>
                                    <td>
                                        <?php if ($l['old_values'] || $l['new_values']): ?>
                                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#auditDetails-<?= $l['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="modal fade" id="auditDetails-<?= $l['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h6 class="modal-title">Audit Details #<?= $l['id'] ?></h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6 class="text-danger">Old Values</h6>
                                                                    <pre class="small p-2 rounded" style="background:var(--bg-body);color:var(--text-primary)"><?= htmlspecialchars(json_encode(json_decode($l['old_values'] ?? '{}'), JSON_PRETTY_PRINT)) ?></pre>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6 class="text-success">New Values</h6>
                                                                    <pre class="small p-2 rounded" style="background:var(--bg-body);color:var(--text-primary)"><?= htmlspecialchars(json_encode(json_decode($l['new_values'] ?? '{}'), JSON_PRETTY_PRINT)) ?></pre>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><code><?= htmlspecialchars($l['ip_address'] ?? '-') ?></code></td>
                                    <td class="small text-muted"><?= formatDateTime($l['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pag['total_pages'] > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= !$pag['has_prev'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=audit&page=<?= $pag['prev_page'] ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=audit&page=<?= $i ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= !$pag['has_next'] ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=audit&page=<?= $pag['next_page'] ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
