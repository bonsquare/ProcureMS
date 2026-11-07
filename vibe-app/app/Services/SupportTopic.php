<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Support\SupportStatus;

/** The Telegram topic of a request: opened in the group when the request is taken, closed when it ends. Without topics in the group everything stays under the card. */
class SupportTopic
{
    public function __construct(private TelegramService $telegram) {}

    /** Opens the topic once the request has someone assigned, and starts it with the client's message. Returns the request as it is now. */
    public function ensure(SupportMessage $request): SupportMessage
    {
        if ($request->telegram_thread_id || ! SupportStatus::isOpen($request->status) || ! $request->assigned_name) {
            return $request;
        }
        $request->loadMissing(['user', 'school', 'attachments']);
        $thread = $this->telegram->createTopic($request->ticket_no.' · '.($request->subject ?: 'Support request'));
        if ($thread === null) {
            return $request;
        }
        $request->update(['telegram_thread_id' => $thread]);
        $files = $request->attachments->map(fn ($file) => "\n📎 {$file->name} — ".app(GoogleDriveService::class)->webLink($file->drive_file_id))->implode('');
        $this->telegram->send("💬 {$request->ticket_no} · {$request->subject}\n👤 ".($request->user?->name ?? '—').' · 🏫 '.($request->school?->name ?? '—')."\n\n{$request->body}{$files}\n\nWrite here to answer the client: your messages are sent to them. Assignment and status are recorded in the main group.", null, null, $thread);

        return $request->fresh();
    }

    /** Says how the conversation ended and closes the topic. */
    public function close(SupportMessage $request, string $line): void
    {
        if (! $request->telegram_thread_id) {
            return;
        }
        $this->telegram->send($line."\nThis conversation is closed.", null, null, (int) $request->telegram_thread_id);
        $this->telegram->closeTopic((int) $request->telegram_thread_id);
    }
}
