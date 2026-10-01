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
