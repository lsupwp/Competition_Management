<?php
// Route: /auth/login
require_once __DIR__ . '/../../vendor/autoload.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Login - Team Competition';

$error = '';
$success = '';

\App\Services\SessionService::start();

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->login($_POST);
    
    if ($result['success']) {
        $success = $result['message'] ?? '';
        if (!empty($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
    } else {
        $error = $result['error'] ?? '';
    }
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">Login</h2>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>

                <?php
                $inputName = 'email';
                $inputLabel = 'Email';
                $inputType = 'email';
                $inputPlaceholder = 'your@email.com';
                $inputRequired = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <?php
                $inputName = 'password';
                $inputLabel = 'Password';
                $inputType = 'password';
                $inputPlaceholder = '••••••••';
                $inputRequired = true;
                $inputTogglePassword = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <?php
                $btnText = 'Login';
                $btnClass = 'btn-primary w-full';
                include __DIR__ . '/../../templates/components/button.php';
                ?>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm">
                    Don't have an account?
                    <a href="/auth/register" class="link link-primary">Register</a>
                </p>
                <p class="text-sm mt-2">
                    <a href="/auth/forgot-password" class="link link-hover">Forgot password?</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
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
