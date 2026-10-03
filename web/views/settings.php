<?php
// Route: /settings
require_once __DIR__ . '/../vendor/autoload.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Settings - Team Competition';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$settingsController = new \App\Controllers\SettingsController();
$userData = $settingsController->getActiveUser((int)$_SESSION['user']['id']);

if (!$userData) {
    \App\Services\SessionService::destroy();
    header('Location: /auth/login');
    exit;
}

$hasPassword = !empty($userData['password_hash']);

$error = '';
$success = '';

if (isset($_SESSION['settings_flash'])) {
    $flash = $_SESSION['settings_flash'];
    unset($_SESSION['settings_flash']);
    if (isset($flash['error'])) {
        $error = $flash['error'];
    }
    if (isset($flash['success'])) {
        $success = $flash['success'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $settingsController->handle(
        (string)($_POST['action'] ?? ''),
        $_POST,
        $_FILES,
        $userData,
        $_SESSION['user']
    );

    if (!empty($result['flash'])) {
        $_SESSION['settings_flash'] = $result['flash'];
    }

    header('Location: ' . ($result['redirect'] ?? '/settings'));
    exit;
}

if (isset($_GET['email_changed']) && $_GET['email_changed'] === '1') {
    $pendingData = $_SESSION['pending_email_change'] ?? null;
    if ($pendingData) {
        $success = $settingsController->applyPendingEmailChange($pendingData, $_SESSION['user']);
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <h1 class="text-3xl font-bold mb-8">Account Settings</h1>

    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-error mb-6">
        <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success mb-6">
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error mb-6">
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <div class="space-y-6">
        <!-- Edit Profile Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Edit Profile</h2>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="edit_profile">
                    <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Avatar</span>
                        </label>
                        <div class="flex flex-col items-center gap-4">
                            <label for="avatarInput" class="avatar cursor-pointer hover:opacity-80 transition-opacity">
                                <div class="w-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                    <?php if (!empty($_SESSION['user']['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($_SESSION['user']['avatar_url']) ?>" alt="Avatar" />
                                    <?php else: ?>
                                    <div class="bg-primary text-primary-content flex items-center justify-center h-full w-full text-4xl font-bold">
                                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <input type="file" name="avatar" id="avatarInput" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp" />
                        </div>
                        <div class="flex flex-col items-center mt-4">
                            <label class="label">
                                <span class="label-text-alt">Click avatar to change. Max 2MB. JPG, PNG, GIF, WebP</span>
                            </label>
                        </div>
                    </div>

                    <?php
                    $inputName = 'name';
                    $inputLabel = 'Name';
                    $inputType = 'text';
                    $inputPlaceholder = 'Your name';
                    $inputValue = $_SESSION['user']['name'] ?? '';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

                    <div class="form-control mt-6">
                        <button type="submit" class="btn btn-primary w-full">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Email Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Change Email</h2>
                
                <form method="POST" class="space-y-4" id="changeEmailForm">
                    <input type="hidden" name="action" value="change_email">
                    <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                    
                    <?php
                    $inputName = 'new_email';
                    $inputLabel = 'New Email';
                    $inputType = 'email';
                    $inputPlaceholder = 'new@email.com';
                    $inputValue = $_SESSION['user']['email'] ?? '';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

                    <?php
                    $inputName = 'email_password';
                    $inputLabel = 'Current Password';
                    $inputType = 'password';
                    $inputPlaceholder = '••••••••';
                    $inputRequired = true;
                    $inputTogglePassword = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>
                    
                    <div class="form-control mt-6">
                        <button type="submit" class="btn btn-primary">Update Email</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <?php if ($hasPassword): ?>
                    <h2 class="card-title text-xl mb-4">Change Password</h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                        <?php
                        $inputName = 'current_password';
                        $inputLabel = 'Current Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>

                        <?php
                        $inputName = 'new_password';
                        $inputLabel = 'New Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>

                        <?php
                        $inputName = 'confirm_password';
                        $inputLabel = 'Confirm Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>

                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h2 class="card-title text-xl mb-4">Add Password</h2>
                    <p class="text-sm opacity-70 mb-4">Set a password to enable email login</p>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_password">
                        <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                        
                        <?php
                        $inputName = 'new_password';
                        $inputLabel = 'Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>

                        <?php
                        $inputName = 'confirm_password';
                        $inputLabel = 'Confirm Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>

                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">Add Password</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card bg-base-100 shadow-xl mt-6 border border-error">
        <div class="card-body">
            <h2 class="card-title text-xl mb-4 text-error">Danger Zone</h2>

            <div class="flex flex-col gap-4">
                <div>
                    <div class="font-semibold">Delete Account</div>
                    <div class="text-sm text-base-content/70">
                        Soft-deletes your account. You cannot register again with the same email until the
                        scheduled purge removes it (every 7 days).
                    </div>
                </div>

                <?php if ($hasPassword): ?>
                <form method="POST"
                      class="flex flex-col gap-3 sm:flex-row sm:items-end"
                      data-confirm="Delete your account? You will be signed out. This email stays reserved until purge."
                      data-confirm-title="Delete Account"
                      data-confirm-text="Delete Account"
                      data-confirm-class="btn-error">
                    <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                    <input type="hidden" name="action" value="delete_account">
                    <div class="form-control w-full sm:max-w-xs">
                        <?php
                        $inputName = 'delete_password';
                        $inputLabel = 'Confirm with password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>
                    </div>
                    <button type="submit" class="btn btn-error btn-outline w-full sm:w-auto">
                        Delete Account
                    </button>
                </form>
                <?php else: ?>
                <div class="text-sm text-base-content/70">
                    Set a password before you can delete your account.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const eyeOpen = this.querySelector('.eye-open');
        const eyeClosed = this.querySelector('.eye-closed');
        
        if (input.type === 'password') {
            input.type = 'text';
            eyeOpen.classList.add('hidden');
            eyeClosed.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeOpen.classList.remove('hidden');
            eyeClosed.classList.add('hidden');
        }
    });
});

// Avatar preview
const avatarInput = document.getElementById('avatarInput');
if (avatarInput) {
    avatarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarContainer = document.querySelector('label[for="avatarInput"] .rounded-full');
                if (avatarContainer) {
                    avatarContainer.innerHTML = `<img src="${e.target.result}" alt="Avatar preview" />`;
                }
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
