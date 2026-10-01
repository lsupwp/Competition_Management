<?php

declare(strict_types=1);

/**
 * Load .env into $_ENV / getenv when present.
 * Docker already injects env vars; file load fills gaps.
 */
$envCandidates = [
    dirname(__DIR__, 2) . '/.env', // /var/www/html/.env (compose mount)
    dirname(__DIR__) . '/.env',    // /var/www/html/web/.env
];

$envFile = null;
foreach ($envCandidates as $candidate) {
    if (is_readable($candidate)) {
        $envFile = $candidate;
        break;
    }
}

if ($envFile !== null && class_exists(Dotenv\Dotenv::class)) {
    try {
        Dotenv\Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
    } catch (Throwable $e) {
        // Keep Docker-injected env if .env parse fails (e.g. unquoted spaces).
        error_log('dotenv load skipped: ' . $e->getMessage());
    }
}

$appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = str_starts_with($appUrl, 'https://')
    || $forwardedProto === 'https'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// Session cookie hardening (must run before session_start)
if (class_exists(App\Services\SessionService::class)) {
    App\Services\SessionService::configure();
} else {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
}

// Hide fatals/details from end users unless APP_DEBUG is explicitly enabled
$appDebug = strtolower((string)($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false'));
$debugEnabled = in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('display_startup_errors', $debugEnabled ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

if (class_exists(App\Services\SecurityHeaders::class)) {
    App\Services\SecurityHeaders::apply();
}
