<?php

require_once __DIR__ . '/../config/config.php';

class LLM
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-3-5';
    private const MAX_TOKENS = 1024;

    public static function sendMessage(string $userMessage, array $conversationHistory = []): string
    {
        $messages = $conversationHistory;
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $body = json_encode([
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => $messages,
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("API request failed: $error");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = $data['error']['message'] ?? "API returned HTTP $httpCode";
            throw new RuntimeException($msg);
        }

        return $data['content'][0]['text'] ?? '';
    }
}
