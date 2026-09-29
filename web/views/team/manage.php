<?php
// Route: /team/manage or /team/manage?id={team_id}
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Manage Teams - Team Competition';

// Mock data - will be replaced with DB queries
$mockTeams = [
    [
        'id' => 1,
        'name' => 'Alpha Warriors',
        'description' => 'Competitive gaming team focused on strategy games',
        'logo_url' => null,
        'max_members' => 10,
        'is_public' => 1,
        'user_role' => 'owner',
        'member_count' => 5,
        'members' => [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'avatar_url' => null, 'role' => 'owner', 'joined_at' => '2024-01-15'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'avatar_url' => null, 'role' => 'admin', 'joined_at' => '2024-01-16'],
            ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-01-20'],
            ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-02-01'],
            ['id' => 5, 'name' => 'Charlie Davis', 'email' => 'charlie@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-02-10'],
        ]
    ],
    [
        'id' => 2,
        'name' => 'Beta Squad',
        'description' => 'Casual team for fun competitions',
        'logo_url' => null,
        'max_members' => 8,
        'is_public' => 1,
        'user_role' => 'admin',
        'member_count' => 3,
        'members' => [
            ['id' => 6, 'name' => 'David Miller', 'email' => 'david@example.com', 'avatar_url' => null, 'role' => 'owner', 'joined_at' => '2024-01-10'],
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'avatar_url' => null, 'role' => 'admin', 'joined_at' => '2024-01-12'],
            ['id' => 7, 'name' => 'Eve Johnson', 'email' => 'eve@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-01-25'],
        ]
    ],
    [
        'id' => 3,
        'name' => 'Gamma Force',
        'description' => 'Elite team for professional tournaments',
        'logo_url' => null,
        'max_members' => 5,
        'is_public' => 0,
        'user_role' => 'member',
        'member_count' => 4,
        'members' => [
            ['id' => 8, 'name' => 'Frank White', 'email' => 'frank@example.com', 'avatar_url' => null, 'role' => 'owner', 'joined_at' => '2024-01-05'],
            ['id' => 9, 'name' => 'Grace Lee', 'email' => 'grace@example.com', 'avatar_url' => null, 'role' => 'admin', 'joined_at' => '2024-01-06'],
            ['id' => 10, 'name' => 'Henry Taylor', 'email' => 'henry@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-01-08'],
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'avatar_url' => null, 'role' => 'member', 'joined_at' => '2024-01-15'],
        ]
    ]
];

// Check if viewing specific team
$selectedTeamId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$selectedTeam = null;

if ($selectedTeamId) {
    foreach ($mockTeams as $team) {
        if ($team['id'] === $selectedTeamId) {
            $selectedTeam = $team;
            break;
        }
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

    <?php if (empty($mockTeams)): ?>
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
        <!-- Team Detail View -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-4">
                        <div class="avatar">
                            <div class="w-16 rounded-full bg-primary text-primary-content flex items-center justify-center text-2xl font-bold">
                                <?= strtoupper(substr($selectedTeam['name'], 0, 1)) ?>
                            </div>
                        </div>
                        <div>
                            <h2 class="card-title text-2xl"><?= htmlspecialchars($selectedTeam['name']) ?></h2>
                            <p class="text-sm text-base-content/70"><?= htmlspecialchars($selectedTeam['description']) ?></p>
                            <div class="flex gap-2 mt-2">
                                <span class="badge badge-outline"><?= $selectedTeam['member_count'] ?>/<?= $selectedTeam['max_members'] ?> members</span>
                                <?php if ($selectedTeam['is_public']): ?>
                                    <span class="badge badge-success badge-sm">Public</span>
                                <?php else: ?>
                                    <span class="badge badge-warning badge-sm">Private</span>
                                <?php endif; ?>
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
                            <a href="/team/settings?id=<?= $selectedTeam['id'] ?>" class="btn btn-outline btn-sm">
                                Settings
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <h3 class="font-bold text-lg mb-4">Members</h3>
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
                                                    <?= strtoupper(substr($member['name'], 0, 1)) ?>
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
                                            <select class="select select-bordered select-sm" onchange="changeRole(<?= $selectedTeam['id'] ?>, <?= $member['id'] ?>, this.value)">
                                                <option value="owner" <?= $member['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                                                <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                            </select>
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
                                                <button class="btn btn-error btn-sm" onclick="leaveTeam(<?= $selectedTeam['id'] ?>)">
                                                    Leave Team
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($selectedTeam['user_role'] === 'owner' && $member['role'] !== 'owner'): ?>
                                                <button class="btn btn-error btn-sm btn-outline" onclick="kickMember(<?= $selectedTeam['id'] ?>, <?= $member['id'] ?>)">
                                                    Kick
                                                </button>
                                            <?php elseif ($selectedTeam['user_role'] === 'admin' && $member['role'] === 'member'): ?>
                                                <button class="btn btn-error btn-sm btn-outline" onclick="kickMember(<?= $selectedTeam['id'] ?>, <?= $member['id'] ?>)">
                                                    Kick
                                                </button>
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
                <dialog id="inviteModal" class="modal">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">Invite Member to <?= htmlspecialchars($selectedTeam['name']) ?></h3>
                        <form method="POST" class="py-4">
                            <input type="hidden" name="action" value="invite_member">
                            <input type="hidden" name="team_id" value="<?= $selectedTeam['id'] ?>">
                            
                            <div class="form-control w-full mb-4">
                                <label class="label">
                                    <span class="label-text">Email Address</span>
                                </label>
                                <input type="email" name="email" placeholder="user@example.com" class="input input-bordered w-full" required />
                            </div>

                            <div class="form-control w-full mb-4">
                                <label class="label">
                                    <span class="label-text">Role</span>
                                </label>
                                <select name="role" class="select select-bordered w-full">
                                    <option value="member">Member</option>
                                    <?php if ($selectedTeam['user_role'] === 'owner'): ?>
                                        <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="modal-action">
                                <button type="button" class="btn" onclick="inviteModal.close()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Send Invite</button>
                            </div>
                        </form>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Team List View -->
        <div class="grid gap-4">
            <?php foreach ($mockTeams as $team): ?>
                <a href="/team/manage?id=<?= $team['id'] ?>" class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow cursor-pointer">
                    <div class="card-body">
                        <div class="flex items-center gap-4">
                            <div class="avatar">
                                <div class="w-16 rounded-full bg-primary text-primary-content flex items-center justify-center text-2xl font-bold">
                                    <?= strtoupper(substr($team['name'], 0, 1)) ?>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h2 class="card-title text-xl"><?= htmlspecialchars($team['name']) ?></h2>
                                <p class="text-sm text-base-content/70"><?= htmlspecialchars($team['description']) ?></p>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-outline"><?= $team['member_count'] ?>/<?= $team['max_members'] ?> members</span>
                                    <?php if ($team['is_public']): ?>
                                        <span class="badge badge-success badge-sm">Public</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning badge-sm">Private</span>
                                    <?php endif; ?>
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
    <?php endif; ?>
</div>

<script>
function changeRole(teamId, userId, newRole) {
    if (confirm(`Change role to ${newRole}?`)) {
        // TODO: Implement API call
        alert(`Role change to ${newRole} (mock - not implemented yet)`);
    }
}

function kickMember(teamId, userId) {
    if (confirm('Are you sure you want to kick this member?')) {
        // TODO: Implement API call
        alert(`Member kicked (mock - not implemented yet)`);
    }
}

function leaveTeam(teamId) {
    if (confirm('Are you sure you want to leave this team?')) {
        // TODO: Implement API call
        alert(`Left team (mock - not implemented yet)`);
    }
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
