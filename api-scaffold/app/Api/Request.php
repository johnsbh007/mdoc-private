<?php
declare(strict_types=1);

namespace App\Api;

/**
 * Helper to get request data (JSON body, query params, headers)
 */
class Request
{
    /**
     * Get the raw JSON body and decode it.
     * @return mixed|null
     */
    public static function json(): ?array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return null;
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Malformed JSON
            return null;
        }
        return $data;
    }

    /**
     * Get a query parameter with fallback.
     */
    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get a header (case-insensitive).
     */
    public static function header(string $key, $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '_', $key));
        // Check in $_SERVER
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        // Also check HTTP_* headers
        $httpKey = 'HTTP_' . $key;
        if (isset($_SERVER[$httpKey])) {
            return $_SERVER[$httpKey];
        }
        return $default;
    }

    /**
     * Get the Bearer token from Authorization header.
     */
    public static function bearerToken(): ?string
    {
        $auth = self::header('Authorization');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }
}