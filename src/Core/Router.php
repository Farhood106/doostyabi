<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var array<string, array<string, array{handler: callable|array, middleware: list<callable>}>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes[$method][rtrim($path, '/') ?: '/'] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request, App $app): mixed
    {
        $route = $this->routes[$request->method][$request->path] ?? null;
        if ($route === null) {
            Response::abort(404);
        }

        foreach ($route['middleware'] as $mw) {
            $result = $mw($request, $app);
            if ($result instanceof Closure) {
                $result();
            }
        }

        $handler = $route['handler'];

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $app->make($class);
            return $controller->{$method}($request);
        }

        return $handler($request, $app);
    }
}
