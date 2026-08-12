<?php
declare(strict_types=1);

namespace App\Api;

/**
 * Helper to send JSON responses with proper headers and HTTP codes.
 */
class Response
{
    /**
     * Send a JSON success response.
     */
    public static function json($data = [], int $code = 200): void
    {
        self::sendHeaders($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send a JSON error response.
     */
    public static function error(string $code, string $message, array $details = [], int $httpCode = 400): void
    {
        self::sendHeaders($httpCode);
        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send appropriate headers for JSON.
     */
    private static function sendHeaders(int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        // Cache control: no caching for API responses by default
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Send a file download (for PDF, etc).
     */
    public static function file(string $filePath, string $downloadName = null): void
    {
        if (!is_file($filePath)) {
            self::error('FILE_NOT_FOUND', 'File not found', [], 404);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($downloadName ?: $filePath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }
}