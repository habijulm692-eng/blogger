<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

class Router
{
    private array $routes = [];

    public function load(string $routesFile): void
    {
        $this->routes = require $routesFile;
    }

    /**
     * @param string $uri Already normalized path (no base prefix), e.g. /login
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = $uri === '' ? '/' : $uri;
        if ($uri !== '/') {
            $uri = '/' . trim($uri, '/');
        }
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            [$routeMethod, $routePath, $controller, $action, $middleware] = array_pad($route, 5, []);

            if (strtoupper((string) $routeMethod) !== $method) {
                continue;
            }

            $params = $this->match((string) $routePath, $uri);
            if ($params === null) {
                continue;
            }

            foreach ((array) $middleware as $mw) {
                $this->runMiddleware((string) $mw);
            }

            $controllerClass = "App\\Controllers\\{$controller}";

            if (!class_exists($controllerClass)) {
                http_response_code(500);
                echo "Controller not found: {$controller}";
                return;
            }

            $instance = new $controllerClass();

            if (!method_exists($instance, $action)) {
                http_response_code(500);
                echo "Action not found: {$action}";
                return;
            }

            $instance->$action(...array_values($params));
            return;
        }

        http_response_code(404);
        $viewFile = dirname(__DIR__) . '/Views/errors/404.php';
        if (is_file($viewFile)) {
            require $viewFile;
        } else {
            echo '404 — Page not found';
        }
    }

    private function match(string $routePath, string $uri): ?array
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = is_numeric($value) ? (int) $value : $value;
            }
        }

        return $params;
    }

    private function runMiddleware(string $name): void
    {
        match ($name) {
            'auth' => (new AuthMiddleware())->handle(),
            'guest' => (new GuestMiddleware())->handle(),
            'csrf' => (new CsrfMiddleware())->handle(),
            default => null,
        };
    }
}
