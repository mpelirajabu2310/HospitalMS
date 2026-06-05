<?php
define("PAGE_TITLE", "Active Sessions");
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
Auth::requireRole('super_admin');

$action = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $message = 'Invalid security token.';
    } elseif (isset($_POST['terminate']) && !empty($_POST['session_id'])) {
        $sessionId = (int)$_POST['session_id'];
        Database::query("DELETE FROM sessions WHERE id = ? AND user_id != ?", [$sessionId, Auth::id()]);
        $message = 'Session terminated successfully.';
        logActivity(Auth::id(), 'terminate_session', 'auth', 'Terminated session ID: ' . $sessionId);
    } elseif (isset($_POST['terminate_all'])) {
        Database::query("DELETE FROM sessions WHERE user_id != ?", [Auth::id()]);
        $message = 'All other sessions terminated.';
        logActivity(Auth::id(), 'terminate_all_sessions', 'auth', 'Terminated all other sessions');
    } elseif (isset($_POST['terminate_user']) && !empty($_POST['user_id'])) {
        $targetUserId = (int)$_POST['user_id'];
        Database::query("DELETE FROM sessions WHERE user_id = ? AND user_id != ?", [$targetUserId, Auth::id()]);
        $message = 'All sessions for that user terminated.';
        logActivity(Auth::id(), 'terminate_user_sessions', 'auth', 'Terminated sessions for user ID: ' . $targetUserId);
    }
}

$sessions = Database::fetchAll(
    "SELECT s.*, u.first_name, u.last_name, u.email, u.username 
     FROM sessions s 
     JOIN users u ON s.user_id = u.id 
     ORDER BY s.last_activity DESC"
);

$currentSessionId = session_id();

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-history me-2 text-primary"></i>Active Sessions</h4>
    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Terminate all other sessions?')">
        <?= csrf_field() ?>
        <button type="submit" name="terminate_all" value="1" class="btn btn-outline-danger">
            <i class="fas fa-ban me-1"></i> Terminate All Others
        </button>
    </form>
</div>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Last Activity</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No active sessions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <tr class="<?= $session['session_id'] === $currentSessionId ? 'table-primary' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-user-circle fa-lg text-muted"></i>
                                        </div>
                                        <div>
                                            <span class="fw-medium"><?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']) ?></span>
                                            <small class="d-block text-muted"><?= htmlspecialchars($session['email']) ?></small>
                                        </div>
                                    </div>
                                    <?php if ($session['session_id'] === $currentSessionId): ?>
                                        <span class="badge bg-success ms-2">Current</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($session['ip_address'] ?? 'N/A') ?></code></td>
                                <td class="text-truncate" style="max-width:200px" title="<?= htmlspecialchars($session['user_agent'] ?? '') ?>">
                                    <small class="text-muted"><?= htmlspecialchars(truncate($session['user_agent'] ?? 'Unknown', 60)) ?></small>
                                </td>
                                <td><?= formatDateTime($session['last_activity']) ?></td>
                                <td><?= formatDateTime($session['created_at']) ?></td>
                                <td class="text-center">
                                    <?php if ($session['session_id'] !== $currentSessionId): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Terminate this session?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" name="terminate" value="1" class="btn btn-sm btn-outline-danger" title="Terminate Session">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">�</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (count($sessions) > 0): ?>
        <div class="card-footer">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i> 
                Showing <?= count($sessions) ?> active session(s). 
                Your current session is highlighted in blue.
            </small>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
