<?php
/**
 * mDoc Citizen API — Front Controller
 * Entry point: https://mdoc.apis.com.la/api/v1/...
 *
 * All business logic lives in DocumentService; this file only wires routes.
 * Handler signature: function(array $query, array $params, ?array $body, array $headers)
 */

declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/Api/Request.php';
require __DIR__ . '/app/Api/Response.php';
require __DIR__ . '/app/Api/Router.php';
require __DIR__ . '/app/Services/DocumentService.php';

use App\Api\Request;
use App\Api\Response;
use App\Api\Router;
use App\Api\ApiException;
use App\Services\DocumentService;

// ---------------------------------------------------------------------------
// CORS — production origin locked; localhost origins allowed for PWA dev
// ---------------------------------------------------------------------------
$allowed_origins = [
    'https://mdoc.apis.com.la',
    'http://localhost:5173',
    'http://localhost:4173',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:4173',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------
$router = new Router();
$docService = new DocumentService();

// Auth
$router->post('auth/login', function ($q, $p, $body, $h) use ($docService) {
    return $docService->login($body);
});
$router->post('auth/register', function ($q, $p, $body, $h) use ($docService) {
    return $docService->register($body);
});
$router->get('auth/me', function ($q, $p, $body, $h) use ($docService) {
    return $docService->me($h);
});

// Catalog
$router->get('doc-types', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getDocTypes($q);
});
$router->get('doc-items', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getDocItems($q);
});
$router->get('doc-items/{id}', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getDocItem((int)$p['id']);
});
$router->get('doc-items/{id}/fields', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getDocItemFields((int)$p['id']);
});

// Hierarchy (Province → District → Taseng → Village)
$router->get('hierarchy', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getHierarchy($q);
});

// Applications
$router->post('documents', function ($q, $p, $body, $h) use ($docService) {
    return $docService->submitApplication($body, $h);
});
$router->get('documents', function ($q, $p, $body, $h) use ($docService) {
    return $docService->listMyDocuments($q, $h);
});
$router->get('documents/{id}', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getDocumentDetail((int)$p['id'], $h);
});

// Payment
$router->get('documents/{id}/pay-qr', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getPaymentQr((int)$p['id'], $h);
});
$router->post('payments/callback', function ($q, $p, $body, $h) use ($docService) {
    return $docService->processPaymentCallback($body);
});

// Download / receipt
$router->get('documents/{id}/download', function ($q, $p, $body, $h) use ($docService) {
    return $docService->downloadDocument((int)$p['id'], $h);
});
$router->get('documents/{id}/receipt', function ($q, $p, $body, $h) use ($docService) {
    return $docService->getReceipt((int)$p['id'], $h);
});

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
// Remove the /api/v1 prefix if present (when routed via Apache alias)
$prefixes = ['api/v1/', 'api/v1'];
foreach ($prefixes as $p) {
    if (str_starts_with($path, $p)) {
        $path = substr($path, strlen($p));
        break;
    }
}
$path = trim($path, '/');

$query = $_GET;
$body = Request::json();
$headers = function_exists('getallheaders') ? getallheaders() : [];

try {
    $result = $router->dispatch($path, $method, $query, $body, $headers);
    // If handler returned an array, send as JSON
    Response::json($result);
} catch (ApiException $e) {
    Response::error($e->apiCode, $e->getMessage(), $e->details, $e->httpCode);
} catch (Exception $e) {
    Response::error('SERVER_ERROR', $e->getMessage(), [], 500);
}
