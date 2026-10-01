<?php

namespace App\Services;

/**
 * Resolve public /uploads/... URLs only when the file exists on disk.
 */
final class UploadUrl
{
    public static function existing(?string $publicPath): ?string
    {
        if ($publicPath === null || $publicPath === '') {
            return null;
        }

        if (!str_starts_with($publicPath, '/uploads/')) {
            return null;
        }

        $fullPath = dirname(__DIR__, 2) . $publicPath;
        if (!is_file($fullPath)) {
            return null;
        }

        return $publicPath;
    }
}
