<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/** The Telegram bot of Customer Services: sends a message to the Customer Services chat and registers the webhook that brings the replies back. */
class TelegramService
{
    public function configured(): bool
    {
        return (string) config('services.telegram.token') !== '' && (string) config('services.telegram.chat_id') !== '';
    }

    /** Sends the text to the Customer Services chat; returns the id of the message sent, or null when it could not be sent. */
    public function send(string $text): ?int
    {
        if (! $this->configured()) {
            return null;
        }
        try {
            $response = Http::timeout(8)->post($this->url('sendMessage'), ['chat_id' => config('services.telegram.chat_id'), 'text' => $text]);

            return $response->successful() ? (int) $response->json('result.message_id') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Tells Telegram where to deliver the replies (the secret is in the address and in the secret-token header). */
    public function setWebhook(string $baseUrl): array
    {
        $secret = (string) config('services.telegram.webhook_secret');

        return Http::timeout(15)->post($this->url('setWebhook'), [
            'url' => rtrim($baseUrl, '/').'/telegram/webhook/'.$secret,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ])->json() ?? [];
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
