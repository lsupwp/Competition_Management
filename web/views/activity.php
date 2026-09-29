<?php
// Route: /activity
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Activity Log - Team Competition';

$activityLog = new \App\Services\ActivityLogService();

// Get filters from query params
$filters = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// Optional filters
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'] . ' 00:00:00';
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'] . ' 23:59:59';
}

$result = $activityLog->getLogs($filters, $page, $perPage);
$logs = $result['logs'];
$totalPages = $result['totalPages'];
$total = $result['total'];

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-7xl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Activity Log</h1>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <h2 class="card-title text-xl mb-4">Filters</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Action</span>
                    </label>
                    <select name="action" class="select select-bordered">
                        <option value="">All Actions</option>
                        <optgroup label="Authentication">
                            <option value="auth.login" <?= ($filters['action'] ?? '') === 'auth.login' ? 'selected' : '' ?>>User Login</option>
                            <option value="auth.logout" <?= ($filters['action'] ?? '') === 'auth.logout' ? 'selected' : '' ?>>User Logout</option>
                            <option value="auth.register" <?= ($filters['action'] ?? '') === 'auth.register' ? 'selected' : '' ?>>User Registration</option>
                        </optgroup>
                        <optgroup label="User Settings">
                            <option value="user.profile.update" <?= ($filters['action'] ?? '') === 'user.profile.update' ? 'selected' : '' ?>>Profile Updated</option>
                            <option value="user.email.change" <?= ($filters['action'] ?? '') === 'user.email.change' ? 'selected' : '' ?>>Email Changed</option>
                            <option value="user.password.add" <?= ($filters['action'] ?? '') === 'user.password.add' ? 'selected' : '' ?>>Password Added</option>
                        </optgroup>
                        <optgroup label="Team Management">
                            <option value="team.create" <?= ($filters['action'] ?? '') === 'team.create' ? 'selected' : '' ?>>Team Created</option>
                            <option value="team.delete" <?= ($filters['action'] ?? '') === 'team.delete' ? 'selected' : '' ?>>Team Deleted</option>
                            <option value="team.settings.update" <?= ($filters['action'] ?? '') === 'team.settings.update' ? 'selected' : '' ?>>Team Settings Updated</option>
                            <option value="team.join" <?= ($filters['action'] ?? '') === 'team.join' ? 'selected' : '' ?>>Team Joined</option>
                            <option value="team.leave" <?= ($filters['action'] ?? '') === 'team.leave' ? 'selected' : '' ?>>Team Left</option>
                            <option value="team.member.kick" <?= ($filters['action'] ?? '') === 'team.member.kick' ? 'selected' : '' ?>>Member Kicked</option>
                            <option value="team.member.role_change" <?= ($filters['action'] ?? '') === 'team.member.role_change' ? 'selected' : '' ?>>Member Role Changed</option>
                            <option value="team.ownership.transfer" <?= ($filters['action'] ?? '') === 'team.ownership.transfer' ? 'selected' : '' ?>>Ownership Transferred</option>
                            <option value="team.invite.create" <?= ($filters['action'] ?? '') === 'team.invite.create' ? 'selected' : '' ?>>Invitation Created</option>
                            <option value="team.invite.revoke" <?= ($filters['action'] ?? '') === 'team.invite.revoke' ? 'selected' : '' ?>>Invitation Revoked</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Date From</span>
                    </label>
                    <input type="date" name="date_from" class="input input-bordered" 
                           value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>" />
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Date To</span>
                    </label>
                    <input type="date" name="date_to" class="input input-bordered" 
                           value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>" />
                </div>

                <div class="form-control flex justify-end items-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h2 class="card-title text-xl">Activities</h2>
                <span class="text-sm text-base-content/70"><?= $total ?> total activities</span>
            </div>

            <?php if (empty($logs)): ?>
                <div class="text-center py-12">
                    <p class="text-base-content/70">No activities found</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar">
                                                    <div class="w-8 rounded-full bg-base-300 flex items-center justify-center font-bold text-sm">
                                                        <?php if (!empty($log['user_avatar'])): ?>
                                                            <img src="<?= htmlspecialchars($log['user_avatar']) ?>" alt="<?= htmlspecialchars($log['user_name']) ?>" />
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm"><?= htmlspecialchars($log['user_name']) ?></div>
                                                    <div class="text-xs opacity-70"><?= htmlspecialchars($log['user_email']) ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm opacity-50">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-outline badge-sm"><?= htmlspecialchars($log['action']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-sm"><?= htmlspecialchars($log['description']) ?></span>
                                        <?php if (!empty($log['metadata'])): ?>
                                            <details class="mt-1">
                                                <summary class="text-xs text-primary cursor-pointer">Details</summary>
                                                <pre class="text-xs mt-1 bg-base-200 p-2 rounded overflow-x-auto"><?= htmlspecialchars(json_encode($log['metadata'], JSON_PRETTY_PRINT)) ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-sm font-mono"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <span class="text-sm"><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <div class="join">
                            <?php
                            // Build query string without page
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <a href="?<?= $queryString ?><?= $queryString ? '&' : '' ?>page=<?= $page - 1 ?>" class="join-item btn btn-sm">«</a>
                            <?php endif; ?>
                            
                            <?php
                            // Show page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?<?= $queryString ?><?= $queryString ? '&' : '' ?>page=<?= $i ?>" 
                                   class="join-item btn btn-sm <?= $i === $page ? 'btn-active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= $queryString ?><?= $queryString ? '&' : '' ?>page=<?= $page + 1 ?>" class="join-item btn btn-sm">»</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
