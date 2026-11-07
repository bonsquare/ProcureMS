<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Support\SupportStatus;

/** The request card the bot posts in the main Telegram group: its text (always the current status and assignee) and its buttons. */
final class SupportCard
{
    /** Ticket number on top, then the subject, then who asked; the rest follows. */
    public static function text(SupportMessage $request): string
    {
        $request->loadMissing(['user', 'school', 'attachments']);
        $files = $request->attachments->map(fn ($file) => "📎 {$file->name} — ".app(GoogleDriveService::class)->webLink($file->drive_file_id))->implode("\n");

        return ($request->type === 'subscription' ? '🧾' : '🆕')." New Support Request\n"
            ."🎫 {$request->ticket_no}\n"
            ."📝 Subject: {$request->subject}\n"
            .'👤 From: '.($request->user?->name ?? '—')."\n"
            .'🗂 Category: '.($request->type === 'subscription' ? 'Subscription' : 'General help')."\n"
            .SupportStatus::icon($request->status).' Status: '.SupportStatus::label($request->status)."\n"
            .'🙋 Assigned: '.($request->assigned_name ?: 'nobody yet')."\n"
            .($request->telegram_thread_id ? '💬 Conversation: https://t.me/c/'.ltrim(str_replace('-100', '', (string) config('services.telegram.chat_id')), '-').'/'.$request->telegram_thread_id."\n" : '')
            ."🕒 Filed: {$request->filed_at}\n"
            .'🏫 School: '.($request->school?->name ?? '—')."\n\n"
            ."Issue\n{$request->body}\n"
            .($files !== '' ? "\nAttachment\n{$files}\n" : '')
            ."\n↩️ To reply: Reply directly to this message. Your reply will be sent to the requester."
            ."\n🔘 Use the buttons below to take it, assign it and set its status."
            ."\n✅ To close: Send /close {$request->id}";
    }

    /** The header at the top of a request's topic: ticket, subject, requester, the current status and assignee, the client's message. */
    public static function topicText(SupportMessage $request): string
    {
        $request->loadMissing(['user', 'school', 'attachments']);
        $files = $request->attachments->map(fn ($file) => "\n📎 {$file->name} — ".app(GoogleDriveService::class)->webLink($file->drive_file_id))->implode('');

        return "💬 {$request->ticket_no}\n"
            ."📝 Subject: {$request->subject}\n"
            .'👤 From: '.($request->user?->name ?? '—').' · 🏫 '.($request->school?->name ?? '—')."\n"
            .SupportStatus::icon($request->status).' Status: '.SupportStatus::label($request->status)."\n"
            .'🙋 Assigned: '.($request->assigned_name ?: 'nobody yet')
            ."\n\n{$request->body}{$files}\n\n"
            .'Write here to answer the client: your messages are sent to them. Use the buttons to set the status; every change is also recorded in the main group.';
    }

    /** @return list<list<array{text: string, callback_data: string}>> */
    public static function keyboard(SupportMessage $request): array
    {
        if (! SupportStatus::isOpen($request->status)) {
            return [];
        }
        $button = fn (string $label, string $action) => ['text' => $label, 'callback_data' => "s:{$request->id}:{$action}"];

        // Two buttons per row, so each label gets half the screen width on a phone.
        return [
            [$button('🙋 Take it', 'take'), $button('👥 Assign to…', 'pick')],
            [$button('🟡 Pending', 'pending'), $button('▶️ On process', 'process')],
            [$button('⏸ On hold', 'hold'), $button('✅ Resolve', 'resolve')],
            [$button('🔒 Close', 'close')],
        ];
    }

    /**
     * One button per staff member the group has seen, with how many open requests each holds.
     *
     * @param  array<int, string>  $staff  Telegram id => name
     * @param  bool  $fromList  true on a message made only to pick the assignee (the typed /assign lists)
     * @return list<list<array{text: string, callback_data: string}>>
     */
    public static function staffKeyboard(SupportMessage $request, array $staff, bool $fromList = false): array
    {
        // From a typed /assign list the picked name replaces that list message; from any other message the buttons are put back.
        $to = $fromList ? 'lto' : 'to';
        $rows = [];
        foreach (array_chunk(array_keys($staff), 2) as $pair) {
            $rows[] = array_map(function (int $id) use ($request, $staff, $to) {
                $open = SupportMessage::withoutGlobalScopes()->where('assigned_telegram_id', $id)->where('status', '!=', 'resolved')->count();

                return ['text' => '👤 '.$staff[$id].' ('.$open.')', 'callback_data' => "s:{$request->id}:{$to}:{$id}"];
            }, $pair);
        }
        $rows[] = [['text' => '⬅️ Back', 'callback_data' => "s:{$request->id}:".($fromList ? 'lback' : 'back')]];

        return $rows;
    }
}
