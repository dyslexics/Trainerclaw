<?php

require_once __DIR__ . '/../config/database.php';

class Auth
{
    public static function register(string $email, string $password): array
    {
        $db = getDB();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Email already registered');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $hash]);
        $userId = (int) $db->lastInsertId();

        return [
            'user_id' => $userId,
            'token' => self::generateToken($userId),
        ];
    }

    public static function login(string $email, string $password): array
    {
        $db = getDB();

        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials');
        }

        return [
            'user_id' => (int) $user['id'],
            'token' => self::generateToken((int) $user['id']),
        ];
    }

    public static function generateToken(int $userId): string
    {
        $header = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64url(json_encode([
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + 86400,
        ]));
        $signature = self::base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        return "$header.$payload.$signature";
    }

    public static function validateToken(string $token): int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$header, $payload, $signature] = $parts;

        $expected = self::base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!$data || ($data['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token expired');
        }

        return (int) $data['sub'];
    }

    public static function getAuthenticatedUser(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new RuntimeException('Missing authorization token');
        }
        return self::validateToken($matches[1]);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
