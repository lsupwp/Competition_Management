<?php

declare(strict_types=1);

/**
 * Escape for HTML text/attributes (UTF-8).
 */
function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Strip CR/LF/NUL so values cannot inject SMTP/HTTP headers.
 */
function header_safe(mixed $value): string
{
    return str_replace(["\r", "\n", "\0"], '', (string)$value);
}

/**
 * Normalize user/team display names before persist or email.
 */
function sanitize_display_name(mixed $value): string
{
    $value = preg_replace('/[\r\n\x00]+/u', ' ', (string)$value) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

/**
 * Allow only app-uploaded relative paths for img src.
 */
function safe_upload_url(mixed $url): string
{
    $url = trim((string)$url);
    if ($url !== '' && str_starts_with($url, '/uploads/') && !str_contains($url, '..')) {
        return h($url);
    }
    return '';
}

/**
 * Resolve an uploads URL to a real path under the uploads root, or null.
 */
function safe_upload_path(mixed $url, string $uploadsRoot): ?string
{
    $url = trim((string)$url);
    if ($url === '' || !str_starts_with($url, '/uploads/') || str_contains($url, '..')) {
        return null;
    }
    $root = realpath($uploadsRoot);
    if ($root === false) {
        return null;
    }
    $candidate = realpath($uploadsRoot . substr($url, strlen('/uploads')));
    if ($candidate === false || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $candidate;
}

/**
 * Allow only #RGB / #RRGGBB for safe use in CSS color contexts.
 */
function css_hex_color(mixed $color, string $fallback = '#3b82f6'): string
{
    $color = trim((string)$color);
    if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $color) === 1) {
        return strtolower($color);
    }
    return $fallback;
}

/**
 * Encode for embedding inside a <script> context.
 */
function js_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
}
