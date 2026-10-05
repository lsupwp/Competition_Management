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

    /** Client filename extension must be one of these (lowercase, no dot). */
    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /**
     * Validate and store an uploaded image.
     *
     * Checks: upload error, size, client extension allow-list, detected MIME,
     * getimagesize content, and extension↔content match. Stores under a new name.
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

        $originalName = (string)($file['name'] ?? '');
        $clientExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($clientExt === '' || !isset(self::ALLOWED_EXTENSIONS[$clientExt])) {
            return ['success' => false, 'error' => 'Invalid file extension. Only JPG, PNG, GIF, and WebP are allowed.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath) ?: '';
        if (!isset(self::ALLOWED_MIMES[$detectedMime])) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.'];
        }

        // Extension must match real file content (blocks PNG renamed to .txt, etc.)
        if (self::ALLOWED_EXTENSIONS[$clientExt] !== $detectedMime) {
            return ['success' => false, 'error' => 'File extension does not match the image content.'];
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

        if (!is_writable($destinationDir)) {
            @chmod($destinationDir, 0775);
        }
        if (!is_writable($destinationDir)) {
            error_log('ImageUploadService: directory not writable: ' . $destinationDir);
            return ['success' => false, 'error' => 'Upload directory is not writable. Please contact the administrator.'];
        }

        $extension = self::ALLOWED_MIMES[$detectedMime];
        $filename = $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!@move_uploaded_file($tmpPath, $destination)) {
            error_log('ImageUploadService: move_uploaded_file failed to ' . $destination);
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
