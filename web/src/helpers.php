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
