<?php
// Route: /auth/login
require_once __DIR__ . '/../../vendor/autoload.php';

$title = 'Login - Team Competition';

$error = '';
$success = '';

session_start();

// Check for flash messages from Google callback
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $authController = new \App\Controllers\AuthController();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $result = $authController->login($_POST);
            echo json_encode($result);
            exit;
        } elseif ($_POST['action'] === 'quick_register') {
            $result = $authController->quickRegister($_POST);
            echo json_encode($result);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
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

            <form id="loginForm" class="space-y-4">
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
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="remember" class="checkbox checkbox-primary checkbox-sm" />
                        <span class="label-text">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-full" id="loginBtn">
                    <span id="loginBtnText">Login</span>
                    <span id="loginBtnLoading" class="hidden">
                        <span class="loading loading-spinner"></span>
                        Logging in...
                    </span>
                </button>
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

<!-- Create Account Modal -->
<dialog id="createAccountModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Account Not Found</h3>
        <p class="py-4">This email is not registered. Would you like to create an account?</p>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn" id="cancelCreateBtn">No</button>
            </form>
            <button class="btn btn-primary" id="confirmCreateBtn">
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
const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const loginBtnText = document.getElementById('loginBtnText');
const loginBtnLoading = document.getElementById('loginBtnLoading');
const createAccountModal = document.getElementById('createAccountModal');
const confirmCreateBtn = document.getElementById('confirmCreateBtn');
const createBtnText = document.getElementById('createBtnText');
const createBtnLoading = document.getElementById('createBtnLoading');

let pendingEmail = '';
let pendingPassword = '';
let csrfToken = '';

// Get CSRF token
csrfToken = document.querySelector('input[name="csrf_token"]').value;

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.querySelector('input[name="email"]').value;
    const password = document.querySelector('input[name="password"]').value;
    
    // Show loading
    loginBtn.disabled = true;
    loginBtnText.classList.add('hidden');
    loginBtnLoading.classList.remove('hidden');
    
    // Clear previous errors
    const errorAlert = document.querySelector('.alert-error');
    if (errorAlert) errorAlert.remove();
    
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'login');
        formData.append('email', email);
        formData.append('password', password);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('/auth/login', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect || '/';
        } else if (result.email_not_found) {
            // Show modal
            pendingEmail = email;
            pendingPassword = password;
            createAccountModal.showModal();
        } else {
            // Show error
            showError(result.error);
        }
    } catch (error) {
        showError('An error occurred. Please try again.');
    } finally {
        loginBtn.disabled = false;
        loginBtnText.classList.remove('hidden');
        loginBtnLoading.classList.add('hidden');
    }
});

confirmCreateBtn.addEventListener('click', async () => {
    // Show loading
    confirmCreateBtn.disabled = true;
    createBtnText.classList.add('hidden');
    createBtnLoading.classList.remove('hidden');
    
    try {
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'quick_register');
        formData.append('email', pendingEmail);
        formData.append('password', pendingPassword);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('/auth/login', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect || '/';
        } else {
            createAccountModal.close();
            showError(result.error);
        }
    } catch (error) {
        createAccountModal.close();
        showError('An error occurred. Please try again.');
    } finally {
        confirmCreateBtn.disabled = false;
        createBtnText.classList.remove('hidden');
        createBtnLoading.classList.add('hidden');
    }
});

function showError(message) {
    const cardBody = document.querySelector('.card-body');
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-error';
    alertDiv.innerHTML = `<span>${message}</span>`;
    cardBody.insertBefore(alertDiv, cardBody.firstChild);
}
</script>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
