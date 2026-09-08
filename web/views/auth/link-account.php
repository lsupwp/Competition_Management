<?php
// Route: /auth/link-account
require_once __DIR__ . '/../../vendor/autoload.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Link Account - Team Competition';

$error = '';

session_start();

// Check if there's pending Google link data
$linkData = null;
if (isset($_SESSION['pending_google_link'])) {
    $linkData = $_SESSION['pending_google_link'];
} else {
    // No pending data, redirect to login
    header('Location: /auth/login');
    exit;
}

// Handle password verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->linkAccountWithPassword($_POST);
    
    if ($result['success']) {
        header('Location: ' . $result['redirect']);
        exit;
    } else {
        $error = $result['error'] ?? '';
    }
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">Link Account</h2>

            <div class="alert alert-info mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="font-bold">Account Already Exists</p>
                    <p class="text-sm">An account with email <strong><?= htmlspecialchars($linkData['email']) ?></strong> already exists. Please verify your identity to link your Google account.</p>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>

                <?php
                $inputName = 'password';
                $inputLabel = 'Enter your password to verify';
                $inputType = 'password';
                $inputPlaceholder = 'Your account password';
                $inputRequired = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <button type="submit" class="btn btn-primary w-full">
                    Verify & Link Account
                </button>
            </form>

            <div class="divider">OR</div>

            <a href="/auth/login" class="btn btn-outline w-full">Cancel</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
