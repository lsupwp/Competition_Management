<?php
// Route: /team/settings?id={team_id}
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Team Settings - Team Competition';

// Get team ID from query param
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$teamId) {
    header('Location: /team/manage');
    exit;
}

$teamController = new \App\Controllers\TeamController();

// Handle POST - update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $result = $teamController->updateTeamSettings($teamId, $_SESSION['user']['id'], $_POST, $_FILES);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }
        
        header('Location: /team/settings?id=' . $teamId);
        exit;
        
    } elseif ($action === 'delete_team') {
        // Check if user is owner
        $team = $teamController->getTeamById($teamId, $_SESSION['user']['id']);
        
        if (!$team || $team['user_role'] !== 'owner') {
            $_SESSION['flash_error'] = 'Only team owner can delete the team';
            header('Location: /team/settings?id=' . $teamId);
            exit;
        }
        
        // Soft delete team
        $db = \App\Services\Database::getInstance();
        $stmt = $db->prepare("UPDATE teams SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $teamId);
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Team deleted successfully';
            header('Location: /team/manage');
        } else {
            $_SESSION['flash_error'] = 'Failed to delete team';
            header('Location: /team/settings?id=' . $teamId);
        }
        $stmt->close();
        exit;
    }
}

// Get team data
$selectedTeam = $teamController->getTeamById($teamId, $_SESSION['user']['id']);

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

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-xl mb-4">Team Settings</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="update_settings">

                <!-- Team Logo -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team Logo</span>
                    </label>
                    <div class="flex flex-col items-center gap-4">
                        <label for="logoInput" class="avatar cursor-pointer hover:opacity-80 transition-opacity">
                            <div class="w-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 bg-primary text-primary-content flex items-center justify-center text-4xl font-bold" id="logoPreview">
                                <?php if (!empty($selectedTeam['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($selectedTeam['logo_url']) ?>" alt="Team logo" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <?= strtoupper(substr($selectedTeam['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                        </label>
                        <input type="file" name="logo" id="logoInput" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp" />
                    </div>
                    <div class="flex flex-col items-center mt-4">
                        <label class="label">
                            <span class="label-text-alt">Click logo to change. Max 2MB. JPG, PNG, GIF, WebP</span>
                        </label>
                    </div>
                </div>

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
                              maxlength="1000"><?= htmlspecialchars($selectedTeam['description'] ?? '') ?></textarea>
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
                <form method="POST">
                    <input type="hidden" name="action" value="delete_team">
                    <button type="submit" class="btn btn-error btn-outline" onclick="return confirm('Are you sure you want to delete this team? This action cannot be undone.')">
                        Delete Team
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Logo preview
const logoInput = document.getElementById('logoInput');
if (logoInput) {
    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('logoPreview');
                preview.innerHTML = `<img src="${e.target.result}" alt="Logo preview" class="w-full h-full object-cover" />`;
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
