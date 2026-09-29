<?php
// Route: /team/manage or /team/manage?id={encoded_team_id}
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Manage Teams - Team Competition';

$teamController = new \App\Controllers\TeamController();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: /team/manage');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $teamId = \App\Services\IdEncoder::decode($_POST['team_id'] ?? '');
    
    if (!$teamId) {
        $_SESSION['flash_error'] = 'Invalid team ID';
        header('Location: /team/manage');
        exit;
    }
    
    if ($action === 'kick_member') {
        $targetUserId = \App\Services\IdEncoder::decode($_POST['target_user_id'] ?? '');
        if (!$targetUserId) {
            $_SESSION['flash_error'] = 'Invalid user ID';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
            exit;
        }
        $result = $teamController->kickMember($teamId, $_SESSION['user']['id'], $targetUserId);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }
        
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
        
    } elseif ($action === 'leave_team') {
        $result = $teamController->leaveTeam($teamId, $_SESSION['user']['id']);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
            header('Location: /team/manage');
        } else {
            $_SESSION['flash_error'] = $result['error'];
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        }
        exit;
        
    } elseif ($action === 'change_role') {
        $targetUserId = \App\Services\IdEncoder::decode($_POST['target_user_id'] ?? '');
        if (!$targetUserId) {
            $_SESSION['flash_error'] = 'Invalid user ID';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
            exit;
        }
        $newRole = $_POST['new_role'] ?? '';
        $result = $teamController->changeMemberRole($teamId, $_SESSION['user']['id'], $targetUserId, $newRole);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }
        
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
        
    } elseif ($action === 'revoke_token') {
        $invitationId = \App\Services\IdEncoder::decode($_POST['invitation_id'] ?? '');
        if (!$invitationId) {
            $_SESSION['flash_error'] = 'Invalid invitation ID';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
            exit;
        }
        $result = $teamController->revokeToken($teamId, $_SESSION['user']['id'], $invitationId);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }
        
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
        
    } elseif ($action === 'transfer_ownership') {
        $newOwnerId = \App\Services\IdEncoder::decode($_POST['new_owner_id'] ?? '');
        if (!$newOwnerId) {
            $_SESSION['flash_error'] = 'Invalid user ID';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
            exit;
        }
        $result = $teamController->transferOwnership($teamId, $_SESSION['user']['id'], $newOwnerId);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }
        
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
    }
}

// Get user's teams with pagination and search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$teamData = $teamController->getTeamsForUser($_SESSION['user']['id'], $searchQuery, $page, $perPage);
$allTeams = $teamData['teams'];
$totalPages = $teamData['totalPages'];
$total = $teamData['total'];

// Check if viewing specific team
$selectedTeamId = null;
if (isset($_GET['id'])) {
    $selectedTeamId = \App\Services\IdEncoder::decode($_GET['id']);
}
$selectedTeam = null;
$members = [];
$memberSearch = '';

if ($selectedTeamId) {
    $selectedTeam = $teamController->getTeamById($selectedTeamId, $_SESSION['user']['id']);
    if ($selectedTeam) {
        // Member search
        $memberSearch = isset($_GET['member_search']) ? trim($_GET['member_search']) : '';
        $members = $teamController->getTeamMembers($selectedTeamId, $memberSearch);
        $selectedTeam['member_count'] = count($members);
        $selectedTeam['members'] = $members;
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">
            <?php if ($selectedTeam): ?>
                <a href="/team/manage" class="link link-hover">Manage Teams</a> / <?= htmlspecialchars($selectedTeam['name']) ?>
            <?php else: ?>
                Manage Teams
            <?php endif; ?>
        </h1>
        <a href="/team/create" class="btn btn-primary">Create New Team</a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($allTeams)): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body text-center py-16">
                <h2 class="text-2xl font-bold mb-4">You're not in any teams yet</h2>
                <p class="text-base-content/70 mb-6">Create a new team or join an existing one to get started</p>
                <div class="flex gap-4 justify-center">
                    <a href="/team/create" class="btn btn-primary">Create Team</a>
                    <a href="/team/join" class="btn btn-outline">Join Team</a>
                </div>
            </div>
        </div>
    <?php elseif ($selectedTeam): ?>
        <!-- Show generated token -->
        <?php if (isset($_GET['show_token']) && $_GET['show_token'] === '1' && isset($_SESSION['invite_token'])): ?>
            <div class="alert alert-success mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h3 class="font-bold">Invite Token Generated!</h3>
                    <div class="text-xs mt-2">
                        <p class="mb-2">Share this link with the person you want to invite:</p>
                        <div class="flex gap-2">
                            <input type="text" value="http://localhost:8000/team/join?token=<?= htmlspecialchars($_SESSION['invite_token']) ?>" 
                                   class="input input-bordered input-sm flex-1" readonly id="inviteLink" />
                            <button class="btn btn-sm btn-primary" onclick="copyInviteLink()">Copy</button>
                        </div>
                        <p class="mt-2 opacity-70">Token: <code class="text-xs"><?= htmlspecialchars($_SESSION['invite_token']) ?></code></p>
                        <p class="opacity-70">Expires: <?= date('M d, Y H:i', strtotime($_SESSION['invite_token_expires'])) ?></p>
                    </div>
                </div>
            </div>
            <?php 
            unset($_SESSION['invite_token']);
            unset($_SESSION['invite_token_expires']);
            ?>
        <?php endif; ?>

        <!-- Team Detail View -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-4">
                        <div class="avatar">
                            <div class="w-16 rounded-full bg-primary text-primary-content flex items-center justify-center text-2xl font-bold">
                                <?php if (!empty($selectedTeam['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($selectedTeam['logo_url']) ?>" alt="<?= htmlspecialchars($selectedTeam['name']) ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <?= strtoupper(substr($selectedTeam['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h2 class="card-title text-2xl"><?= htmlspecialchars($selectedTeam['name']) ?></h2>
                            <p class="text-sm text-base-content/70"><?= htmlspecialchars($selectedTeam['description'] ?? '') ?></p>
                            <div class="flex gap-2 mt-2">
                                <span class="badge badge-outline"><?= $selectedTeam['member_count'] ?>/<?= $selectedTeam['max_members'] ?> members</span>
                                <span class="badge badge-primary badge-sm">Your role: <?= ucfirst($selectedTeam['user_role']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <?php if ($selectedTeam['user_role'] === 'owner' || $selectedTeam['user_role'] === 'admin'): ?>
                            <button class="btn btn-primary btn-sm" onclick="inviteModal.showModal()">
                                Invite Member
                            </button>
                        <?php endif; ?>
                        <?php if ($selectedTeam['user_role'] === 'owner'): ?>
                            <button class="btn btn-warning btn-sm" onclick="transferOwnershipModal.showModal()">
                                Transfer Ownership
                            </button>
                            <a href="/team/settings?id=<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>" class="btn btn-outline btn-sm">
                                Settings
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg">Members</h3>
                    <form method="GET" class="flex gap-2">
                        <input type="hidden" name="id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                        <input type="text" name="member_search" value="<?= htmlspecialchars($memberSearch) ?>" 
                               placeholder="Search members..." class="input input-bordered input-sm w-64" />
                        <button type="submit" class="btn btn-sm btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                        <?php if ($memberSearch): ?>
                            <a href="/team/manage?id=<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>" class="btn btn-sm btn-ghost">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selectedTeam['members'] as $member): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="w-10 rounded-full bg-base-300 flex items-center justify-center font-bold">
                                                    <?php if (!empty($member['avatar_url'])): ?>
                                                        <img src="<?= htmlspecialchars($member['avatar_url']) ?>" alt="<?= htmlspecialchars($member['name']) ?>" />
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?= htmlspecialchars($member['name']) ?></div>
                                                <div class="text-sm opacity-70"><?= htmlspecialchars($member['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($selectedTeam['user_role'] === 'owner' && $member['id'] !== $_SESSION['user']['id']): ?>
                                            <form method="POST" style="display:inline;">
                                                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                                <input type="hidden" name="target_user_id" value="<?= \App\Services\IdEncoder::encode($member['id']) ?>">
                                                <select name="new_role" class="select select-bordered select-sm" onchange="this.form.submit()">
                                                    <option value="owner" <?= $member['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                                                    <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-<?= $member['role'] === 'owner' ? 'primary' : ($member['role'] === 'admin' ? 'secondary' : 'ghost') ?>">
                                                <?= ucfirst($member['role']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($member['joined_at'])) ?></td>
                                    <td>
                                        <?php if ($member['id'] === $_SESSION['user']['id']): ?>
                                            <?php if ($selectedTeam['user_role'] === 'owner'): ?>
                                                <span class="text-sm opacity-50">You (Owner)</span>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                    <input type="hidden" name="action" value="leave_team">
                                                    <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                                    <button type="submit" class="btn btn-error btn-sm" onclick="return confirm('Are you sure you want to leave this team?')">
                                                        Leave Team
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($selectedTeam['user_role'] === 'owner' && $member['role'] !== 'owner'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                    <input type="hidden" name="action" value="kick_member">
                                                    <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                                    <input type="hidden" name="target_user_id" value="<?= \App\Services\IdEncoder::encode($member['id']) ?>">
                                                    <button type="submit" class="btn btn-error btn-sm btn-outline" onclick="return confirm('Are you sure you want to kick this member?')">
                                                        Kick
                                                    </button>
                                                </form>
                                            <?php elseif ($selectedTeam['user_role'] === 'admin' && $member['role'] === 'member'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                    <input type="hidden" name="action" value="kick_member">
                                                    <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                                    <input type="hidden" name="target_user_id" value="<?= \App\Services\IdEncoder::encode($member['id']) ?>">
                                                    <button type="submit" class="btn btn-error btn-sm btn-outline" onclick="return confirm('Are you sure you want to kick this member?')">
                                                        Kick
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-sm opacity-50">-</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invite Modal -->
            <?php if ($selectedTeam['user_role'] === 'owner' || $selectedTeam['user_role'] === 'admin'): ?>
                <?php
                // Get existing valid tokens for this team
                $db = \App\Services\Database::getInstance();
                $stmt = $db->prepare("
                    SELECT id, token, expires_at, created_at, invited_by
                    FROM team_invitations
                    WHERE team_id = ? AND deleted_at IS NULL AND used_at IS NULL AND expires_at > NOW() AND email IS NULL
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->bind_param('i', $selectedTeam['id']);
                $stmt->execute();
                $existingTokens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                ?>
                <dialog id="inviteModal" class="modal">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">Invite Member to <?= htmlspecialchars($selectedTeam['name']) ?></h3>
                        
                        <div role="tablist" class="tabs tabs-bordered mt-4">
                            <input type="radio" name="invite_tabs" role="tab" class="tab" aria-label="Email Invite" checked />
                            <div role="tabpanel" class="tab-content pt-4">
                                <form method="POST" action="/team/invite" class="space-y-4">
                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                    <input type="hidden" name="action" value="invite_email">
                                    <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                    
                                    <div class="form-control w-full">
                                        <label class="label">
                                            <span class="label-text">Email Address</span>
                                        </label>
                                        <input type="email" name="email" placeholder="user@example.com" class="input input-bordered w-full" required />
                                    </div>

                                    <div class="modal-action">
                                        <button type="button" class="btn" onclick="inviteModal.close()">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Send Invite</button>
                                    </div>
                                </form>
                            </div>
                            
                            <input type="radio" name="invite_tabs" role="tab" class="tab" aria-label="Generate Token" />
                            <div role="tabpanel" class="tab-content pt-4">
                                <?php if (empty($existingTokens)): ?>
                                    <!-- No active token, show generate form -->
                                    <form method="POST" action="/team/invite" class="space-y-4">
                                        <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                        <input type="hidden" name="action" value="generate_token">
                                        <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                        
                                        <p class="text-sm text-base-content/70">Generate a shareable invite link. Anyone with this link can join as a member.</p>
                                        
                                        <div class="alert alert-info">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Token expires in 7 days. Can be used multiple times until revoked or expired.</span>
                                        </div>

                                        <div class="modal-action">
                                            <button type="button" class="btn" onclick="inviteModal.close()">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Generate Token</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <!-- Active token exists, show it -->
                                    <?php foreach ($existingTokens as $tokenData): ?>
                                        <div class="space-y-3">
                                            <h4 class="font-semibold text-sm">Active Invite Link:</h4>
                                            <div class="bg-base-200 p-3 rounded-lg">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <input type="text" value="http://localhost:8000/team/join?token=<?= htmlspecialchars($tokenData['token']) ?>" 
                                                           class="input input-bordered input-sm flex-1 text-xs" readonly id="token_<?= htmlspecialchars($tokenData['token']) ?>" />
                                                    <button class="btn btn-sm btn-primary" onclick="copyToken('token_<?= htmlspecialchars($tokenData['token']) ?>')">Copy</button>
                                                    <?php 
                                                    // Show revoke button if owner OR admin who created this token
                                                    $canRevoke = ($selectedTeam['user_role'] === 'owner') || 
                                                                 ($selectedTeam['user_role'] === 'admin' && $tokenData['invited_by'] == $_SESSION['user']['id']);
                                                    if ($canRevoke): 
                                                    ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke this token? It will no longer be usable.')">
                                                            <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                            <input type="hidden" name="action" value="revoke_token">
                                                            <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                                            <input type="hidden" name="invitation_id" value="<?= \App\Services\IdEncoder::encode($tokenData['id']) ?>">
                                                            <button type="submit" class="btn btn-sm btn-error btn-outline">Revoke</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs opacity-70">
                                                    Expires: <?= date('M d, Y H:i', strtotime($tokenData['expires_at'])) ?>
                                                </div>
                                            </div>
                                            <div class="alert alert-info">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Only one active token allowed. Revoke this token to generate a new one.</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>

                <!-- Transfer Ownership Modal -->
                <?php if ($selectedTeam['user_role'] === 'owner'): ?>
                    <dialog id="transferOwnershipModal" class="modal">
                        <div class="modal-box">
                            <h3 class="font-bold text-lg">Transfer Ownership</h3>
                            <p class="py-4 text-sm text-base-content/70">
                                Transfer ownership of this team to another member. You will become an admin after the transfer.
                            </p>
                            <form method="POST" class="space-y-4">
                                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                <input type="hidden" name="action" value="transfer_ownership">
                                <input type="hidden" name="team_id" value="<?= \App\Services\IdEncoder::encode($selectedTeam['id']) ?>">
                                
                                <div class="form-control w-full">
                                    <label class="label">
                                        <span class="label-text">Select New Owner</span>
                                    </label>
                                    <select name="new_owner_id" class="select select-bordered w-full" required>
                                        <option value="">Choose a member...</option>
                                        <?php foreach ($selectedTeam['members'] as $member): ?>
                                            <?php if ($member['id'] !== $_SESSION['user']['id'] && $member['role'] !== 'owner'): ?>
                                                <option value="<?= \App\Services\IdEncoder::encode($member['id']) ?>">
                                                    <?= htmlspecialchars($member['name']) ?> (<?= ucfirst($member['role']) ?>)
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="alert alert-warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>This action cannot be undone easily. The new owner will have full control.</span>
                                </div>

                                <div class="modal-action">
                                    <button type="button" class="btn" onclick="transferOwnershipModal.close()">Cancel</button>
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to transfer ownership? This action cannot be undone easily.')">Transfer Ownership</button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button>close</button>
                        </form>
                    </dialog>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Team List View -->
        <div class="mb-6">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="Search teams..." class="input input-bordered flex-1" />
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
                <?php if ($searchQuery): ?>
                <a href="/team/manage" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($allTeams)): ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body text-center py-16">
                    <h2 class="text-2xl font-bold mb-4">No teams found</h2>
                    <p class="text-base-content/70 mb-6">
                        <?php if ($searchQuery): ?>
                            No teams match your search "<?= htmlspecialchars($searchQuery) ?>"
                        <?php else: ?>
                            You're not in any teams yet
                        <?php endif; ?>
                    </p>
                    <?php if (!$searchQuery): ?>
                    <div class="flex gap-4 justify-center">
                        <a href="/team/create" class="btn btn-primary">Create Team</a>
                        <a href="/team/join" class="btn btn-outline">Join Team</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <div class="mb-4 text-sm text-base-content/70">
            Showing <?= count($allTeams) ?> of <?= $total ?> teams
        </div>
        <div class="grid gap-4">
            <?php foreach ($allTeams as $team): ?>
                <a href="/team/manage?id=<?= \App\Services\IdEncoder::encode($team['id']) ?>" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow cursor-pointer">
                    <div class="card-body">
                        <div class="flex items-center gap-4">
                            <div class="avatar">
                                <div class="w-16 rounded-full bg-primary text-primary-content flex items-center justify-center text-2xl font-bold">
                                    <?php if (!empty($team['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($team['logo_url']) ?>" alt="<?= htmlspecialchars($team['name']) ?>" class="w-full h-full object-cover" />
                                    <?php else: ?>
                                        <?= strtoupper(substr($team['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h2 class="card-title text-xl"><?= htmlspecialchars($team['name']) ?></h2>
                                <p class="text-sm text-base-content/70"><?= htmlspecialchars($team['description'] ?? '') ?></p>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-outline"><?= $team['member_count'] ?>/<?= $team['max_members'] ?> members</span>
                                    <span class="badge badge-primary badge-sm">Your role: <?= ucfirst($team['user_role']) ?></span>
                                </div>
                            </div>
                            <div class="text-base-content/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
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
    <?php endif; ?>
</div>

<script>
function copyInviteLink() {
    const input = document.getElementById('inviteLink');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    // Show feedback
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(() => {
        btn.textContent = originalText;
    }, 2000);
}

function copyToken(inputId) {
    const input = document.getElementById(inputId);
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    
    // Show feedback
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(() => {
        btn.textContent = originalText;
    }, 2000);
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
