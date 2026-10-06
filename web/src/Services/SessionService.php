<?php

namespace App\Services;

final class SessionService
{
    /** Idle timeout in seconds (30 minutes). */
    public const TIMEOUT_SECONDS = 1800;

    public static function configure(): void
    {
        $appUrl = Env::get('APP_URL', '') ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $isHttps = str_starts_with($appUrl, 'https://')
            || $forwardedProto === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }

        $timeout = (int)(Env::get('SESSION_TIMEOUT', (string)self::TIMEOUT_SECONDS) ?? self::TIMEOUT_SECONDS);
        if ($timeout > 0) {
            ini_set('session.gc_maxlifetime', (string)$timeout);
        }
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::enforceTimeout();
        self::enforceAuthStamp();
        AdminAccessService::enforceScope();
    }

    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
    }

    /**
     * Fingerprint of password_hash so password change/reset invalidates other sessions.
     */
    public static function authStampFromHash(?string $passwordHash): string
    {
        return hash('sha256', (string)$passwordHash);
    }

    public static function destroyAuthenticatedSession(?string $flashError = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        session_destroy();
        session_start();
        if ($flashError !== null && $flashError !== '') {
            $_SESSION['flash_error'] = $flashError;
        }
    }

    public static function enforceAuthStamp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user']['id'])) {
            return;
        }

        $userId = (int)$_SESSION['user']['id'];
        $stamp = (string)($_SESSION['user']['auth_stamp'] ?? '');

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT password_hash, role, deleted_at
                FROM users
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log('Session auth stamp check failed: ' . $e->getMessage());
            return;
        }

        if (!$row || $row['deleted_at'] !== null) {
            self::destroyAuthenticatedSession('Your session is no longer valid. Please log in again.');
            if (!headers_sent()) {
                header('Location: /auth/login');
                exit;
            }
            return;
        }

        $currentStamp = self::authStampFromHash($row['password_hash'] ?? null);
        // First request after deploy: adopt stamp without forcing logout
        if ($stamp === '') {
            $_SESSION['user']['auth_stamp'] = $currentStamp;
            $_SESSION['user']['role'] = $row['role'] ?? 'user';
            return;
        }

        if (!hash_equals($currentStamp, $stamp)) {
            self::destroyAuthenticatedSession('Your password was changed. Please log in again.');
            if (!headers_sent()) {
                header('Location: /auth/login');
                exit;
            }
            return;
        }

        // Keep role in sync with DB (admin demotion / promotion)
        $_SESSION['user']['role'] = $row['role'] ?? 'user';
    }

    public static function enforceTimeout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // No authenticated user — nothing to expire
        if (!isset($_SESSION['user'])) {
            return;
        }

        $timeout = (int)(Env::get('SESSION_TIMEOUT', (string)self::TIMEOUT_SECONDS) ?? self::TIMEOUT_SECONDS);
        if ($timeout <= 0) {
            $_SESSION['last_activity'] = time();
            return;
        }

        $now = time();
        $last = (int)($_SESSION['last_activity'] ?? $now);

        if (($now - $last) > $timeout) {
            self::destroyAuthenticatedSession('Your session expired. Please log in again.');

            if (!headers_sent()) {
                header('Location: /auth/login');
                exit;
            }
            return;
        }

        $_SESSION['last_activity'] = $now;
    }

    /** Clear session data, expire cookie, and destroy the session. */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
