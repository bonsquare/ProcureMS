<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Support\SupportStatus;

/** The Telegram topic of a request: opened in the group when the request is taken, closed when it ends. Without topics in the group everything stays under the card. */
class SupportTopic
{
    public function __construct(private TelegramService $telegram) {}

    /** Opens the topic once the request has someone assigned, and starts it with a header (status, assignee, the client's message, buttons). Returns the request as it is now. */
    public function ensure(SupportMessage $request): SupportMessage
    {
        if ($request->telegram_thread_id || ! SupportStatus::isOpen($request->status) || ! $request->assigned_name) {
            return $request;
        }
        $thread = $this->telegram->createTopic($request->ticket_no.' · '.($request->subject ?: 'Support request'));
        if ($thread === null) {
            return $request;
        }
        $request->update(['telegram_thread_id' => $thread]);
        $header = $this->telegram->send(SupportCard::topicText($request), null, SupportCard::keyboard($request), $thread);
        $request->update(['telegram_thread_card_id' => $header]);

        return $request->fresh();
    }

    /** Rewrites the header of the topic with the current status and assignee (no buttons once the request has ended). */
    public function refresh(SupportMessage $request): void
    {
        if ($request->telegram_thread_card_id) {
            $this->telegram->edit((int) $request->telegram_thread_card_id, SupportCard::topicText($request), SupportCard::keyboard($request));
        }
    }

    /** Posts a line in the topic (a record of a change). */
    public function note(SupportMessage $request, string $line): void
    {
        if ($request->telegram_thread_id) {
            $this->telegram->send($line, null, null, (int) $request->telegram_thread_id);
        }
    }

    /** Says how the conversation ended and closes the topic. */
    public function close(SupportMessage $request, string $line): void
    {
        if (! $request->telegram_thread_id) {
            return;
        }
        $this->note($request, $line."\nThis conversation is closed.");
        $this->telegram->closeTopic((int) $request->telegram_thread_id);
    }
}
