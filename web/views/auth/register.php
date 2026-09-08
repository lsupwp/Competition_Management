<?php
// Route: /auth/register
require_once __DIR__ . '/../../vendor/autoload.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Register - Team Competition';

$error = '';
$success = '';
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new \App\Controllers\AuthController();
    $result = $authController->register($_POST);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['error'] ?? '';
        $old = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
        ];
    }
}

ob_start();
?>
<div class="min-h-[80vh] flex items-center justify-center py-8">
    <div class="card bg-base-100 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-4">Register</h2>

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

            <form method="POST" class="space-y-4" id="registerForm">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>

                <?php
                $inputName = 'name';
                $inputLabel = 'Name';
                $inputType = 'text';
                $inputPlaceholder = 'John Doe';
                $inputValue = $old['name'];
                $inputRequired = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <?php
                $inputName = 'email';
                $inputLabel = 'Email';
                $inputType = 'email';
                $inputPlaceholder = 'your@email.com';
                $inputValue = $old['email'];
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
                $inputName = 'password_confirmation';
                $inputLabel = 'Confirm Password';
                $inputType = 'password';
                $inputPlaceholder = '••••••••';
                $inputRequired = true;
                $inputTogglePassword = true;
                include __DIR__ . '/../../templates/components/input.php';
                ?>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="terms" class="checkbox checkbox-primary checkbox-sm" required />
                        <span class="label-text">
                            I agree to the
                            <a href="/terms" class="link link-primary">Terms of Service</a>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-full" id="submitBtn">
                    <span id="btnText">Register</span>
                    <span id="btnLoading" class="hidden">
                        <span class="loading loading-spinner"></span>
                        Sending...
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
                Register with Google
            </a>

            <div class="text-center mt-4">
                <p class="text-sm">
                    Already have an account?
                    <a href="/auth/login" class="link link-primary">Login</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');
    
    btn.disabled = true;
    btnText.classList.add('hidden');
    btnLoading.classList.remove('hidden');
});

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
