<?php
// Route: /team/create
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Create Team - Team Competition';

$error = '';
$old = ['name' => '', 'description' => '', 'max_members' => '10'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $teamController = new \App\Controllers\TeamController();
        $result = $teamController->createTeam($_POST, $_FILES, $_SESSION['user']['id']);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = 'Team created successfully!';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($result['team_id']));
            exit;
        } else {
            $error = $result['error'];
            $old = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'max_members' => $_POST['max_members'] ?? '10',
            ];
        }
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-8">
        <a href="/team/manage" class="link link-hover">Manage Teams</a> / Create Team
    </h1>

    <?php if ($error): ?>
        <div class="alert alert-error mb-6">
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                <!-- Team Logo -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team Logo</span>
                    </label>
                    <div class="flex flex-col items-center gap-4">
                        <label for="logoInput" class="avatar cursor-pointer hover:opacity-80 transition-opacity">
                            <div class="w-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 bg-primary text-primary-content flex items-center justify-center text-4xl font-bold" id="logoPreview">
                                T
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
                    <input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" 
                           class="input input-bordered w-full" placeholder="Enter team name" required maxlength="255" />
                </div>

                <!-- Description -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Description</span>
                    </label>
                    <textarea name="description" class="textarea textarea-bordered w-full h-24" 
                              placeholder="Tell others about your team" maxlength="1000"><?= htmlspecialchars($old['description']) ?></textarea>
                </div>

                <!-- Max Members -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Maximum Members</span>
                        <span class="label-text-alt">Limit team size</span>
                    </label>
                    <input type="number" name="max_members" value="<?= htmlspecialchars($old['max_members']) ?>" 
                           class="input input-bordered w-full" min="2" max="100" required />
                    <label class="label">
                        <span class="label-text-alt">Must be between 2 and 100</span>
                    </label>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary w-full">Create Team</button>
            </form>
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
