<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\EmailService;
use App\Services\CsrfService;
use App\Services\ActivityLogService;
use App\Services\PasswordPolicyService;
use App\Services\LoginRateLimiter;

class AuthController
{
    private $db;
    private $emailService;
    private $activityLog;
    private $loginRateLimiter;

    /** Dummy hash for timing-safe failed logins when user is missing */
    private static ?string $dummyPasswordHash = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
        $this->activityLog = new ActivityLogService();
        $this->loginRateLimiter = new LoginRateLimiter();
    }

    public function register(array $data): array
    {
        if (!CsrfService::validateToken($data['csrf_token'] ?? null)) {
            return [
                'success' => false,
                'error' => 'Invalid or expired token. Please try again.'
            ];
        }

        $validationError = $this->validate($data);
        if ($validationError !== null) {
            return ['success' => false, 'error' => $validationError];
        }

        $email = trim($data['email']);
        $name = sanitize_display_name($data['name'] ?? '');
        $password = $data['password'];
        $genericMessage = 'If this email can be registered, you will receive a verification link shortly.';

        if ($name === '') {
            return ['success' => false, 'error' => 'Name is required'];
        }

        $existingUser = $this->findUserByEmail($email);

        // Already verified — do not reveal existence (SEC-03)
        if ($existingUser && $existingUser['email_verified_at'] !== null) {
            return [
                'success' => true,
                'message' => $genericMessage
            ];
        }

        // Someone has this address pending verification — do not hijack / race
        if ($this->findUserIdByPendingEmail($email) !== null) {
            return [
                'success' => true,
                'message' => $genericMessage
            ];
        }

        if ($existingUser) {
            // Never overwrite an account that already owns teams/events
            if ($this->userHasOwnedData((int)$existingUser['id'])) {
                return [
                    'success' => true,
                    'message' => $genericMessage
                ];
            }
            $this->updateUnverifiedUser($existingUser['id'], $name, $password);
            $userId = $existingUser['id'];
        } else {
            $userId = $this->createUser($email, $name, $password);
        }

        $token = $this->generateVerificationToken();
        $this->saveVerificationToken($userId, $token);

        $sent = $this->emailService->sendVerificationEmail($email, $name, $token);

        if (!$sent) {
            return [
                'success' => false,
                'error' => 'Unable to send verification email. Please try again.'
            ];
        }

        $this->activityLog->log(
            'auth.register',
            "New user registered: '$name'",
            $userId,
            'user',
            $userId,
            ['email' => $email, 'name' => $name]
        );

        return [
            'success' => true,
            'message' => $genericMessage
        ];
    }

    public function verify(string $token): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, pending_email
            FROM users 
            WHERE verification_token = ? 
            AND verification_token_expires_at > NOW()
            AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $this->activityLog->log(
                'auth.verify_failed',
                'Email verification failed: invalid or expired token',
                null,
                'user',
                null,
                ['reason' => 'invalid_token']
            );
            return [
                'success' => false,
                'message' => 'Verification link is invalid or has expired'
            ];
        }

        $userId = (int)$user['id'];
        $pendingEmail = trim((string)($user['pending_email'] ?? ''));
        $finalEmail = $user['email'];

        if ($pendingEmail !== '') {
            // Ensure the new address is still free
            $check = $this->db->prepare("
                SELECT id FROM users
                WHERE email = ? AND id != ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $check->bind_param('si', $pendingEmail, $userId);
            $check->execute();
            $taken = $check->get_result()->fetch_assoc();
            $check->close();

            if ($taken) {
                $clear = $this->db->prepare("
                    UPDATE users
                    SET pending_email = NULL,
                        verification_token = NULL,
                        verification_token_expires_at = NULL
                    WHERE id = ?
                ");
                $clear->bind_param('i', $userId);
                $clear->execute();
                $clear->close();

                return [
                    'success' => false,
                    'message' => 'That email address is no longer available. Please request a new email change.'
                ];
            }

            $updateStmt = $this->db->prepare("
                UPDATE users
                SET email = ?,
                    pending_email = NULL,
                    email_verified_at = NOW(),
                    verification_token = NULL,
                    verification_token_expires_at = NULL
                WHERE id = ?
            ");
            $updateStmt->bind_param('si', $pendingEmail, $userId);
            $updateStmt->execute();
            $updateStmt->close();
            $finalEmail = $pendingEmail;

            if (isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === $userId) {
                $_SESSION['user']['email'] = $pendingEmail;
            }
        } else {
            $updateStmt = $this->db->prepare("
                UPDATE users 
                SET email_verified_at = NOW(), 
                    verification_token = NULL, 
                    verification_token_expires_at = NULL 
                WHERE id = ?
            ");
            $updateStmt->bind_param('i', $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        $this->activityLog->log(
            'auth.verify',
            "User '{$user['name']}' verified email",
            $userId,
            'user',
            $userId,
            ['email' => $finalEmail, 'via_pending' => $pendingEmail !== '']
        );

        return [
            'success' => true,
            'message' => 'Email verified successfully. You can now login.'
        ];
    }

    public function login(array $data): array
    {
        if (!CsrfService::validateToken($data['csrf_token'] ?? null)) {
            return [
                'success' => false,
                'error' => 'Invalid or expired token. Please try again.'
            ];
        }

        if (empty($data['email'])) {
            return ['success' => false, 'error' => 'Email is required'];
        }

        if (empty($data['password'])) {
            return ['success' => false, 'error' => 'Password is required'];
        }

        $email = trim($data['email']);
        $password = $data['password'];
        $clientIp = ActivityLogService::resolveClientIp();

        if ($this->loginRateLimiter->tooManyAttempts($clientIp, $email)) {
            $this->activityLog->log(
                'auth.login_rate_limited',
                'Login blocked by rate limiter',
                null,
                'user',
                null,
                ['email' => $email, 'ip' => $clientIp]
            );
            return [
                'success' => false,
                'error' => $this->loginRateLimiter->retryAfterMessage()
            ];
        }

        $stmt = $this->db->prepare("
            SELECT id, email, name, password_hash, email_verified_at, role, avatar_url
            FROM users 
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $hashToCheck = (!empty($user['password_hash']))
            ? $user['password_hash']
            : $this->dummyPasswordHash();

        $passwordOk = password_verify($password, $hashToCheck);

        if (!$user) {
            $this->activityLog->log(
                'auth.login_failed',
                'Failed login attempt for unknown email',
                null,
                'user',
                null,
                ['email' => $email, 'reason' => 'user_not_found']
            );
            return [
                'success' => false,
                'error' => 'Invalid email or password'
            ];
        }

        if (empty($user['password_hash']) || !$passwordOk) {
            $this->activityLog->log(
                'auth.login_failed',
                "Failed login attempt for '{$user['email']}'",
                (int)$user['id'],
                'user',
                (int)$user['id'],
                ['email' => $user['email'], 'reason' => empty($user['password_hash']) ? 'no_password' : 'bad_password']
            );
            return [
                'success' => false,
                'error' => 'Invalid email or password'
            ];
        }

        if ($user['email_verified_at'] === null) {
            $this->activityLog->log(
                'auth.login_failed',
                "Failed login attempt for unverified email '{$user['email']}'",
                (int)$user['id'],
                'user',
                (int)$user['id'],
                ['email' => $user['email'], 'reason' => 'unverified']
            );
            return [
                'success' => false,
                'error' => 'Please verify your email before logging in'
            ];
        }

        \App\Services\SessionService::start();
        \App\Services\SessionService::regenerate();

        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar_url' => $user['avatar_url'] ?? null,
            'role' => $user['role'] ?? 'user',
            'auth_stamp' => \App\Services\SessionService::authStampFromHash($user['password_hash'] ?? null),
        ];
        $_SESSION['last_activity'] = time();

        $this->activityLog->log(
            'auth.login',
            "User '{$user['name']}' logged in",
            $user['id'],
            'user',
            $user['id'],
            ['email' => $user['email']]
        );

        return [
            'success' => true,
            'message' => 'Login successful',
            'redirect' => '/'
        ];
    }

    public function forgotPassword(array $data): array
    {
        if (!CsrfService::validateToken($data['csrf_token'] ?? null)) {
            return [
                'success' => false,
                'error' => 'Invalid or expired token. Please try again.'
            ];
        }

        if (empty($data['email'])) {
            return [
                'success' => false,
                'error' => 'Email is required'
            ];
        }

        $email = trim($data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid email format'
            ];
        }

        $clientIp = ActivityLogService::resolveClientIp();
        if ($this->loginRateLimiter->tooManyPasswordResetAttempts($clientIp, $email)) {
            return [
                'success' => false,
                'error' => $this->loginRateLimiter->passwordResetRetryAfterMessage()
            ];
        }

        $existing = $this->findUserByEmail($email);
        $this->activityLog->log(
            'auth.password_reset_request',
            'Password reset requested',
            $existing ? (int)$existing['id'] : null,
            'user',
            $existing ? (int)$existing['id'] : null,
            ['email' => $email, 'user_found' => (bool)$existing]
        );

        $generic = 'If your email is registered, you will receive a password reset link.';

        // Only rotate token if none is still valid (avoid recovery DoS)
        if ($existing && $existing['email_verified_at'] !== null) {
            $userId = (int)$existing['id'];
            $token = null;

            if (!empty($existing['password_reset_token'])
                && !empty($existing['password_reset_token_expires_at'])
                && strtotime((string)$existing['password_reset_token_expires_at']) > time()
            ) {
                $token = $existing['password_reset_token'];
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $this->db->prepare("
                    UPDATE users
                    SET password_reset_token = ?, password_reset_token_expires_at = ?
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->bind_param('ssi', $token, $expiresAt, $userId);
                $stmt->execute();
                $stmt->close();
            }

            $sent = $this->emailService->sendPasswordResetEmail($email, $existing['name'], $token);
            if (!$sent) {
                error_log('Password reset email failed for user #' . $userId);
            }
        }

        return [
            'success' => true,
            'message' => $generic
        ];
    }

    /**
     * Whether a password-reset token is present and not expired.
     */
    public function isValidPasswordResetToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE password_reset_token = ?
              AND password_reset_token_expires_at > NOW()
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row !== null;
    }

    public function resetPassword(array $data): array
    {
        if (!CsrfService::validateToken($data['csrf_token'] ?? null)) {
            return [
                'success' => false,
                'error' => 'Invalid or expired token. Please try again.'
            ];
        }

        $token = trim((string)($data['token'] ?? ''));
        if ($token === '') {
            return ['success' => false, 'error' => 'Reset token is required'];
        }

        $password = (string)($data['password'] ?? '');
        $confirm = (string)($data['password_confirmation'] ?? '');

        $passwordError = PasswordPolicyService::validate($password);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
        }
        if ($password !== $confirm) {
            return ['success' => false, 'error' => 'Passwords do not match'];
        }

        $stmt = $this->db->prepare("
            SELECT id, email, name
            FROM users
            WHERE password_reset_token = ?
              AND password_reset_token_expires_at > NOW()
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $this->activityLog->log(
                'auth.password_reset_failed',
                'Password reset failed: invalid or expired token',
                null,
                'user',
                null,
                ['reason' => 'invalid_token']
            );
            return [
                'success' => false,
                'error' => 'Reset link is invalid or has expired'
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $userId = (int)$user['id'];
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = ?,
                password_reset_token = NULL,
                password_reset_token_expires_at = NULL
            WHERE id = ?
        ");
        $stmt->bind_param('si', $passwordHash, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'error' => 'Failed to update password. Please try again.'];
        }
        $stmt->close();

        $this->activityLog->log(
            'auth.password_reset',
            "User '{$user['name']}' reset password",
            $userId,
            'user',
            $userId,
            ['email' => $user['email']]
        );

        return [
            'success' => true,
            'message' => 'Password updated successfully. You can now log in.'
        ];
    }

    private function validate(array $data): ?string
    {
        if (empty($data['name'])) {
            return 'Name is required';
        }

        if (mb_strlen($data['name']) > 255) {
            return 'Name must not exceed 255 characters';
        }

        if (empty($data['email'])) {
            return 'Email is required';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }

        if (empty($data['password'])) {
            return 'Password is required';
        }

        $passwordError = PasswordPolicyService::validate($data['password']);
        if ($passwordError !== null) {
            return $passwordError;
        }

        if (empty($data['password_confirmation'])) {
            return 'Password confirmation is required';
        }

        if ($data['password'] !== $data['password_confirmation']) {
            return 'Passwords do not match';
        }

        return null;
    }

    private function dummyPasswordHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            self::$dummyPasswordHash = password_hash('dummy-password-for-timing', PASSWORD_ARGON2ID);
        }
        return self::$dummyPasswordHash;
    }

    private function findUserIdByPendingEmail(string $email): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM users
            WHERE pending_email = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }

    private function userHasOwnedData(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT
                EXISTS(SELECT 1 FROM teams WHERE owner_id = ? AND deleted_at IS NULL) AS has_team,
                EXISTS(SELECT 1 FROM events WHERE created_by = ? AND deleted_at IS NULL) AS has_event,
                EXISTS(
                    SELECT 1 FROM team_members
                    WHERE user_id = ? AND deleted_at IS NULL
                ) AS has_membership
        ");
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row['has_team']) || !empty($row['has_event']) || !empty($row['has_membership']);
    }

    private function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, email_verified_at,
                   password_reset_token, password_reset_token_expires_at
            FROM users 
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user;
    }

    private function createUser(string $email, string $name, string $password): int
    {
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $name = sanitize_display_name($name);

        $stmt = $this->db->prepare("
            INSERT INTO users (email, name, password_hash) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sss', $email, $name, $passwordHash);
        $stmt->execute();
        $userId = $this->db->insert_id;
        $stmt->close();

        return $userId;
    }

    private function updateUnverifiedUser(int $userId, string $name, string $password): void
    {
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $name = sanitize_display_name($name);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET name = ?, password_hash = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $name, $passwordHash, $userId);
        $stmt->execute();
        $stmt->close();
    }

    private function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function saveVerificationToken(int $userId, string $token): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET verification_token = ?, verification_token_expires_at = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $token, $expiresAt, $userId);
        $stmt->execute();
        $stmt->close();
    }

    private function findUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, avatar_url, email_verified_at 
            FROM users 
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user;
    }
}
