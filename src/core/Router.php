<?php

class Router {
    /** @var array<string, array<int, array{pattern:string, handler:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $pattern, array $handler): void {
        $this->routes['GET'][] = ['pattern' => $pattern, 'handler' => $handler];
    }
    public function post(string $pattern, array $handler): void {
        $this->routes['POST'][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void {
        $method = strtoupper($method);

        // Synthetic error pages from .htaccess ErrorDocument.
        if (isset($_GET['_err'])) {
            $code = (int)$_GET['_err'];
            http_response_code($code);
            (new Controller())->view('pages/' . $code, ['title' => $code . ' · ' . AFT_NAME], 'main');
            return;
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->invoke($route['handler'], array_values($params));
                return;
            }
        }

        http_response_code(404);
        (new Controller())->view('pages/404', ['title' => 'Page not found · ' . AFT_NAME], 'main');
    }

    private function compile(string $pattern): string {
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', function ($m) {
            return '(?P<' . $m[1] . '>[a-zA-Z0-9\-\_]+)';
        }, $pattern);
        return '#^' . $regex . '/?$#';
    }

    private function invoke(array $handler, array $args): void {
        [$class, $method] = $handler;
        $instance = new $class();
        call_user_func_array([$instance, $method], $args);
    }
}
