<?php

function loadEnv(string $path): array
{
    $vars = [];
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found: $path");
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }
    return $vars;
}

$config = loadEnv(__DIR__ . '/../.env');

define('APP_NAME', $config['APP_NAME'] ?? 'OpenClaw');
define('APP_ENV', $config['APP_ENV'] ?? 'local');
define('APP_URL', $config['APP_URL'] ?? 'http://localhost');

define('DB_HOST', $config['DB_HOST'] ?? 'localhost');
define('DB_PORT', $config['DB_PORT'] ?? '3306');
define('DB_DATABASE', $config['DB_DATABASE'] ?? '');
define('DB_USERNAME', $config['DB_USERNAME'] ?? '');
define('DB_PASSWORD', $config['DB_PASSWORD'] ?? '');

define('ANTHROPIC_API_KEY', $config['ANTHROPIC_API_KEY'] ?? '');
define('JWT_SECRET', $config['JWT_SECRET'] ?? '');
define('STRIPE_WEBHOOK_SECRET', $config['STRIPE_WEBHOOK_SECRET'] ?? '');
