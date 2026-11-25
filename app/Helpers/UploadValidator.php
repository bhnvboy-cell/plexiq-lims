<?php

namespace App\Helpers;

class UploadValidator
{
    private static array $allowedExtensions = [
        'csv', 'xml', 'txt', 'json', 'pdf', 'png', 'jpg', 'jpeg'
    ];

    private static array $allowedMimeTypes = [
        'text/csv',
        'text/plain',
        'text/xml',
        'application/xml',
        'application/json',
        'application/pdf',
        'image/png',
        'image/jpeg'
    ];

    public static function validate(array $file, array $customExtensions = [], int $maxBytes = 20971520): array
    {
        $errors = [];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed with error code ' . ($file['error'] ?? 'UNKNOWN');
            return $errors;
        }

        if ($file['size'] > $maxBytes) {
            $errors[] = 'File exceeds maximum allowed size of ' . round($maxBytes / (1024 * 1024), 1) . ' MB.';
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowedExts = !empty($customExtensions) ? $customExtensions : self::$allowedExtensions;
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = "File extension '.{$ext}' is not permitted. Allowed extensions: " . implode(', ', $allowedExts);
        }

        if (function_exists('finfo_open') && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Allow generic text/plain for CSV/TXT/XML
            if ($mime && !in_array($mime, self::$allowedMimeTypes, true) && !str_starts_with($mime, 'text/')) {
                $errors[] = "MIME type '{$mime}' is not permitted.";
            }
        }

        return $errors;
    }

    public static function sanitizeFileName(string $filename): string
    {
        $base = basename($filename);
        $clean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $base);
        return ltrim($clean, '.');
    }
}
