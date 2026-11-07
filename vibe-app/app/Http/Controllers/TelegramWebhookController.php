<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Services\SupportDesk;
use App\Services\TelegramService;
use App\Support\SupportStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives what staff write in the Customer Services Telegram chat. A reply to a request message (or "#12 text") is the answer shown to the user;
 * commands take a request (/assign), set its status (/process /hold /approve /resolve) and list what is waiting (/queue /mine).
 */
class TelegramWebhookController extends Controller
{
    private const COMMANDS = 'assign|process|hold|approve|resolve|close|queue|mine';

    private const STATUS_OF = ['process' => 'for_process', 'hold' => 'on_hold', 'approve' => 'approved', 'resolve' => 'resolved', 'close' => 'resolved'];

    private const CONFIRM = ['for_process' => '▶ %s on process', 'on_hold' => '⏸ %s on hold', 'approved' => '👍 %s approved', 'resolved' => '✅ %s resolved'];

    public function handle(Request $request, string $secret, TelegramService $telegram, SupportDesk $desk)
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
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;

        if (preg_match('/^\/('.self::COMMANDS.')(?:@\w+)?(?:\s+(.+))?$/is', $text, $command)) {
            $this->command(strtolower($command[1]), trim($command[2] ?? ''), $message, $messageId, $telegram, $desk);

            return response()->json(['ok' => true]);
        }

        $support = $this->repliedTicket($message);
        if (! $support && preg_match('/^(?:#|SUP-\d{8}-\d{4}-)(\d+)\s+(.+)$/s', $text, $parts)) {
            $support = SupportMessage::query()->find((int) $parts[1]);
            $text = trim($parts[2]);
        }

        if ($support && ! SupportStatus::isOpen($support->status)) {
            $telegram->send("{$support->ticket_no} is resolved. Your message was not delivered.", $messageId);
        } elseif ($support) {
            $support->replies()->create(['body' => $text, 'sender' => 'staff', 'telegram_message_id' => $messageId]);
            if ($support->status === 'pending') {
                $support->update(['status' => 'for_process']);
            }
            // Marks the question as answered in the chat, so nobody has to scroll to find what is still open.
            $telegram->send("✅ {$support->ticket_no} answered", $support->telegram_message_id);
        } elseif (! str_starts_with($text, '/')) {
            // Never guess: a message that cannot be tied to one ticket is not sent to any user.
            Log::warning('Telegram staff message matched no support ticket and was not delivered.', ['telegram_message_id' => $messageId]);
            $telegram->send('This message was not delivered to any user: it is not tied to a ticket. Reply to the ticket message itself, or start with the ticket number, for example "#12 your answer".', $messageId);
        }

        return response()->json(['ok' => true]);
    }

    private function command(string $name, string $argument, array $message, ?int $messageId, TelegramService $telegram, SupportDesk $desk): void
    {
        $from = (array) ($message['from'] ?? []);
        if ($name === 'queue') {
            $telegram->send($desk->queue(), $messageId);

            return;
        }
        if ($name === 'mine') {
            $telegram->send($desk->mine((int) ($from['id'] ?? 0), $from['username'] ?? null), $messageId);

            return;
        }

        $support = $this->repliedTicket($message);
        if (! $support && preg_match('/(?:SUP-\d{8}-\d{4}-|#)?(\d+)/', $argument, $number)) {
            $support = SupportMessage::query()->find((int) $number[1]);
        }
        if (! $support) {
            Log::warning('Telegram support command matched no ticket.', ['command' => $name]);
            $telegram->send("/{$name} was not applied: reply to the ticket message, or add its number, for example \"/{$name} 12\".", $messageId);

            return;
        }
        $anchor = $support->telegram_message_id ? (int) $support->telegram_message_id : $messageId;
        if (! SupportStatus::isOpen($support->status)) {
            $telegram->send("{$support->ticket_no} is resolved.", $anchor);

            return;
        }

        if ($name === 'assign') {
            if (preg_match('/@(\w+)/', $argument, $who)) {
                $desk->assignToName($support, '@'.$who[1]);
                $telegram->send("👤 {$support->ticket_no} · @{$who[1]}", $anchor);
            } elseif ($desk->assign($support, (int) ($from['id'] ?? 0), (string) ($from['first_name'] ?? 'Staff'))) {
                $telegram->send("👤 {$support->ticket_no} · ".($support->fresh()->assigned_name), $anchor);
            } else {
                $telegram->send($support->fresh()->assigned_name." already has {$support->ticket_no}.", $anchor);
            }

            return;
        }

        $status = self::STATUS_OF[$name];
        $desk->setStatus($support, $status);
        $by = $support->fresh()->assigned_name;
        $telegram->send(sprintf(self::CONFIRM[$status], $support->ticket_no).($by ? ' · '.$by : ''), $anchor);
    }

    private function repliedTicket(array $message): ?SupportMessage
    {
        $repliedTo = data_get($message, 'reply_to_message.message_id');

        return $repliedTo ? SupportMessage::query()->where('telegram_message_id', (int) $repliedTo)->first() : null;
    }
}
