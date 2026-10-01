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

if ($isHttps) {
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Lax');
}
