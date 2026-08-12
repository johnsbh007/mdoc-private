<?php
declare(strict_types=1);

namespace App\Api;

/**
 * Simple API Router — maps HTTP method + path to a callable.
 * Handlers receive: (array $query, array $params, array $body, array $headers)
 */
class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST ' . $path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT ' . $path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE ' . $path] = $handler;
    }

    /**
     * Dispatch the request.
     *
     * @param string $path    Path without leading slash (e.g. 'documents/123')
     * @param string $method  HTTP method
     * @param array  $query   $_GET
     * @param array|null $body Decoded JSON body
     * @param array  $headers Request headers
     * @return mixed Handler return value (usually an array for JSON response)
     */
    public function dispatch(string $path, string $method, array $query, ?array $body, array $headers = [])
    {
        $method = strtoupper($method);
        $key = $method . ' ' . $path;

        // Exact match first
        if (isset($this->routes[$key])) {
            return $this->callHandler($this->routes[$key], $query, [], $body, $headers);
        }

        // Pattern matching for {id} placeholders
        foreach ($this->routes as $routeKey => $handler) {
            [$routeMethod, $routePattern] = explode(' ', $routeKey, 2);
            if ($routeMethod !== $method) {
                continue;
            }

            // Convert 'documents/{id}/pay-qr' -> regex with named captures.
            // IMPORTANT: quote only the literal segments, never the {placeholder}
            // parts — preg_quote() would escape the braces AND the parens of the
            // named group, breaking the pattern.
            $parts = preg_split('/(\{[a-zA-Z0-9_]+\})/', $routePattern, -1, PREG_SPLIT_DELIM_CAPTURE);
            $regexParts = [];
            foreach ($parts as $part) {
                if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $m)) {
                    $regexParts[] = '(?P<' . $m[1] . '>[a-zA-Z0-9_-]+)';
                } else {
                    $regexParts[] = preg_quote($part, '@');
                }
            }
            $regex = '@^' . implode('', $regexParts) . '$@D';
            if (preg_match($regex, $path, $matches)) {
                // Extract named params only
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                return $this->callHandler($handler, $query, $params, $body, $headers);
            }
        }

        // Not found
        Response::error('ROUTE_NOT_FOUND', "No route for $method /$path", [], 404);
        return null;
    }

    /**
     * Invoke the handler with normalized arguments.
     */
    private function callHandler(callable $handler, array $query, array $params, ?array $body, array $headers)
    {
        return $handler($query, $params, $body, $headers);
    }
}
