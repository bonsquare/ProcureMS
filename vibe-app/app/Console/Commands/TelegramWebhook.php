<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {url? : The public https address of the system (default APP_URL)}';

    protected $description = 'Register the Telegram webhook so replies written in Telegram reach the Support page';

    public function handle(TelegramService $telegram): int
    {
        if (! $telegram->configured() || (string) config('services.telegram.webhook_secret') === '') {
            $this->error('Set TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID and TELEGRAM_WEBHOOK_SECRET first.');

            return self::FAILURE;
        }
        $url = (string) ($this->argument('url') ?: config('app.url'));
        if (! str_starts_with($url, 'https://')) {
            $this->error('Telegram needs a public https address (got "'.$url.'"). Pass it as: php artisan telegram:webhook https://your-domain');

            return self::FAILURE;
        }
        $result = $telegram->setWebhook($url);
        $this->line(json_encode($result));
        $this->line(json_encode($telegram->setCommands()));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
