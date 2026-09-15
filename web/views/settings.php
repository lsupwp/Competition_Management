<?php
// Route: /settings
require_once __DIR__ . '/../vendor/autoload.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Settings - Team Competition';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$db = \App\Services\Database::getInstance();
$stmt = $db->prepare("SELECT google_id, password_hash FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $_SESSION['user']['id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

$hasGoogle = !empty($userData['google_id']);
$hasPassword = !empty($userData['password_hash']);

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <h1 class="text-3xl font-bold mb-8">Account Settings</h1>

    <div class="space-y-6">
        <!-- Edit Profile Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Edit Profile</h2>
                
                <form method="POST" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Avatar</span>
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="avatar">
                                <div class="w-16 rounded-full">
                                    <?php if (!empty($_SESSION['user']['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($_SESSION['user']['avatar_url']) ?>" alt="Avatar" />
                                    <?php else: ?>
                                    <div class="bg-primary text-primary-content flex items-center justify-center h-full w-full text-2xl font-bold">
                                        <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm">Change Avatar</button>
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
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Email Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Change Email</h2>
                
                <form method="POST" class="space-y-4">
                    <?php
                    $inputName = 'current_email';
                    $inputLabel = 'Current Email';
                    $inputType = 'email';
                    $inputPlaceholder = 'your@email.com';
                    $inputValue = $_SESSION['user']['email'] ?? '';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

                    <?php
                    $inputName = 'new_email';
                    $inputLabel = 'New Email';
                    $inputType = 'email';
                    $inputPlaceholder = 'new@email.com';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

                    <?php
                    $inputName = 'email_password';
                    $inputLabel = 'Password';
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

        <!-- Google Account Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Google Account</h2>
                
                <div class="flex items-center justify-between">
                    <div>
                        <?php if ($hasGoogle): ?>
                            <p class="text-sm opacity-70">Your account is connected to Google</p>
                        <?php else: ?>
                            <p class="text-sm opacity-70">Connect your Google account for faster login</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasGoogle): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="unlink_google">
                            <button type="submit" class="btn btn-error btn-outline">Unlink Google Account</button>
                        </form>
                    <?php else: ?>
                        <a href="/auth/google" class="btn btn-outline gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                                <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                                <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                                <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                                <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                            </svg>
                            Connect Google Account
                        </a>
                    <?php endif; ?>
                </div>
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
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
