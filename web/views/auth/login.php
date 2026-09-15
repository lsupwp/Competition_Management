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
$showGoogleSignupModal = false;
$pendingGoogleUser = null;

session_start();

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (isset($_GET['google_signup']) && $_GET['google_signup'] === '1') {
    if (isset($_SESSION['pending_google_user'])) {
        $showGoogleSignupModal = true;
        $pendingGoogleUser = $_SESSION['pending_google_user'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_google_account') {
    header('Content-Type: application/json');
    $authController = new \App\Controllers\AuthController();
    $result = $authController->createAccountFromGoogle();
    echo json_encode($result);
    exit;
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

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="remember" class="checkbox checkbox-primary checkbox-sm" />
                        <span class="label-text">Remember me</span>
                    </label>
                </div>

                <?php
                $btnText = 'Login';
                $btnClass = 'btn-primary w-full';
                include __DIR__ . '/../../templates/components/button.php';
                ?>
            </form>

            <div class="divider">OR</div>

            <a href="/auth/google" class="btn btn-outline w-full gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                    <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                    <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                    <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                </svg>
                Login with Google
            </a>

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

<?php if ($showGoogleSignupModal && $pendingGoogleUser): ?>
<dialog id="googleSignupModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Account Not Found</h3>
        <p class="py-4">
            No account found for <strong><?= htmlspecialchars($pendingGoogleUser['email']) ?></strong>.<br>
            Would you like to create an account with this Google account?
        </p>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn" id="cancelGoogleSignup">No, Cancel</button>
            </form>
            <button class="btn btn-primary" id="confirmGoogleSignup">
                <span id="createBtnText">Yes, Create Account</span>
                <span id="createBtnLoading" class="hidden">
                    <span class="loading loading-spinner loading-sm"></span>
                    Creating...
                </span>
            </button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('googleSignupModal');
    const confirmBtn = document.getElementById('confirmGoogleSignup');
    const cancelBtn = document.getElementById('cancelGoogleSignup');
    const createBtnText = document.getElementById('createBtnText');
    const createBtnLoading = document.getElementById('createBtnLoading');
    
    if (modal) {
        modal.showModal();
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            window.location.href = '/auth/login';
        });
    }
    
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            confirmBtn.disabled = true;
            createBtnText.classList.add('hidden');
            createBtnLoading.classList.remove('hidden');
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_google_account');
                
                const response = await fetch('/auth/login', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = result.redirect || '/';
                } else {
                    alert(result.error || 'Failed to create account');
                    window.location.href = '/auth/login';
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                window.location.href = '/auth/login';
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
