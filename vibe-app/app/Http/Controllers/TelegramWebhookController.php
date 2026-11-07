<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Receives what is written in the Customer Services Telegram chat: a reply to a forwarded message (or "#12 text") becomes the answer shown to the user on the Support page. */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramService $telegram)
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
        $close = preg_match('/^\/close(?:@\w+)?(?:\s+#?(\d+))?$/i', $text, $closing) === 1;
        if ($repliedTo = data_get($message, 'reply_to_message.message_id')) {
            $support = SupportMessage::query()->where('telegram_message_id', (int) $repliedTo)->first();
        }
        if (! $support && $close && isset($closing[1])) {
            $support = SupportMessage::query()->find((int) $closing[1]);
        }
        if (! $support && ! $close && preg_match('/^#(\d+)\s+(.+)$/s', $text, $parts)) {
            $support = SupportMessage::query()->find((int) $parts[1]);
            $text = trim($parts[2]);
        }

        if ($support && $close) {
            $support->update(['status' => 'closed']);
            $telegram->send("🔒 Support #{$support->id} closed", $support->telegram_message_id);
        } elseif ($support) {
            $support->replies()->create(['body' => $text, 'telegram_message_id' => $message['message_id'] ?? null]);
            $support->update(['status' => 'answered']);
            // Marks the question as answered in the chat, so nobody has to scroll to find what is still open.
            $telegram->send("✅ Support #{$support->id} answered", $support->telegram_message_id);
        } elseif (! str_starts_with($text, '/') || $close) {
            // Never guess: a message that cannot be tied to one ticket is not sent to any user.
            Log::warning('Telegram staff message matched no support ticket and was not delivered.', ['telegram_message_id' => $message['message_id'] ?? null]);
            $telegram->send('This message was not delivered to any user: it is not tied to a ticket. Reply to the ticket message itself, or start with the ticket number, for example "#12 your answer".', isset($message['message_id']) ? (int) $message['message_id'] : null);
        }

        return response()->json(['ok' => true]);
    }
}
