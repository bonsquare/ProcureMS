<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Services\SupportCard;
use App\Services\SupportDesk;
use App\Services\SupportTopic;
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
    private const COMMANDS = 'assign|process|pending|hold|approve|resolve|close|queue|mine';

    public function handle(Request $request, string $secret, TelegramService $telegram, SupportDesk $desk)
    {
        $expected = (string) config('services.telegram.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);
        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        abort_unless($header === '' || hash_equals($expected, $header), 403);

        if (is_array($request->input('callback_query'))) {
            $this->button((array) $request->input('callback_query'), $telegram, $desk);

            return response()->json(['ok' => true]);
        }

        $message = $request->input('message');
        // Only the Customer Services chat can answer.
        if (! is_array($message) || (string) data_get($message, 'chat.id') !== (string) config('services.telegram.chat_id')) {
            return response()->json(['ok' => true]);
        }
        $this->learnStaff((array) ($message['from'] ?? []), $desk);
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
            $this->say($telegram, $support, "{$support->ticket_no} is resolved. Your message was not delivered.", $messageId);
        } elseif ($support) {
            $support->replies()->create(['body' => $text, 'sender' => 'staff', 'telegram_message_id' => $messageId]);
            if ($support->status === 'pending') {
                $support->update(['status' => 'for_process']);
                $this->refreshCard($support->fresh(), $telegram);
            }
            // Marks the question as answered in the chat, so nobody has to scroll to find what is still open.
            $this->say($telegram, $support, "✅ {$support->ticket_no} answered");
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

        $actorId = (int) ($from['id'] ?? 0);
        $actor = (string) ($from['first_name'] ?? 'Staff');
        $wasOpen = SupportStatus::isOpen($support->status);
        if ($name === 'assign' && preg_match('/@(\w+)/', $argument, $who)) {
            $desk->assignToName($support, '@'.$who[1]);
            $line = "👤 {$support->ticket_no} · @{$who[1]}";
        } else {
            $line = $desk->apply($support, $name === 'assign' ? 'take' : $name, $actorId, $actor);
        }
        $telegram->send($line, $anchor);
        $this->refreshCard($support->fresh(), $telegram, $wasOpen ? $line : null);
    }

    /** A tap on one of the buttons under a request card. */
    private function button(array $callback, TelegramService $telegram, SupportDesk $desk): void
    {
        if ((string) data_get($callback, 'message.chat.id') !== (string) config('services.telegram.chat_id')) {
            return;
        }
        $from = (array) ($callback['from'] ?? []);
        $this->learnStaff($from, $desk);
        $callbackId = (string) ($callback['id'] ?? '');
        $actorId = (int) ($from['id'] ?? 0);
        $actor = (string) ($from['first_name'] ?? 'Staff');
        if (! preg_match('/^s:(\d+):(\w+)(?::(\d+))?$/', (string) ($callback['data'] ?? ''), $parts) || ! ($support = SupportMessage::query()->find((int) $parts[1]))) {
            $telegram->answer($callbackId);

            return;
        }
        [, , $action] = $parts;
        $cardId = (int) ($support->telegram_message_id ?: data_get($callback, 'message.message_id'));
        $wasOpen = SupportStatus::isOpen($support->status);
        $telegram->answer($callbackId);

        if ($action === 'pick' && SupportStatus::isOpen($support->status)) {
            $telegram->editKeyboard($cardId, SupportCard::staffKeyboard($support, $desk->staff()));

            return;
        }
        if ($action === 'back') {
            $telegram->editKeyboard($cardId, SupportCard::keyboard($support));

            return;
        }
        if ($action === 'to') {
            $staffId = (int) ($parts[3] ?? 0);
            $name = $desk->staff()[$staffId] ?? null;
            if ($name === null) {
                return;
            }
            $line = $desk->reassign($support, $staffId, $name, $actor);
        } elseif (in_array($action, ['take', 'pending', 'process', 'hold', 'approve', 'resolve', 'close'], true)) {
            $line = $desk->apply($support, $action, $actorId, $actor);
        } else {
            return;
        }
        $telegram->send($line, $cardId);
        $this->refreshCard($support->fresh(), $telegram, $wasOpen ? $line : null);
    }

    /** Rewrites the request card so it always shows the current status and assignee (and no buttons once finished). */
    private function refreshCard(SupportMessage $support, TelegramService $telegram, ?string $line = null): void
    {
        // The topic opens once someone holds the request and closes when the request has just ended ($line is the record of that change).
        $topics = app(SupportTopic::class);
        if (! SupportStatus::isOpen($support->status)) {
            if ($line !== null) {
                $topics->close($support, $line);
            }
        } else {
            $support = $topics->ensure($support);
        }
        if ($support->telegram_message_id) {
            $telegram->edit((int) $support->telegram_message_id, SupportCard::text($support), SupportCard::keyboard($support));
        }
    }

    /** Anyone writing or tapping in the support group is staff, and is offered under "Assign to…". */
    private function learnStaff(array $from, SupportDesk $desk): void
    {
        if (! empty($from['id']) && empty($from['is_bot'])) {
            $desk->rememberStaff((int) $from['id'], (string) ($from['first_name'] ?? $from['username'] ?? 'Staff'));
        }
    }

    /** Writes into the request's topic when it has one, otherwise under its card (or as a reply to $fallback). */
    private function say(TelegramService $telegram, SupportMessage $support, string $text, ?int $fallback = null): void
    {
        if ($support->telegram_thread_id) {
            $telegram->send($text, null, null, (int) $support->telegram_thread_id);

            return;
        }
        $telegram->send($text, $support->telegram_message_id ?: $fallback);
    }

    private function repliedTicket(array $message): ?SupportMessage
    {
        $thread = (int) data_get($message, 'message_thread_id');
        if ($thread > 0 && ($inTopic = SupportMessage::query()->where('telegram_thread_id', $thread)->first())) {
            return $inTopic;
        }
        // A reply to a line the bot posted under a card (a record or an "answered" marker) counts as a reply to that card.
        $ids = array_filter([data_get($message, 'reply_to_message.message_id'), data_get($message, 'reply_to_message.reply_to_message.message_id')]);

        return $ids ? SupportMessage::query()->whereIn('telegram_message_id', array_map('intval', $ids))->first() : null;
    }
}
