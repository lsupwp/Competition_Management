<?php

namespace App\Services;

class ImageUploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and store an uploaded image.
     *
     * @return array{success:bool, path?:string, mime?:string, size?:int, filename?:string, error?:string}
     */
    public static function store(array $file, string $destinationDir, string $filenamePrefix, int $maxBytes = 2097152): array
    {
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file was uploaded.'];
        }

        if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
            return ['success' => false, 'error' => 'File is too large. Maximum size is 2MB.'];
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload failed. Please try again.'];
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['success' => false, 'error' => 'Invalid uploaded file.'];
        }

        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return ['success' => false, 'error' => 'Uploaded file is empty.'];
        }
        if ($fileSize > $maxBytes) {
            return ['success' => false, 'error' => 'File is too large. Maximum size is 2MB.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath) ?: '';
        if (!isset(self::ALLOWED_MIMES[$detectedMime])) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.'];
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'File is not a valid image.'];
        }

        $imageMime = $imageInfo['mime'] ?? '';
        if ($imageMime !== $detectedMime || !isset(self::ALLOWED_MIMES[$imageMime])) {
            return ['success' => false, 'error' => 'File is not a valid image.'];
        }

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            return ['success' => false, 'error' => 'Failed to prepare upload directory.'];
        }

        $extension = self::ALLOWED_MIMES[$detectedMime];
        $filename = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        // Strip execute bits from stored file
        @chmod($destination, 0644);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $destination,
            'mime' => $detectedMime,
            'size' => $fileSize,
        ];
    }
}
