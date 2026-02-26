<?php

require_once __DIR__ . '/../config/database.php';

class Subscription
{
    public static function hasActiveSubscription(int $userId): bool
    {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT id FROM subscriptions WHERE user_id = ? AND status = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$userId, 'active']);
        return (bool) $stmt->fetch();
    }

    public static function createSubscription(int $userId, string $stripeCustomerId, string $status, ?string $expiresAt): int
    {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO subscriptions (user_id, stripe_customer_id, status, expires_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $stripeCustomerId, $status, $expiresAt]);

        $db->prepare('UPDATE users SET subscription_status = ? WHERE id = ?')->execute([$status, $userId]);

        return (int) $db->lastInsertId();
    }

    public static function updateSubscription(string $stripeCustomerId, string $status, ?string $expiresAt): void
    {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE subscriptions SET status = ?, expires_at = ? WHERE stripe_customer_id = ?'
        );
        $stmt->execute([$status, $expiresAt, $stripeCustomerId]);

        $stmt = $db->prepare(
            'UPDATE users u JOIN subscriptions s ON u.id = s.user_id SET u.subscription_status = ? WHERE s.stripe_customer_id = ?'
        );
        $stmt->execute([$status, $stripeCustomerId]);
    }
}
