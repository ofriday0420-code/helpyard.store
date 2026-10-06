<?php

namespace Helpyard\App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    public function get(string $path, callable|array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): self
    {
        $this->routes[strtoupper($method)][$path] = $handler;

        return $this;
    }

    public function merge(self $router): self
    {
        foreach ($router->routes as $method => $paths) {
            $this->routes[$method] = array_merge($this->routes[$method] ?? [], $paths);
        }

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $routes = $this->routes[$method] ?? [];

        if (isset($routes[$path])) {
            return $this->callHandler($routes[$path], [], $request);
        }

        foreach ($routes as $routePath => $handler) {
            $params = [];
            if ($this->matchRoute($routePath, $path, $params)) {
                return $this->callHandler($handler, $params, $request);
            }
        }

        return new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], ['error' => 'Route not found']);
    }

    private function matchRoute(string $routePath, string $requestPath, array &$params): bool
    {
        if ($routePath === $requestPath) {
            return true;
        }

        $names = [];
        $pattern = '';
        $offset = 0;
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routePath, $tokens, PREG_OFFSET_CAPTURE);

        foreach ($tokens[0] as $index => [$token, $position]) {
            $pattern .= preg_quote(substr($routePath, $offset, $position - $offset), '#') . '([^/]+)';
            $names[] = $tokens[1][$index][0];
            $offset = $position + strlen($token);
        }

        $pattern = '#^' . $pattern . preg_quote(substr($routePath, $offset), '#') . '$#';

        if (preg_match($pattern, $requestPath, $matches)) {
            array_shift($matches);
            $params = array_combine($names, $matches) ?: [];
            return true;
        }

        return false;
    }

    private function callHandler(callable|array $handler, array $params, Request $request): Response
    {
        if (is_array($handler)) {
            [$controllerReference, $method] = $handler;
            $controller = is_string($controllerReference) ? new $controllerReference() : $controllerReference;
            $result = $controller->$method($params, $request);

            return $result instanceof Response ? $result : new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], $result);
        }

        $result = $handler($params, $request);

        return $result instanceof Response ? $result : new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], $result);
    }
}
