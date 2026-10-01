<?php
// Route: /auth/reset-password
require_once __DIR__ . '/../../vendor/autoload.php';

$title = 'Reset Password - Team Competition';

$error = '';
$success = '';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->resetPassword($_POST);

    if ($result['success']) {
        $success = $result['message'];
        $token = '';
    } else {
        $error = $result['error'] ?? 'Unable to reset password';
        $token = trim((string)($_POST['token'] ?? ''));
    }
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">Reset Password</h2>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <div class="text-center mt-4">
                <a href="/auth/login" class="btn btn-primary">Go to Login</a>
            </div>
            <?php elseif ($token === ''): ?>
            <div class="alert alert-error">
                <span>Reset token is missing or invalid.</span>
            </div>
            <div class="text-center mt-4">
                <a href="/auth/forgot-password" class="link link-primary">Request a new reset link</a>
            </div>
            <?php else: ?>
            <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <?php
                $inputName = 'password';
                $inputLabel = 'New Password';
                $inputType = 'password';
                $inputPlaceholder = '••••••••';
                $inputRequired = true;
                $inputTogglePassword = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>
                <p class="text-xs text-base-content/60 -mt-2">At least 12 characters, with a letter and a number.</p>

                <?php
                $inputName = 'password_confirmation';
                $inputLabel = 'Confirm Password';
                $inputType = 'password';
                $inputPlaceholder = '••••••••';
                $inputRequired = true;
                $inputTogglePassword = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <button type="submit" class="btn btn-primary w-full">Update Password</button>
            </form>
            <?php endif; ?>

            <div class="text-center mt-4">
                <p class="text-sm">
                    <a href="/auth/login" class="link link-primary">← Back to Login</a>
                </p>
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
include_once __DIR__ . '/../../templates/layout.php';
