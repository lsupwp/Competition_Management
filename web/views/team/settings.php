<?php
// Route: /team/settings?id={team_id}
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Team Settings - Team Competition';

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
    ],
    [
        'id' => 2,
        'name' => 'Beta Squad',
        'description' => 'Casual team for fun competitions',
        'logo_url' => null,
        'max_members' => 8,
        'is_public' => 1,
        'user_role' => 'admin',
    ],
    [
        'id' => 3,
        'name' => 'Gamma Force',
        'description' => 'Elite team for professional tournaments',
        'logo_url' => null,
        'max_members' => 5,
        'is_public' => 0,
        'user_role' => 'member',
    ]
];

// Get team ID from query param
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$teamId) {
    header('Location: /team/manage');
    exit;
}

// Find team and check permissions
$selectedTeam = null;
foreach ($mockTeams as $team) {
    if ($team['id'] === $teamId) {
        $selectedTeam = $team;
        break;
    }
}

if (!$selectedTeam) {
    header('Location: /team/manage');
    exit;
}

// Only owner can access settings
if ($selectedTeam['user_role'] !== 'owner') {
    $_SESSION['flash_error'] = 'You do not have permission to access team settings';
    header('Location: /team/manage?id=' . $teamId);
    exit;
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">
            <a href="/team/manage" class="link link-hover">Manage Teams</a> / 
            <a href="/team/manage?id=<?= $teamId ?>" class="link link-hover"><?= htmlspecialchars($selectedTeam['name']) ?></a> / 
            Settings
        </h1>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-xl mb-4">Team Settings</h2>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="team_id" value="<?= $teamId ?>">

                <!-- Team Name -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team Name</span>
                    </label>
                    <input type="text" name="name" value="<?= htmlspecialchars($selectedTeam['name']) ?>" 
                           class="input input-bordered w-full" required maxlength="255" />
                </div>

                <!-- Description -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Description</span>
                    </label>
                    <textarea name="description" class="textarea textarea-bordered w-full h-24" 
                              maxlength="1000"><?= htmlspecialchars($selectedTeam['description']) ?></textarea>
                </div>

                <!-- Max Members -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Maximum Members</span>
                        <span class="label-text-alt">Limit team size</span>
                    </label>
                    <input type="number" name="max_members" value="<?= $selectedTeam['max_members'] ?>" 
                           class="input input-bordered w-full" min="2" max="100" required />
                    <label class="label">
                        <span class="label-text-alt">Must be between 2 and 100</span>
                    </label>
                </div>

                <!-- Visibility -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team Visibility</span>
                    </label>
                    <div class="space-y-2">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="radio" name="is_public" value="1" 
                                   class="radio radio-primary" 
                                   <?= $selectedTeam['is_public'] ? 'checked' : '' ?> />
                            <div>
                                <div class="font-semibold">Public</div>
                                <div class="text-sm text-base-content/70">Anyone can find and request to join</div>
                            </div>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="radio" name="is_public" value="0" 
                                   class="radio radio-primary" 
                                   <?= !$selectedTeam['is_public'] ? 'checked' : '' ?> />
                            <div>
                                <div class="font-semibold">Private</div>
                                <div class="text-sm text-base-content/70">Only invited members can join</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Submit Button -->
                <div class="form-control">
                    <button type="submit" class="btn btn-primary w-full">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card bg-base-100 shadow-xl mt-6 border border-error">
        <div class="card-body">
            <h2 class="card-title text-xl mb-4 text-error">Danger Zone</h2>
            
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold">Delete Team</div>
                    <div class="text-sm text-base-content/70">Once deleted, this team cannot be recovered</div>
                </div>
                <button class="btn btn-error btn-outline" onclick="deleteTeam(<?= $teamId ?>)">
                    Delete Team
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function deleteTeam(teamId) {
    if (confirm('Are you sure you want to delete this team? This action cannot be undone.')) {
        if (confirm('This will permanently delete the team and all its data. Continue?')) {
            // TODO: Implement API call
            alert(`Team deleted (mock - not implemented yet)`);
            window.location.href = '/team/manage';
        }
    }
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
