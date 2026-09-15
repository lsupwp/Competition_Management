<?php
// Route: /auth/forgot-password
require_once __DIR__ . '/../../vendor/autoload.php';

$title = 'Forgot Password - Team Competition';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->forgotPassword($_POST);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['error'] ?? '';
    }
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">Forgot Password</h2>
            <p class="text-center text-sm text-base-content/70 mb-4">
                Enter your email to receive a password reset link
            </p>

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
                $btnText = 'Send Reset Link';
                $btnClass = 'btn-primary w-full';
                include __DIR__ . '/../../templates/components/button.php';
                ?>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm">
                    <a href="/auth/login" class="link link-primary">← Back to Login</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
