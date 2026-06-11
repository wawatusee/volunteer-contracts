<?php
/**
 * Router
 *
 * Minimal front-controller that matches (method, path) pairs to callables.
 *
 * Supports:
 *  - Static segments : /api/workers
 *  - Named parameters: /api/workers/{id}
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/api/workers',       [WorkerController::class, 'index']);
 *   $router->post('/api/workers',      [WorkerController::class, 'store']);
 *   $router->get('/api/workers/{id}',  [WorkerController::class, 'show']);
 *   $router->dispatch();
 *
 * @package VolunteerContracts\Routing
 */
class Router
{
    /** @var array[] Registered routes: [{method, pattern, handler, paramNames}] */
    private array $routes = [];

    // -------------------------------------------------------------------------
    // Route registration helpers
    // -------------------------------------------------------------------------

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Match the current request and invoke the corresponding handler.
     * Sends a JSON 404 if no route matches.
     */
    public function dispatch(): void
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Strip query string and trailing slash
        $uri     = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $uri     = '/' . trim($uri, '/');

        // Allow browsers to send PUT/DELETE via POST + _method override
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            if ($this->match($route['pattern'], $uri, $route['paramNames'], $params)) {
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        // No route matched
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found', 'path' => $uri]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function add(string $method, string $path, callable|array $handler): void
    {
        [$pattern, $paramNames] = $this->compile($path);
        $this->routes[] = compact('method', 'pattern', 'handler', 'paramNames');
    }

    /**
     * Convert a route path like /api/workers/{id} into a regex and extract
     * the parameter names.
     *
     * @return array [string $regex, string[] $paramNames]
     */
    private function compile(string $path): array
    {
        $paramNames = [];

        $pattern = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $path);

        $pattern = '#^' . $pattern . '$#';

        return [$pattern, $paramNames];
    }

    /**
     * Try to match a URI against a compiled pattern.
     * On success, populates $params with named captures.
     */
    private function match(string $pattern, string $uri, array $paramNames, array &$params): bool
    {
        if (!preg_match($pattern, $uri, $matches)) {
            return false;
        }

        array_shift($matches); // remove full match
        foreach ($paramNames as $i => $name) {
            $params[$name] = rawurldecode($matches[$i] ?? '');
        }

        return true;
    }

    /**
     * Invoke a handler (callable or [ClassName, method] array).
     *
     * @param callable|array $handler
     * @param array          $params  Named URL parameters
     */
    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            // Instantiate controller from DI container or directly
            $class  = $handler[0];
            $method = $handler[1];
            $obj    = new $class();
            $obj->$method($params);
        } else {
            call_user_func($handler, $params);
        }
    }
}
