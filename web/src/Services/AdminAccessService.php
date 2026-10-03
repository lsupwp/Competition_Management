<?php

namespace App\Services;

/**
 * System admins may only use the activity log (+ logout).
 */
final class AdminAccessService
{
    public const HOME_PATH = '/activity';

    /** @var list<string> */
    private const ALLOWED_PATHS = [
        '/activity',
        '/auth/logout',
    ];

    public static function isAdmin(?array $user = null): bool
    {
        $user ??= $_SESSION['user'] ?? null;
        return is_array($user) && ($user['role'] ?? '') === 'admin';
    }

    public static function loginRedirectPath(?array $user = null): string
    {
        return self::isAdmin($user) ? self::HOME_PATH : '/';
    }

    /**
     * If the current session user is a system admin on a disallowed page, redirect.
     * Call after the session is started.
     */
    public static function enforceScope(): void
    {
        if (!self::isAdmin()) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $path = rtrim($path, '/') ?: '/';

        if (in_array($path, self::ALLOWED_PATHS, true)) {
            return;
        }

        header('Location: ' . self::HOME_PATH);
        exit;
    }
}
