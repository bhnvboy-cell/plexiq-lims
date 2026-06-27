<?php

namespace App;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, $handler, array $middleware): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function addMiddleware(string $name, callable $handler): void
    {
        $this->middleware[$name] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // CSRF protection for state-changing requests (skip API routes)
        if (in_array($method, ['POST', 'PUT', 'DELETE']) && !str_starts_with($uri, '/api/')) {
            if (!csrf_validate()) {
                http_response_code(419);
                echo '<h1>419 Page Expired</h1><p>CSRF token mismatch. Please go back and try again.</p>';
                return;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Run route middleware
                foreach ($route['middleware'] as $mw) {
                    if (isset($this->middleware[$mw])) {
                        $result = call_user_func($this->middleware[$mw]);
                        if ($result === false) return;
                    }
                }

                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    echo call_user_func_array([$controller, $method], $params);
                } elseif (is_callable($handler)) {
                    echo call_user_func_array($handler, $params);
                }
                return;
            }
        }

        http_response_code(404);
        echo view('errors.404');
    }
}
