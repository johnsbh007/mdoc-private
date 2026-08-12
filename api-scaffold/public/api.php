<?php
/**
 * API Front Controller - mDoc Citizen PWA
 * Handles all /api/v1/* requests
 * 
 * Usage: Point your web server to this file for API routes
 * Example Nginx:
 *   location /api/v1/ {
 *       try_files $uri $uri/ /api.php?$query_string;
 *   }
 */

// Load Composer autoloader if available (for PHPMailer, etc)
// require __DIR__ . '/../vendor/autoload.php';

// Load application bootstrap
require __DIR__ . '/../bootstrap.php'; // Your existing app init

use App\Api\Router;
use App\Api\Request;
use App\Api\Response;

// Only allow API routes
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/api/v1/')) {
    // Remove /api/v1 prefix for internal routing
    $path = substr($uri, strlen('/api/v1/'));
    
    // Instantiate router and dispatch
    $router = new Router();
    $response = $router->dispatch($path, $_SERVER['REQUEST_METHOD'], $_GET, file_get_contents('php://input'));
    
    // Send response
    $response->send();
    exit;
}

// If not an API route, return 404 (or let your main app handle it)
http_response_code(404);
echo json_encode(['error' => ['code' => 'NOT_FOUND', 'message' => 'API endpoint not found']]);
?>