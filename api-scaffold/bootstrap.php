<?php
/**
 * Bootstrap file - Initializes the application
 * This would be included by both the main web app (admin) and the API front controller.
 */

// Start session (if needed)
session_start();

// Load configuration (DB credentials, etc)
// In practice, this would come from environment variables or a config file not in VCS
$config = require __DIR__ . '/config.php';

// Initialize PDO connection (singleton pattern)
// This matches the DB::conn() pattern we discussed in the security baseline
if (!isset($GLOBALS['db'])) {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db']['host'],
        $config['db']['name']
    );
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $GLOBALS['db'] = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
}

// Define a simple DB helper function for convenience
function db(): PDO
{
    return $GLOBALS['db'];
}

// Define a simple json_response helper if not using the Response class
if (!function_exists('json_response')) {
    function json_response($data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Define error_response helper
if (!function_exists('error_response')) {
    function error_response(string $code, string $message, array $details = [], int $httpCode = 400): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}