<?php
declare(strict_types=1);

$router = require dirname(__DIR__) . '/src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route = isset($_GET['route']) ? (string) $_GET['route'] : '';

$router->dispatch($method, $route);