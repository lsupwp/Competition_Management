<?php
// Route: /settings
require_once __DIR__ . '/../vendor/autoload.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = 'Settings - Team Competition';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$db = \App\Services\Database::getInstance();
$stmt = $db->prepare("SELECT password_hash, email FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $_SESSION['user']['id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    session_destroy();
    header('Location: /auth/login');
    exit;
}

$hasPassword = !empty($userData['password_hash']);

$error = '';
$success = '';

if (isset($_SESSION['settings_flash'])) {
    $flash = $_SESSION['settings_flash'];
    unset($_SESSION['settings_flash']);
    if (isset($flash['error'])) $error = $flash['error'];
    if (isset($flash['success'])) $success = $flash['success'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flashData = [];
    $activityLog = new \App\Services\ActivityLogService();
    $userId = $_SESSION['user']['id'];
    $userName = $_SESSION['user']['name'] ?? 'Unknown';

    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $flashData['error'] = 'Invalid security token. Please try again.';
        $_SESSION['settings_flash'] = $flashData;
        header('Location: /settings');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
        $name = sanitize_display_name($_POST['name'] ?? '');
        $errors = [];
        $successMessages = [];
        
        // Validate name
        if (empty($name)) {
            $errors[] = 'Name is required';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Name must not exceed 255 characters';
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $name, $_SESSION['user']['id']);
            
            if ($stmt->execute()) {
                $_SESSION['user']['name'] = $name;
                $successMessages[] = 'Name updated';
            } else {
                $errors[] = 'Failed to update name';
            }
            $stmt->close();
        }
        
        // Handle avatar upload if file provided
        if (isset($_FILES['avatar']) && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = \App\Services\ImageUploadService::store(
                $_FILES['avatar'],
                __DIR__ . '/../uploads/avatars',
                'avatar_' . $_SESSION['user']['id']
            );

            if (!$stored['success']) {
                $errors[] = $stored['error'];
            } else {
                $avatarUrl = '/uploads/avatars/' . $stored['filename'];

                if (!empty($_SESSION['user']['avatar_url'])) {
                    $oldAvatarPath = safe_upload_path(
                        $_SESSION['user']['avatar_url'],
                        __DIR__ . '/../uploads'
                    );
                    if ($oldAvatarPath !== null && is_file($oldAvatarPath)) {
                        unlink($oldAvatarPath);
                    }
                }

                $stmt = $db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->bind_param('si', $avatarUrl, $_SESSION['user']['id']);

                if ($stmt->execute()) {
                    $_SESSION['user']['avatar_url'] = $avatarUrl;
                    $successMessages[] = 'Avatar updated';
                    $activityLog->log(
                        'file.upload',
                        "Uploaded avatar '{$stored['filename']}'",
                        $userId,
                        'user',
                        $userId,
                        ['filename' => $stored['filename'], 'mime' => $stored['mime'], 'size' => $stored['size'], 'path' => $avatarUrl]
                    );
                } else {
                    @unlink($stored['path']);
                    $errors[] = 'Failed to update avatar';
                }
                $stmt->close();
            }
        }
        
        if (!empty($errors)) {
            $flashData['error'] = implode('. ', $errors);
            if (!empty($successMessages)) {
                $flashData['error'] .= '. Also: ' . implode(' and ', $successMessages) . ' succeeded';
            }
        } elseif (!empty($successMessages)) {
            $flashData['success'] = implode(' and ', $successMessages) . ' successfully';
            
            // Log profile update
            $activityLog->log(
                'user.profile.update',
                "User '$userName' updated profile",
                $userId,
                'user',
                $userId,
                ['changes' => $successMessages]
            );
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_email') {
        $newEmail = trim($_POST['new_email'] ?? '');
        
        if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $flashData['error'] = 'Invalid email address';
        } else {
            $stmt = $db->prepare("
                SELECT id FROM users
                WHERE deleted_at IS NULL
                  AND (email = ? OR pending_email = ?)
                  AND id != ?
                LIMIT 1
            ");
            $uid = (int)$_SESSION['user']['id'];
            $stmt->bind_param('ssi', $newEmail, $newEmail, $uid);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $flashData['error'] = 'Unable to change email. Please try a different address.';
            } else {
                $stmt->close();
                
                $password = $_POST['email_password'] ?? '';
                if (empty($userData['password_hash'])) {
                    $flashData['error'] = 'Set a password before changing email';
                } elseif (password_verify($password, $userData['password_hash'])) {
                    if (strcasecmp($newEmail, (string)$userData['email']) === 0) {
                        $flashData['error'] = 'That is already your current email';
                    } else {
                        $token = bin2hex(random_bytes(32));
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

                        // Keep current email verified until the new address confirms
                        $stmt = $db->prepare("
                            UPDATE users
                            SET pending_email = ?,
                                verification_token = ?,
                                verification_token_expires_at = ?
                            WHERE id = ?
                        ");
                        $stmt->bind_param('sssi', $newEmail, $token, $expiresAt, $_SESSION['user']['id']);
                        $stmt->execute();
                        $stmt->close();

                        $emailService = new \App\Services\EmailService();
                        $emailService->sendVerificationEmail($newEmail, $_SESSION['user']['name'], $token);

                        $flashData['success'] = 'Check your new inbox to confirm the email change. Your current email stays active until then.';

                        $activityLog->log(
                            'user.email.change_requested',
                            "User '$userName' requested email change to '$newEmail'",
                            $userId,
                            'user',
                            $userId,
                            ['pending_email' => $newEmail]
                        );
                    }
                } else {
                    $flashData['error'] = 'Invalid password';
                    $activityLog->log(
                        'user.email.change_failed',
                        "User '$userName' failed email change (bad password)",
                        $userId,
                        'user',
                        $userId,
                        ['reason' => 'bad_password', 'attempted_email' => $newEmail]
                    );
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($hasPassword) {
            $flashData['error'] = 'Password already set. Use change password instead.';
        } elseif (empty($newPassword)) {
            $flashData['error'] = 'Password is required';
        } elseif ($policyError = \App\Services\PasswordPolicyService::validate($newPassword)) {
            $flashData['error'] = $policyError;
        } elseif ($newPassword !== $confirmPassword) {
            $flashData['error'] = 'Passwords do not match';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);

            $stmt = $db->prepare("
                UPDATE users
                SET password_hash = ?
                WHERE id = ?
                  AND deleted_at IS NULL
                  AND (password_hash IS NULL OR password_hash = '')
            ");
            $stmt->bind_param('si', $passwordHash, $_SESSION['user']['id']);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $flashData['success'] = 'Password added successfully';
                $hasPassword = true;
                \App\Services\SessionService::regenerate();
                $_SESSION['user']['auth_stamp'] = \App\Services\SessionService::authStampFromHash($passwordHash);

                $activityLog->log(
                    'user.password.add',
                    "User '$userName' added password",
                    $userId,
                    'user',
                    $userId
                );
            } else {
                $flashData['error'] = 'Password already set. Use change password instead.';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($userData['password_hash'])) {
            $flashData['error'] = 'No password set. Please add a password first.';
        } elseif (empty($currentPassword) || empty($newPassword)) {
            $flashData['error'] = 'Current and new password are required';
        } elseif (!password_verify($currentPassword, $userData['password_hash'])) {
            $flashData['error'] = 'Current password is incorrect';
            $activityLog->log(
                'user.password.change_failed',
                "User '$userName' failed password change (bad current password)",
                $userId,
                'user',
                $userId,
                ['reason' => 'bad_current_password']
            );
        } elseif ($policyError = \App\Services\PasswordPolicyService::validate($newPassword)) {
            $flashData['error'] = $policyError;
        } elseif ($newPassword !== $confirmPassword) {
            $flashData['error'] = 'Passwords do not match';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $passwordHash, $_SESSION['user']['id']);

            if ($stmt->execute()) {
                $flashData['success'] = 'Password updated successfully';
                \App\Services\SessionService::regenerate();
                $_SESSION['user']['auth_stamp'] = \App\Services\SessionService::authStampFromHash($passwordHash);
                $activityLog->log(
                    'user.password.change',
                    "User '$userName' changed password",
                    $userId,
                    'user',
                    $userId
                );
            } else {
                $flashData['error'] = 'Failed to update password';
            }
            $stmt->close();
        }
    }

    $_SESSION['settings_flash'] = $flashData;
    header('Location: /settings');
    exit;
}

if (isset($_GET['email_changed']) && $_GET['email_changed'] === '1') {
    // Legacy OAuth-style callback removed: never swap email before verification.
    unset($_SESSION['pending_email_change']);
    $success = 'To change your email, use the form below. Confirm via the link sent to the new address.';
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
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="edit_profile">
                    <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Avatar</span>
                        </label>
                        <div class="flex flex-col items-center gap-4">
                            <label for="avatarInput" class="avatar cursor-pointer hover:opacity-80 transition-opacity">
                                <div class="w-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                    <?php if (!empty($_SESSION['user']['avatar_url'])): ?>
                                    <img src="<?= safe_upload_url($_SESSION['user']['avatar_url'] ?? '') ?>" alt="Avatar" />
                                    <?php else: ?>
                                    <div class="bg-primary text-primary-content flex items-center justify-center h-full w-full text-4xl font-bold">
                                        <?= h(strtoupper(substr((string)($_SESSION['user']['name'] ?? 'U'), 0, 1))) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <input type="file" name="avatar" id="avatarInput" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp" />
                        </div>
                        <div class="flex flex-col items-center mt-4">
                            <label class="label">
                                <span class="label-text-alt">Click avatar to change. Max 2MB. JPG, PNG, GIF, WebP</span>
                            </label>
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
                        <button type="submit" class="btn btn-primary w-full">Save Changes</button>
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
                    <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                    
                    <?php
                    $inputName = 'new_email';
                    $inputLabel = 'New Email';
                    $inputType = 'email';
                    $inputPlaceholder = 'new@email.com';
                    $inputValue = $_SESSION['user']['email'] ?? '';
                    $inputRequired = true;
                    include __DIR__ . '/../templates/components/input.php';
                    ?>

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
                </form>
            </div>
        </div>

        <!-- Change Password Section -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <?php if ($hasPassword): ?>
                    <h2 class="card-title text-xl mb-4">Change Password</h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
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
                        <?php include __DIR__ . '/../templates/components/csrf.php'; ?>
                        
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

// Avatar preview
const avatarInput = document.getElementById('avatarInput');
if (avatarInput) {
    avatarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarContainer = document.querySelector('label[for="avatarInput"] .rounded-full');
                if (avatarContainer) {
                    avatarContainer.innerHTML = `<img src="${e.target.result}" alt="Avatar preview" />`;
                }
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
