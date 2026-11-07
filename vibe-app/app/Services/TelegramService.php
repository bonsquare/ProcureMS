<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** The Telegram bot of Customer Services: sends a message to the Customer Services chat and registers the webhook that brings the replies back. */
class TelegramService
{
    public function configured(): bool
    {
        return (string) config('services.telegram.token') !== '' && (string) config('services.telegram.chat_id') !== '';
    }

    /** Sends the text to the Customer Services chat (as a reply to message $replyTo when given); returns the id of the message sent, or null when it could not be sent. */
    public function send(string $text, ?int $replyTo = null): ?int
    {
        if (! $this->configured()) {
            return null;
        }
        try {
            $response = Http::timeout(8)->post($this->url('sendMessage'), array_filter(['chat_id' => config('services.telegram.chat_id'), 'text' => $text, 'reply_to_message_id' => $replyTo]));

            if (! $response->successful()) {
                Log::warning('Telegram message was not sent.', ['status' => $response->status(), 'error' => $response->json('description')]);

                return null;
            }

            return (int) $response->json('result.message_id');
        } catch (Throwable $exception) {
            Log::warning('Telegram message was not sent.', ['error' => $exception->getMessage()]);

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

    /** Fills the menu Telegram shows when someone types "/" in the chat. */
    public function setCommands(): array
    {
        return Http::timeout(15)->post($this->url('setMyCommands'), ['commands' => [
            ['command' => 'assign', 'description' => 'Take a request (or: /assign 12 @name)'],
            ['command' => 'process', 'description' => 'Set the request to On process'],
            ['command' => 'hold', 'description' => 'Set the request to On hold'],
            ['command' => 'approve', 'description' => 'Set the request to Approved'],
            ['command' => 'resolve', 'description' => 'Set the request to Resolved'],
            ['command' => 'queue', 'description' => 'List the requests not yet resolved'],
            ['command' => 'mine', 'description' => 'List my requests'],
        ]])->json() ?? [];
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
