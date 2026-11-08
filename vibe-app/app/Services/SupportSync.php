<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\User;
use App\Support\SupportStatus;

/**
 * What the master user does on a support request inside the system (answer, take, set the status) and the report of it to the Customer Services
 * Telegram chat, so the chat and the system always show the same conversation. The other direction (Telegram to system) is the webhook.
 */
class SupportSync
{
    public function __construct(private TelegramService $telegram, private SupportDesk $desk) {}

    public function telegramOn(): bool
    {
        return $this->telegram->configured();
    }

    /** Saves an answer for the requester and posts it in the request's Telegram topic (or under its card). The first answer moves a pending request to On process. */
    public function reply(SupportMessage $support, string $text, User $by): void
    {
        $support->replies()->create(['body' => $text, 'sender' => 'staff', 'user_id' => $by->id]);
        $support->touch();
        if ($support->status === 'pending') {
            $support->update(['status' => 'for_process']);
        }
        $this->post($support, "🖥 {$support->ticket_no} · answered from the system by {$by->name}\n\n{$text}");
        $this->refreshCard($support->fresh());
    }

    /** "take" gives the request to the master user; any other action is a status the Telegram buttons also set. Returns the line recorded in the chat. */
    public function apply(SupportMessage $support, string $action, User $by): string
    {
        if ($action === 'take') {
            $this->desk->assignToName($support, $by->name);
            $line = "👤 {$support->ticket_no} · Assigned to {$by->name} — by {$by->name} (system) · ".now()->format('M d, g:i A');
        } else {
            $line = $this->desk->apply($support, $action, 0, $by->name.' (system)');
        }
        $this->telegram->send($line, $support->telegram_message_id ? (int) $support->telegram_message_id : null);
        $this->refreshCard($support->fresh(), $line);

        return $line;
    }

    /**
     * Gives the request to someone from the system: "me" (the master user), "none" (back to the queue) or the Telegram id of a staff member the chat has seen.
     * Returns the line recorded in the chat, or null when the person is not in the staff list.
     */
    public function assignTo(SupportMessage $support, string $who, User $by): ?string
    {
        if ($who === 'me') {
            return $this->apply($support, 'take', $by);
        }
        $actor = $by->name.' (system)';
        if ($who === 'none') {
            $support->update(['assigned_name' => null, 'assigned_telegram_id' => null]);
            $line = "↩️ {$support->ticket_no} · Back in the queue — by {$actor} · ".now()->format('M d, g:i A');
        } else {
            $name = ctype_digit($who) && (int) $who > 0 ? ($this->desk->staff()[(int) $who] ?? null) : null;
            if ($name === null) {
                return null;
            }
            $line = $this->desk->reassign($support, (int) $who, $name, $actor);
        }
        $this->telegram->send($line, $support->telegram_message_id ? (int) $support->telegram_message_id : null);
        $this->refreshCard($support->fresh(), $line);

        return $line;
    }

    /** Writes into the request's topic when it has one, otherwise as a reply under its card. */
    private function post(SupportMessage $support, string $text): void
    {
        if ($support->telegram_thread_id) {
            $this->telegram->send($text, null, null, (int) $support->telegram_thread_id);

            return;
        }
        $this->telegram->send($text, $support->telegram_message_id ? (int) $support->telegram_message_id : null);
    }

    /** Rewrites the card and the topic header so they show the current status and assignee; a change that ends the request also closes the topic. */
    private function refreshCard(SupportMessage $support, ?string $line = null): void
    {
        $topics = app(SupportTopic::class);
        if (SupportStatus::isOpen($support->status)) {
            $support = $topics->ensure($support);
        }
        $topics->refresh($support);
        if ($line !== null) {
            SupportStatus::isOpen($support->status) ? $topics->note($support, $line) : $topics->close($support, $line);
        }
        if ($support->telegram_message_id) {
            $this->telegram->edit((int) $support->telegram_message_id, SupportCard::text($support), SupportCard::keyboard($support));
        }
    }
}
