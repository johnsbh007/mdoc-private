<?php
declare(strict_types=1);

namespace App\Api;

/**
 * Simple API Router.
 * Maps HTTP method + path to a callable (usually a method on a Service class).
 * 
 * Example usage:
 *   $router = new Router();
 *   $router->get('documents/{id}', [DocumentService::class, 'getById']);
 *   $response = $router->dispatch($path, $method, $query, $body);
 */
class Router
{
    /** @var array<string, array{callable, array<string, string>}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET '.$path] = [$handler, []];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST '.$path] = [$handler, []];
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT '.$path] = [$handler, []];
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE '.$path] = [$handler, []];
    }

    /**
     * Dispatch the request.
     * 
     * @param string $path        Path without leading slash (e.g. 'documents/123')
     * @param string $method      HTTP method (GET, POST, ...)
     * @param array  $query       $_GET
     * @param string $body        Raw request body
     * @return Response           An instance that knows how to send itself
     */
    public function dispatch(string $path, string $method, array $query, string $body): Response
    {
        $key = strtoupper($method) . ' ' . $path;

        // Try exact match first
        if (isset($this->routes[$key])) {
            [$handler, $defaults] = $this->routes[$key];
            return $this->callHandler($handler, $query, $body);
        }

        // Try pattern matching for {id} placeholders
        foreach ($this->routes as $routeKey => [$handler, $defaults]) {
            [$routeMethod, $routePattern] = explode(' ', $routeKey, 2);
            if ($routeMethod !== strtoupper($method)) {
                continue;
            }

            // Convert pattern like 'documents/{id}' to regex
            $regex = '@^' . preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_-]+)', preg_quote($routePattern, '@')) . '$@D';
            if (preg_match($regex, $path, $matches)) {
                // Remove first element (full match)
                array_shift($matches);
                // Inject captured params into query
                $params = [];
                // We need to know the order of placeholders - for simplicity, we'll assume they are passed as query
                // A more robust implementation would extract placeholder names from $routePattern
                // For this scaffold, we'll rely on the service method signature to receive an 'id' param via query
                // Better approach: modify callHandler to inject captured params as arguments
                // For now, we'll just add them as query parameters with generic names
                // This is a limitation of this simple router; in practice use a proper router like FastRoute
                // Given the scope, we'll assume only single {id} placeholder and pass it as 'id'
                if (!empty($matches)) {
                    $query = array_merge($query, ['id' => $matches[0]]);
                }
                return $this->callHandler($handler, $query, $body);
            }
        }

        // Not found
        return Response::error('ROUTE_NOT_FOUND', "No route for $method $path", [], 404);
    }

    /**
     * Call the handler (could be a Closure or [Class, method]).
     */
    private function callHandler(callable $handler, array $query, string $body): Response
    {
        // If handler is a Closure, call it with Request and Body
        if ($handler instanceof \Closure) {
            // Assume closure signature: function(Request $request, array $query, string $body)
            // For simplicity, we'll create a Request object from our static helpers
            // but we need to pass the actual query and body
            // Let's instead design the handler to expect (array $query, string $body)
            // We'll keep it simple: handler receives query and body
            return $handler($query, $body);
        }

        // If handler is [Class::method, ...]
        if (is_array($handler) && isset($handler[0]) && isset($handler[1])) {
            [$class, $method] = $handler;
            // Instantiate the class (assuming no constructor args for now)
            $instance = new $class();
            // Call the method with query and body
            return $instance->$method($query, $body);
        }

        // Fallback: treat as invokable object
        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return $handler->__invoke($query, $body);
        }

        // If we reach here, the handler is not callable in a way we understand
        return Response::error('INTERNAL_ERROR', 'Invalid route handler configuration', [], 500);
    }
}