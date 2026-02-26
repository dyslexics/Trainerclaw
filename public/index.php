<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    'GET' => [
        '/' => function () {
            echo '<h1>OpenClaw</h1>';
            echo '<p>Welcome to OpenClaw.</p>';
        },
        '/about' => function () {
            echo '<h1>About</h1>';
            echo '<p>OpenClaw project.</p>';
        },
        '/health' => function () {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok',
                'php' => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'],
            ]);
        },
    ],
    'POST' => [],
];

if (isset($routes[$method][$uri])) {
    $routes[$method][$uri]();
} else {
    http_response_code(404);
    echo '<h1>404</h1><p>Page not found.</p>';
}
