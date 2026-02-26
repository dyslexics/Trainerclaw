<?php

require_once __DIR__ . '/../config/database.php';

class Chat
{
    public static function saveMessage(int $userId, string $role, string $message): int
    {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO conversations (user_id, role, message) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $role, $message]);
        return (int) $db->lastInsertId();
    }

    public static function getHistory(int $userId, int $limit = 50): array
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT id, role, message, created_at FROM conversations WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }

    public static function getConversationContext(int $userId, int $limit = 10): array
    {
        $rows = self::getHistory($userId, $limit);
        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'role' => $row['role'],
                'content' => $row['message'],
            ];
        }
        return $messages;
    }
}
