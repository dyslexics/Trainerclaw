<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Chat.php';
require_once __DIR__ . '/../src/LLM.php';
require_once __DIR__ . '/../src/Subscription.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function jsonResponse(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid JSON body'], 400);
    }
    return $input;
}

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
            jsonResponse([
                'status' => 'ok',
                'php' => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'],
            ]);
        },
        '/api/history' => function () {
            try {
                $userId = Auth::getAuthenticatedUser();
                $history = Chat::getHistory($userId);
                jsonResponse(['messages' => $history]);
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 401);
            }
        },
    ],
    'POST' => [
        '/api/register' => function () {
            $input = getJsonInput();
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            if (!$email || !$password) {
                jsonResponse(['error' => 'Email and password are required'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid email format'], 400);
            }
            if (strlen($password) < 8) {
                jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
            }

            try {
                $result = Auth::register($email, $password);
                jsonResponse($result, 201);
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 409);
            }
        },
        '/api/login' => function () {
            $input = getJsonInput();
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            if (!$email || !$password) {
                jsonResponse(['error' => 'Email and password are required'], 400);
            }

            try {
                $result = Auth::login($email, $password);
                jsonResponse($result);
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 401);
            }
        },
        '/api/chat' => function () {
            try {
                $userId = Auth::getAuthenticatedUser();
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 401);
            }

            if (!Subscription::hasActiveSubscription($userId)) {
                $db = getDB();
                $stmt = $db->prepare('SELECT subscription_status FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if (($user['subscription_status'] ?? 'free') !== 'free') {
                    jsonResponse(['error' => 'Active subscription required'], 403);
                }
            }

            $input = getJsonInput();
            $message = trim($input['message'] ?? '');
            if (!$message) {
                jsonResponse(['error' => 'Message is required'], 400);
            }

            try {
                $systemPrompt = 'Du bist OpenClaw, ein spezialisierter KI-Assistent für diplomierte Legasthenietrainer. '
                    . 'Du kennst die AFS-Methode (Aufmerksamkeit, Funktion, Symptom), alle EÖDL-Ausbildungsinhalte, '
                    . 'pädagogische Diagnostik, Trainingsplanung und Elterngesprächsführung. '
                    . 'Antworte immer auf Deutsch, präzise und fachlich korrekt.';
                $context = Chat::getConversationContext($userId);
                $response = LLM::sendMessage($message, $context, $systemPrompt);

                Chat::saveMessage($userId, 'user', $message);
                Chat::saveMessage($userId, 'assistant', $response);

                jsonResponse([
                    'response' => $response,
                ]);
            } catch (RuntimeException $e) {
                jsonResponse(['error' => $e->getMessage()], 502);
            }
        },
        '/api/webhook/stripe' => function () {
            $payload = file_get_contents('php://input');
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

            $elements = [];
            foreach (explode(',', $sigHeader) as $part) {
                [$key, $value] = explode('=', trim($part), 2);
                $elements[$key] = $value;
            }

            $timestamp = $elements['t'] ?? '';
            $signature = $elements['v1'] ?? '';

            if (!$timestamp || !$signature) {
                jsonResponse(['error' => 'Invalid signature'], 400);
            }

            $expected = hash_hmac('sha256', "$timestamp.$payload", STRIPE_WEBHOOK_SECRET);
            if (!hash_equals($expected, $signature)) {
                jsonResponse(['error' => 'Signature verification failed'], 400);
            }

            $event = json_decode($payload, true);
            $type = $event['type'] ?? '';
            $object = $event['data']['object'] ?? [];

            switch ($type) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $customerId = $object['customer'] ?? '';
                    $status = $object['status'] === 'active' ? 'active' : 'cancelled';
                    $expiresAt = isset($object['current_period_end'])
                        ? date('Y-m-d H:i:s', $object['current_period_end'])
                        : null;
                    Subscription::updateSubscription($customerId, $status, $expiresAt);
                    break;

                case 'customer.subscription.deleted':
                    $customerId = $object['customer'] ?? '';
                    Subscription::updateSubscription($customerId, 'cancelled', null);
                    break;
            }

            jsonResponse(['received' => true]);
        },
    ],
];

if (isset($routes[$method][$uri])) {
    $routes[$method][$uri]();
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}
