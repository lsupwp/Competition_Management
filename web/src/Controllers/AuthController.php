<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\EmailService;
use App\Services\CsrfService;
use App\Services\GoogleAuthService;

class AuthController
{
    private $db;
    private $emailService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
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
        $name = trim($data['name']);
        $password = $data['password'];

        $existingUser = $this->findUserByEmail($email);

        if ($existingUser) {
            if ($existingUser['email_verified_at'] !== null) {
                return [
                    'success' => false,
                    'error' => 'This email is already in use'
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

        return [
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.'
        ];
    }

    public function verify(string $token): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email 
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
            return [
                'success' => false,
                'message' => 'Verification link is invalid or has expired'
            ];
        }

        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET email_verified_at = NOW(), 
                verification_token = NULL, 
                verification_token_expires_at = NULL 
            WHERE id = ?
        ");
        $updateStmt->bind_param('i', $user['id']);
        $updateStmt->execute();
        $updateStmt->close();

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

        $stmt = $this->db->prepare("
            SELECT id, email, name, password_hash, email_verified_at 
            FROM users 
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid email or password'
            ];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'error' => 'Invalid email or password'
            ];
        }

        if ($user['email_verified_at'] === null) {
            return [
                'success' => false,
                'error' => 'Please verify your email before logging in'
            ];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar_url' => $user['avatar_url'] ?? null,
        ];

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

        // TODO: Implement password reset logic
        return [
            'success' => true,
            'message' => 'If your email is registered, you will receive a password reset link.'
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

        if (mb_strlen($data['password']) < 8) {
            return 'Password must be at least 8 characters long';
        }

        if (empty($data['password_confirmation'])) {
            return 'Password confirmation is required';
        }

        if ($data['password'] !== $data['password_confirmation']) {
            return 'Passwords do not match';
        }

        return null;
    }

    private function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, email_verified_at 
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

    public function getGoogleAuthUrl(): string
    {
        $googleAuth = new GoogleAuthService();
        return $googleAuth->getAuthUrl();
    }

    public function handleGoogleCallback(string $code): array
    {
        $googleAuth = new GoogleAuthService();
        $userInfo = $googleAuth->authenticate($code);

        if (!$userInfo) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with Google'
            ];
        }

        if (!$userInfo['email_verified']) {
            return [
                'success' => false,
                'error' => 'Google email is not verified'
            ];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Step 1: Check if user exists with this google_id
        $user = $this->findUserByGoogleId($userInfo['google_id']);

        if ($user) {
            // User exists and linked with this google_id → Login success
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'avatar_url' => $user['avatar_url'] ?? $userInfo['avatar_url'],
            ];

            return [
                'success' => true,
                'redirect' => '/',
                'user_id' => $user['id'],
            ];
        }

        // Step 2: Check if user exists with this email
        $user = $this->findUserByEmail($userInfo['email']);

        if (!$user) {
            // No user found → Prompt account creation
            $_SESSION['pending_google_user'] = [
                'google_id' => $userInfo['google_id'],
                'email' => $userInfo['email'],
                'name' => $userInfo['name'],
                'avatar_url' => $userInfo['avatar_url'],
            ];

            return [
                'success' => false,
                'user_not_found' => true,
                'redirect' => '/auth/login',
                'user_info' => $userInfo,
            ];
        }

        // Step 3: User exists but not linked with google_id
        if ($user['email_verified_at'] === null) {
            // UNVERIFIED: Wipe old record, promote to VERIFIED with Google
            $this->promoteUnverifiedToGoogle($user['id'], $userInfo);

            // Get updated user
            $updatedUser = $this->findUserById($user['id']);

            $_SESSION['user'] = [
                'id' => $updatedUser['id'],
                'email' => $updatedUser['email'],
                'name' => $updatedUser['name'],
                'avatar_url' => $updatedUser['avatar_url'] ?? $userInfo['avatar_url'],
            ];

            return [
                'success' => true,
                'redirect' => '/'
            ];
        } else {
            // VERIFIED: Requires account linking
            $_SESSION['pending_google_link'] = [
                'google_id' => $userInfo['google_id'],
                'email' => $userInfo['email'],
                'name' => $userInfo['name'],
                'avatar_url' => $userInfo['avatar_url'],
                'existing_user_id' => $user['id'],
            ];

            return [
                'success' => false,
                'requires_linking' => true,
                'redirect' => '/auth/link-account'
            ];
        }
    }

    private function promoteUnverifiedToGoogle(int $userId, array $googleData): void
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET google_id = ?, 
                name = ?, 
                avatar_url = ?, 
                password_hash = NULL,
                email_verified_at = NOW(),
                verification_token = NULL,
                verification_token_expires_at = NULL
            WHERE id = ?
        ");
        $stmt->bind_param('sssi', 
            $googleData['google_id'],
            $googleData['name'],
            $googleData['avatar_url'],
            $userId
        );
        $stmt->execute();
        $stmt->close();
    }

    public function getLinkAccountData(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['pending_google_link'] ?? null;
    }

    public function linkAccountWithPassword(array $data): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['pending_google_link'])) {
            return [
                'success' => false,
                'error' => 'No pending Google link'
            ];
        }

        $pendingData = $_SESSION['pending_google_link'];
        $password = $data['password'] ?? '';

        // Get existing user
        $user = $this->findUserById($pendingData['existing_user_id']);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'error' => 'Invalid password'
            ];
        }

        // Link Google account
        $stmt = $this->db->prepare("
            UPDATE users 
            SET google_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param('si', $pendingData['google_id'], $user['id']);
        $stmt->execute();
        $stmt->close();

        // Set session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar_url' => $user['avatar_url'] ?? $pendingData['avatar_url'],
        ];

        // Clear pending data
        unset($_SESSION['pending_google_link']);

        return [
            'success' => true,
            'redirect' => '/'
        ];
    }

    public function createAccountFromGoogle(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['pending_google_user'])) {
            return [
                'success' => false,
                'error' => 'No pending Google account'
            ];
        }

        $googleData = $_SESSION['pending_google_user'];

        // Create user
        $stmt = $this->db->prepare("
            INSERT INTO users (email, name, google_id, avatar_url, email_verified_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('ssss', 
            $googleData['email'], 
            $googleData['name'], 
            $googleData['google_id'], 
            $googleData['avatar_url']
        );
        $stmt->execute();
        $userId = $this->db->insert_id;
        $stmt->close();

        // Get created user
        $user = $this->findUserById($userId);

        // Set session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar_url' => $user['avatar_url'],
        ];

        // Clear pending data
        unset($_SESSION['pending_google_user']);

        return [
            'success' => true,
            'redirect' => '/'
        ];
    }

    private function findUserByGoogleId(string $googleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, avatar_url, email_verified_at 
            FROM users 
            WHERE google_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $googleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user;
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

    private function linkGoogleAccount(int $userId, string $googleId, ?string $avatarUrl): void
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET google_id = ?, avatar_url = COALESCE(?, avatar_url), email_verified_at = COALESCE(email_verified_at, NOW())
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $googleId, $avatarUrl, $userId);
        $stmt->execute();
        $stmt->close();
    }

    private function createGoogleUser(array $userInfo): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (email, name, google_id, avatar_url, email_verified_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('ssss', 
            $userInfo['email'], 
            $userInfo['name'], 
            $userInfo['google_id'], 
            $userInfo['avatar_url']
        );
        $stmt->execute();
        $userId = $this->db->insert_id;
        $stmt->close();

        return $userId;
    }
}
