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
    }

    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
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
            $_SESSION['flash_error'] = 'Your session expired. Please log in again.';

            if (!headers_sent()) {
                header('Location: /auth/login');
                exit;
            }
            return;
        }

        $_SESSION['last_activity'] = $now;
    }
}
