<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\CsrfService;
use App\Services\EmailService;
use App\Services\ImageUploadService;
use App\Services\PasswordPolicyService;
use App\Services\SessionService;
use App\Services\UserAccountService;

class SettingsController
{
    private UserAccountService $accounts;
    private EmailService $emailService;
    private ActivityLogService $activityLog;
    private string $webRoot;

    public function __construct(
        ?UserAccountService $accounts = null,
        ?EmailService $emailService = null,
        ?ActivityLogService $activityLog = null,
        ?string $webRoot = null
    ) {
        $this->accounts = $accounts ?? new UserAccountService();
        $this->emailService = $emailService ?? new EmailService();
        $this->activityLog = $activityLog ?? new ActivityLogService();
        $this->webRoot = $webRoot ?? dirname(__DIR__, 2); // web/
    }

    public function getActiveUser(int $userId): ?array
    {
        return $this->accounts->findActiveById($userId);
    }

    /**
     * Handle a settings POST action.
     *
     * @return array{
     *   flash?: array{error?: string, success?: string},
     *   redirect: string,
     *   logout?: bool
     * }
     */
    public function handle(string $action, array $post, array $files, array $userData, array $sessionUser): array
    {
        if (!CsrfService::validateToken($post['csrf_token'] ?? null)) {
            return [
                'flash' => ['error' => 'Invalid security token. Please try again.'],
                'redirect' => '/settings',
            ];
        }

        $userId = (int)$sessionUser['id'];
        $userName = $sessionUser['name'] ?? 'Unknown';

        return match ($action) {
            'edit_profile' => $this->editProfile($post, $files, $userId, $userName, $sessionUser),
            'change_email' => $this->changeEmail($post, $userData, $userId, $userName, $sessionUser),
            'add_password' => $this->addPassword($post, $userId, $userName),
            'change_password' => $this->changePassword($post, $userData, $userId, $userName),
            'delete_account' => $this->deleteAccount($post, $userData, $userId, $userName),
            default => [
                'flash' => ['error' => 'Unknown action.'],
                'redirect' => '/settings',
            ],
        };
    }

    public function applyPendingEmailChange(array $pending, array $sessionUser): string
    {
        $newEmail = $pending['new_email'];
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $userId = (int)$sessionUser['id'];
        $userName = $sessionUser['name'] ?? 'Unknown';

        $this->accounts->changeEmail($userId, $newEmail, $token, $expiresAt);
        $this->emailService->sendVerificationEmail($newEmail, $sessionUser['name'] ?? '', $token);
        $_SESSION['user']['email'] = $newEmail;
        unset($_SESSION['pending_email_change']);

        $this->activityLog->log(
            'user.email.change',
            "User '$userName' changed email to '$newEmail'",
            $userId,
            'user',
            $userId,
            ['new_email' => $newEmail, 'via' => 'pending_email_change']
        );

        return 'Email updated. Please check your inbox to verify your new email address.';
    }

    private function editProfile(array $post, array $files, int $userId, string $userName, array $sessionUser): array
    {
        $name = trim($post['name'] ?? '');
        $errors = [];
        $successMessages = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Name must not exceed 255 characters';
        } elseif ($this->accounts->updateName($userId, $name)) {
            $_SESSION['user']['name'] = $name;
            $successMessages[] = 'Name updated';
        } else {
            $errors[] = 'Failed to update name';
        }

        $avatar = $files['avatar'] ?? null;
        if (is_array($avatar) && (int)($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = ImageUploadService::store(
                $avatar,
                $this->webRoot . '/uploads/avatars',
                'avatar_' . $userId
            );

            if (!$stored['success']) {
                $errors[] = $stored['error'];
            } else {
                $avatarUrl = '/uploads/avatars/' . $stored['filename'];
                $this->accounts->deleteAvatarFile($sessionUser['avatar_url'] ?? null, $this->webRoot);

                if ($this->accounts->updateAvatarUrl($userId, $avatarUrl)) {
                    $_SESSION['user']['avatar_url'] = $avatarUrl;
                    $successMessages[] = 'Avatar updated';
                    $this->activityLog->log(
                        'file.upload',
                        "Uploaded avatar '{$stored['filename']}'",
                        $userId,
                        'user',
                        $userId,
                        [
                            'filename' => $stored['filename'],
                            'mime' => $stored['mime'],
                            'size' => $stored['size'],
                            'path' => $avatarUrl,
                        ]
                    );
                } else {
                    @unlink($stored['path']);
                    $errors[] = 'Failed to update avatar';
                }
            }
        }

        $flash = [];
        if ($errors !== []) {
            $flash['error'] = implode('. ', $errors);
            if ($successMessages !== []) {
                $flash['error'] .= '. Also: ' . implode(' and ', $successMessages) . ' succeeded';
            }
        } elseif ($successMessages !== []) {
            $flash['success'] = implode(' and ', $successMessages) . ' successfully';
            $this->activityLog->log(
                'user.profile.update',
                "User '$userName' updated profile",
                $userId,
                'user',
                $userId,
                ['changes' => $successMessages]
            );
        }

        return ['flash' => $flash, 'redirect' => '/settings'];
    }

    private function changeEmail(array $post, array $userData, int $userId, string $userName, array $sessionUser): array
    {
        $newEmail = trim($post['new_email'] ?? '');

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return ['flash' => ['error' => 'Invalid email address'], 'redirect' => '/settings'];
        }

        if ($this->accounts->emailExists($newEmail)) {
            return ['flash' => ['error' => 'Email already in use'], 'redirect' => '/settings'];
        }

        $password = $post['email_password'] ?? '';
        if (empty($userData['password_hash'])) {
            return ['flash' => ['error' => 'Set a password before changing email'], 'redirect' => '/settings'];
        }

        if (!password_verify($password, $userData['password_hash'])) {
            $this->activityLog->log(
                'user.email.change_failed',
                "User '$userName' failed email change (bad password)",
                $userId,
                'user',
                $userId,
                ['reason' => 'bad_password', 'attempted_email' => $newEmail]
            );
            return ['flash' => ['error' => 'Invalid password'], 'redirect' => '/settings'];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->accounts->changeEmail($userId, $newEmail, $token, $expiresAt);
        $this->emailService->sendVerificationEmail($newEmail, $sessionUser['name'] ?? '', $token);
        $_SESSION['user']['email'] = $newEmail;

        $this->activityLog->log(
            'user.email.change',
            "User '$userName' changed email to '$newEmail'",
            $userId,
            'user',
            $userId,
            ['new_email' => $newEmail]
        );

        return [
            'flash' => ['success' => 'Email updated. Please check your inbox to verify your new email address.'],
            'redirect' => '/settings',
        ];
    }

    private function addPassword(array $post, int $userId, string $userName): array
    {
        $newPassword = $post['new_password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';

        if ($newPassword === '') {
            return ['flash' => ['error' => 'Password is required'], 'redirect' => '/settings'];
        }
        if ($policyError = PasswordPolicyService::validate($newPassword)) {
            return ['flash' => ['error' => $policyError], 'redirect' => '/settings'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['flash' => ['error' => 'Passwords do not match'], 'redirect' => '/settings'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        if (!$this->accounts->setPasswordHash($userId, $passwordHash)) {
            return ['flash' => ['error' => 'Failed to add password'], 'redirect' => '/settings'];
        }

        SessionService::regenerate();
        $_SESSION['user']['auth_stamp'] = SessionService::authStampFromHash($passwordHash);

        $this->activityLog->log(
            'user.password.add',
            "User '$userName' added password",
            $userId,
            'user',
            $userId
        );

        return ['flash' => ['success' => 'Password added successfully'], 'redirect' => '/settings'];
    }

    private function changePassword(array $post, array $userData, int $userId, string $userName): array
    {
        $currentPassword = $post['current_password'] ?? '';
        $newPassword = $post['new_password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';

        if (empty($userData['password_hash'])) {
            return ['flash' => ['error' => 'No password set. Please add a password first.'], 'redirect' => '/settings'];
        }
        if ($currentPassword === '' || $newPassword === '') {
            return ['flash' => ['error' => 'Current and new password are required'], 'redirect' => '/settings'];
        }
        if (!password_verify($currentPassword, $userData['password_hash'])) {
            $this->activityLog->log(
                'user.password.change_failed',
                "User '$userName' failed password change (bad current password)",
                $userId,
                'user',
                $userId,
                ['reason' => 'bad_current_password']
            );
            return ['flash' => ['error' => 'Current password is incorrect'], 'redirect' => '/settings'];
        }
        if ($policyError = PasswordPolicyService::validate($newPassword)) {
            return ['flash' => ['error' => $policyError], 'redirect' => '/settings'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['flash' => ['error' => 'Passwords do not match'], 'redirect' => '/settings'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        if (!$this->accounts->setPasswordHash($userId, $passwordHash)) {
            return ['flash' => ['error' => 'Failed to update password'], 'redirect' => '/settings'];
        }

        SessionService::regenerate();
        $_SESSION['user']['auth_stamp'] = SessionService::authStampFromHash($passwordHash);

        $this->activityLog->log(
            'user.password.change',
            "User '$userName' changed password",
            $userId,
            'user',
            $userId
        );

        return ['flash' => ['success' => 'Password updated successfully'], 'redirect' => '/settings'];
    }

    private function deleteAccount(array $post, array $userData, int $userId, string $userName): array
    {
        $password = $post['delete_password'] ?? '';

        if (empty($userData['password_hash'])) {
            return ['flash' => ['error' => 'Set a password before deleting your account'], 'redirect' => '/settings'];
        }

        if ($password === '' || !password_verify($password, $userData['password_hash'])) {
            $this->activityLog->log(
                'user.account.delete_failed',
                "User '$userName' failed account deletion (bad password)",
                $userId,
                'user',
                $userId,
                ['reason' => 'bad_password']
            );
            return ['flash' => ['error' => 'Invalid password'], 'redirect' => '/settings'];
        }

        if (!$this->accounts->softDelete($userId)) {
            return ['flash' => ['error' => 'Failed to delete account'], 'redirect' => '/settings'];
        }

        $this->activityLog->log(
            'user.account.delete',
            "User '$userName' deleted their account",
            $userId,
            'user',
            $userId
        );

        SessionService::destroy();
        SessionService::start();
        $_SESSION['flash_success'] = 'Your account has been deleted. You can register again with this email after the retention period ends.';

        return [
            'redirect' => '/auth/login',
            'logout' => true,
        ];
    }
}
