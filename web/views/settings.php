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
$stmt = $db->prepare("SELECT google_id, password_hash, email FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $_SESSION['user']['id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

$hasGoogle = !empty($userData['google_id']);
$hasPassword = !empty($userData['password_hash']);
$currentGoogleEmail = $userData['email'];

$error = '';
$success = '';
$showEmailVerifyModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_email') {
        $newEmail = trim($_POST['new_email'] ?? '');
        
        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $stmt->bind_param('s', $newEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already in use';
            } else {
                $stmt->close();
                
                if ($hasPassword && $hasGoogle) {
                    $_SESSION['pending_email_change'] = ['new_email' => $newEmail];
                    $showEmailVerifyModal = true;
                } elseif ($hasPassword) {
                    $password = $_POST['email_password'] ?? '';
                    if (password_verify($password, $userData['password_hash'])) {
                        $token = bin2hex(random_bytes(32));
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        
                        $stmt = $db->prepare("UPDATE users SET email = ?, email_verified_at = NULL, verification_token = ?, verification_token_expires_at = ? WHERE id = ?");
                        $stmt->bind_param('sssi', $newEmail, $token, $expiresAt, $_SESSION['user']['id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        $emailService = new \App\Services\EmailService();
                        $emailService->sendVerificationEmail($newEmail, $_SESSION['user']['name'], $token);
                        
                        $_SESSION['user']['email'] = $newEmail;
                        $success = 'Email updated. Please check your inbox to verify your new email address.';
                    } else {
                        $error = 'Invalid password';
                    }
                } elseif ($hasGoogle) {
                    $_SESSION['pending_email_change'] = [
                        'new_email' => $newEmail,
                        'expected_google_id' => $userData['google_id']
                    ];
                    header('Location: /auth/google?source=settings_email');
                    exit;
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_email_with_password') {
        $password = $_POST['password'] ?? '';
        
        if (password_verify($password, $userData['password_hash'])) {
            $pendingData = $_SESSION['pending_email_change'] ?? null;
            if ($pendingData) {
                $newEmail = $pendingData['new_email'];
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $stmt = $db->prepare("UPDATE users SET email = ?, email_verified_at = NULL, verification_token = ?, verification_token_expires_at = ? WHERE id = ?");
                $stmt->bind_param('sssi', $newEmail, $token, $expiresAt, $_SESSION['user']['id']);
                $stmt->execute();
                $stmt->close();
                
                $emailService = new \App\Services\EmailService();
                $emailService->sendVerificationEmail($newEmail, $_SESSION['user']['name'], $token);
                
                $_SESSION['user']['email'] = $newEmail;
                unset($_SESSION['pending_email_change']);
                
                echo json_encode(['success' => true, 'message' => 'Email updated. Please check your inbox to verify your new email address.']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Invalid password']);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($newPassword)) {
            $error = 'Password is required';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $passwordHash, $_SESSION['user']['id']);
            
            if ($stmt->execute()) {
                $success = 'Password added successfully';
                $hasPassword = true;
            } else {
                $error = 'Failed to add password';
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['email_changed']) && $_GET['email_changed'] === '1') {
    $pendingData = $_SESSION['pending_email_change'] ?? null;
    if ($pendingData) {
        $newEmail = $pendingData['new_email'];
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $db->prepare("UPDATE users SET email = ?, email_verified_at = NULL, verification_token = ?, verification_token_expires_at = ? WHERE id = ?");
        $stmt->bind_param('sssi', $newEmail, $token, $expiresAt, $_SESSION['user']['id']);
        $stmt->execute();
        $stmt->close();
        
        $emailService = new \App\Services\EmailService();
        $emailService->sendVerificationEmail($newEmail, $_SESSION['user']['name'], $token);
        
        $_SESSION['user']['email'] = $newEmail;
        unset($_SESSION['pending_email_change']);
        
        $success = 'Email updated. Please check your inbox to verify your new email address.';
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <h1 class="text-3xl font-bold mb-8">Account Settings</h1>

    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-error mb-6">
        <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success mb-6">
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error mb-6">
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

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
                
                <form method="POST" class="space-y-4" id="changeEmailForm">
                    <input type="hidden" name="action" value="change_email">
                    
                    <?php
                    $inputName = 'new_email';
                    $inputLabel = 'New Email';
                    $inputType = 'email';
                    $inputPlaceholder = 'new@email.com';
                    $inputValue = $_SESSION['user']['email'] ?? '';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

                    <?php if ($hasPassword && !$hasGoogle): ?>
                        <?php
                        $inputName = 'email_password';
                        $inputLabel = 'Current Password';
                        $inputType = 'password';
                        $inputPlaceholder = '••••••••';
                        $inputRequired = true;
                        $inputTogglePassword = true;
                        include __DIR__ . '/../templates/components/input.php';
                        ?>
                        
                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">Update Email</button>
                        </div>
                    <?php elseif (!$hasPassword && $hasGoogle): ?>
                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">Update Email</button>
                        </div>
                    <?php else: ?>
                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">Update Email</button>
                        </div>
                    <?php endif; ?>
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
                        <input type="hidden" name="action" value="add_password">
                        
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

<?php if ($showEmailVerifyModal): ?>
<dialog id="emailVerifyModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Verify Identity</h3>
        <p class="py-4">Choose how to verify your identity to change email:</p>
        
        <div class="space-y-3">
            <button id="verifyWithPasswordBtn" class="btn btn-outline w-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Verify with Password
            </button>
            
            <button id="verifyWithGoogleBtn" class="btn btn-outline w-full gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                    <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                    <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                    <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                </svg>
                Verify with Google
            </button>
        </div>
        
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Cancel</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<dialog id="passwordVerifyModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">Enter Password</h3>
        <form id="passwordVerifyForm" class="py-4">
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Password</span>
                </label>
                <input type="password" name="password" id="verifyPasswordInput" class="input input-bordered" required autocomplete="current-password" />
            </div>
            <div class="modal-action">
                <button type="button" class="btn" id="cancelPasswordVerify">Cancel</button>
                <button type="submit" class="btn btn-primary">Verify</button>
            </div>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailVerifyModal = document.getElementById('emailVerifyModal');
    const passwordVerifyModal = document.getElementById('passwordVerifyModal');
    const verifyWithPasswordBtn = document.getElementById('verifyWithPasswordBtn');
    const verifyWithGoogleBtn = document.getElementById('verifyWithGoogleBtn');
    const passwordVerifyForm = document.getElementById('passwordVerifyForm');
    const cancelPasswordVerify = document.getElementById('cancelPasswordVerify');
    
    if (emailVerifyModal) {
        emailVerifyModal.showModal();
    }
    
    if (verifyWithPasswordBtn) {
        verifyWithPasswordBtn.addEventListener('click', function() {
            emailVerifyModal.close();
            passwordVerifyModal.showModal();
        });
    }
    
    if (verifyWithGoogleBtn) {
        verifyWithGoogleBtn.addEventListener('click', function() {
            window.location.href = '/auth/google?source=settings_email';
        });
    }
    
    if (cancelPasswordVerify) {
        cancelPasswordVerify.addEventListener('click', function() {
            passwordVerifyModal.close();
        });
    }
    
    if (passwordVerifyForm) {
        passwordVerifyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('verifyPasswordInput').value;
            const formData = new FormData();
            formData.append('action', 'verify_email_with_password');
            formData.append('password', password);
            
            try {
                const response = await fetch('/settings', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    window.location.href = '/settings';
                } else {
                    alert(result.error || 'Verification failed');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
