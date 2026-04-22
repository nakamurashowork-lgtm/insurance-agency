<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /**
     * @var array<string, callable>
     */
    private array $routes = [];

    public function __construct(private string $appUrl = '')
    {
    }

    public function get(string $route, callable $handler): void
    {
        $this->addRoute('GET', $route, $handler);
    }

    public function post(string $route, callable $handler): void
    {
        $this->addRoute('POST', $route, $handler);
    }

    public function dispatch(string $method, string $route): void
    {
        $normalizedMethod = strtoupper($method);
        $normalizedRoute = trim($route, '/');
        $key = $normalizedMethod . ':' . $normalizedRoute;

        if (!isset($this->routes[$key])) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<h1>404 Not Found</h1><p>ページが見つかりません。</p><p><a href="?route=">ホームへ</a></p>';
            return;
        }

        ($this->routes[$key])();
    }

    private function addRoute(string $method, string $route, callable $handler): void
    {
        $normalizedRoute = trim($route, '/');
        $key = strtoupper($method) . ':' . $normalizedRoute;
        $this->routes[$key] = $handler;
    }
}
