<?php
// Route: /auth/verify
require_once __DIR__ . '/../../vendor/autoload.php';

$title = 'Verify Email - Team Competition';

$success = false;
$message = '';

if (isset($_GET['token'])) {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->verify($_GET['token']);
    
    $success = $result['success'];
    $message = $result['message'];
} else {
    $message = 'Verification token not found';
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body text-center">
            <?php if ($success): ?>
            <div class="flex justify-center mb-4">
                <div class="bg-success/10 rounded-full p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <h2 class="card-title text-2xl font-bold justify-center text-success">Email Verified Successfully!</h2>
            <?php else: ?>
            <div class="flex justify-center mb-4">
                <div class="bg-error/10 rounded-full p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
            <h2 class="card-title text-2xl font-bold justify-center text-error">Email Verification Failed</h2>
            <?php endif; ?>
            
            <p class="mt-4 text-base-content/70"><?= htmlspecialchars($message) ?></p>
            
            <div class="card-actions justify-center mt-6">
                <?php if ($success): ?>
                <a href="/auth/login" class="btn btn-primary">Login</a>
                <?php else: ?>
                <a href="/auth/register" class="btn btn-primary">Try Again</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
