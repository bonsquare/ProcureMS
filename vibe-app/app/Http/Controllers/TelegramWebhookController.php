<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use Illuminate\Http\Request;

/** Receives what is written in the Customer Services Telegram chat: a reply to a forwarded message (or "#12 text") becomes the answer shown to the user on the Support page. */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret)
    {
        $expected = (string) config('services.telegram.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        abort_unless($header === '' || hash_equals($expected, $header), 403);

        $message = $request->input('message');
        // Only the Customer Services chat can answer.
        if (! is_array($message) || (string) data_get($message, 'chat.id') !== (string) config('services.telegram.chat_id')) {
            return response()->json(['ok' => true]);
        }
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return response()->json(['ok' => true]);
        }

        $support = null;
        if ($repliedTo = data_get($message, 'reply_to_message.message_id')) {
            $support = SupportMessage::query()->where('telegram_message_id', (int) $repliedTo)->first();
        }
        if (! $support && preg_match('/^#(\d+)\s+(.+)$/s', $text, $parts)) {
            $support = SupportMessage::query()->find((int) $parts[1]);
            $text = trim($parts[2]);
        }
        if ($support) {
            $support->replies()->create(['body' => $text, 'telegram_message_id' => $message['message_id'] ?? null]);
            $support->update(['status' => 'answered']);
        }

        return response()->json(['ok' => true]);
    }
}
