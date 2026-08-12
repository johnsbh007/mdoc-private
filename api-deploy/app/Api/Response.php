<?php
declare(strict_types=1);

namespace App\Api;

/**
 * Helper to send JSON responses with proper headers and HTTP codes.
 * Handlers may also throw ApiException (see DocumentService) for controlled errors.
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
                'details' => $details,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function sendHeaders(int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

/**
 * Throwable API error — caught by the front controller and rendered as JSON.
 * $apiCode is a stable string identifier (e.g. 'VALIDATION_ERROR'), $message is human-readable.
 */
class ApiException extends \Exception
{
    public string $apiCode;
    public int $httpCode;
    public array $details;

    public function __construct(string $apiCode, string $message, int $httpCode = 400, array $details = [])
    {
        parent::__construct($message);
        $this->apiCode = $apiCode;
        $this->httpCode = $httpCode;
        $this->details = $details;
    }
}
