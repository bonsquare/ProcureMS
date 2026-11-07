<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\SystemSetting;
use App\Support\SupportStatus;
use Illuminate\Support\Collection;

/** The rules staff work by in the Customer Services Telegram chat: who has a request, its status, and what is still waiting. */
class SupportDesk
{
    private const QUEUE_LIMIT = 50;

    /** What each button or command does: the status it sets, its mark and its name in the record. */
    private const ACTIONS = [
        'pending' => ['pending', '🟡', 'Pending'],
        'process' => ['for_process', '▶️', 'On process'],
        'hold' => ['on_hold', '⏸', 'On hold'],
        'approve' => ['approved', '👍', 'Approved'],
        'resolve' => ['resolved', '✅', 'Resolved'],
        'close' => ['resolved', '🔒', 'Closed'],
    ];

    /** Does a button or command: "take" or a status action. Returns the line to record in the group. */
    public function apply(SupportMessage $request, string $action, int $actorId, string $actorName): string
    {
        if (! SupportStatus::isOpen($request->status)) {
            return "{$request->ticket_no} is resolved.";
        }
        if ($action === 'take') {
            if (! $this->assign($request, $actorId, $actorName)) {
                return $request->fresh()->assigned_name." already has {$request->ticket_no}.";
            }

            return $this->record('👤', $request, 'Assigned to '.$request->fresh()->assigned_name, $actorName);
        }
        [$status, $icon, $label] = self::ACTIONS[$action];
        $this->setStatus($request, $status);

        return $this->record($icon, $request, $label, $actorName);
    }

    /** Gives the request to a staff member, replacing whoever held it. Returns the line to record in the group. */
    public function reassign(SupportMessage $request, int $staffId, string $staffName, string $actorName): string
    {
        if (! SupportStatus::isOpen($request->status)) {
            return "{$request->ticket_no} is resolved.";
        }
        $request->update(['assigned_telegram_id' => $staffId, 'assigned_name' => mb_substr($staffName, 0, 80)]);

        return $this->record('👤', $request, 'Assigned to '.$staffName, $actorName);
    }

    private function record(string $icon, SupportMessage $request, string $what, string $actorName): string
    {
        return "{$icon} {$request->ticket_no} · {$what} — by {$actorName} · ".now()->format('M d, g:i A');
    }

    /** Remembers a person seen in the group, so "Assign to…" can offer them. */
    public function rememberStaff(int $telegramId, string $name): void
    {
        $staff = $this->staff();
        if ($telegramId > 0 && ($staff[$telegramId] ?? null) !== $name) {
            $staff[$telegramId] = mb_substr($name, 0, 80);
            SystemSetting::put('support_staff', json_encode($staff));
        }
    }

    /** @return array<int, string> Telegram id => name */
    public function staff(): array
    {
        $list = json_decode((string) SystemSetting::get('support_staff', '{}'), true);

        return collect(is_array($list) ? $list : [])->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])->all();
    }

    /** Takes the request for this person. Only one person can win: the update succeeds only while nobody holds it. */
    public function assign(SupportMessage $request, int $telegramId, string $name): bool
    {
        $won = SupportMessage::withoutGlobalScopes()->whereKey($request->id)->whereNull('assigned_telegram_id')
            ->update(['assigned_telegram_id' => $telegramId, 'assigned_name' => mb_substr($name, 0, 80)]) === 1;

        return $won || (int) $request->fresh()->assigned_telegram_id === $telegramId;
    }

    /** Gives the request to a named person (for example "@ben"); it counts as taken, so nobody else can take it. */
    public function assignToName(SupportMessage $request, string $name): void
    {
        $request->update(['assigned_name' => mb_substr($name, 0, 80), 'assigned_telegram_id' => 0]);
    }

    /** Moves the request to a status. A resolved request stays resolved, and an unknown status is refused. */
    public function setStatus(SupportMessage $request, string $status): bool
    {
        if (! in_array($status, SupportStatus::ALL, true) || ($request->status === 'resolved' && $status !== 'resolved')) {
            return false;
        }
        $request->update(['status' => $status, 'resolved_at' => $status === 'resolved' ? ($request->resolved_at ?? now()) : null]);

        return true;
    }

    /** "/assign" with no request in sight: the open requests as buttons (unassigned first); a tap then offers the staff. */
    public function openRequestsPrompt(): array
    {
        $open = $this->open()->sortBy(fn (SupportMessage $request) => $request->assigned_telegram_id === null ? 0 : 1)->take(10);
        if ($open->isEmpty()) {
            return ['text' => 'Nothing is waiting.', 'keyboard' => null];
        }

        return ['text' => '👥 Which request do you want to assign?', 'keyboard' => $open->map(fn (SupportMessage $request) => [[
            'text' => '#'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT).' · '.mb_substr($request->subject ?: 'Support request', 0, 28).($request->assigned_name ? ' · '.$this->firstName($request->assigned_name) : ''),
            'callback_data' => "s:{$request->id}:lpick",
        ]])->values()->all()];
    }

    /** Every request that is not resolved, by status, with the unassigned ones first. */
    public function queue(): string
    {
        return $this->list('📋 Support queue', $this->open());
    }

    /** The open requests held by this person (by Telegram id, or by "@username" when it was assigned by name). */
    public function mine(int $telegramId, ?string $username = null): string
    {
        $open = $this->open()->filter(fn (SupportMessage $request) => ($telegramId > 0 && (int) $request->assigned_telegram_id === $telegramId)
            || ($username && mb_strtolower((string) $request->assigned_name) === '@'.mb_strtolower(ltrim($username, '@'))));

        return $this->list('📌 Your requests', $open);
    }

    /** @return Collection<int, SupportMessage> */
    private function open(): Collection
    {
        return SupportMessage::withoutGlobalScopes()->with('school')->where('status', '!=', 'resolved')->orderBy('id')->get();
    }

    private function list(string $title, Collection $requests): string
    {
        if ($requests->isEmpty()) {
            return "{$title}\n\nNothing is waiting.";
        }
        $lines = [$title.' ('.$requests->count().')'];
        $shown = 0;
        foreach (array_diff(SupportStatus::ALL, ['resolved']) as $status) {
            $group = $requests->where('status', $status)->sortBy(fn (SupportMessage $request) => $request->assigned_telegram_id === null ? 0 : 1)->values();
            if ($group->isEmpty() || $shown >= self::QUEUE_LIMIT) {
                continue;
            }
            $lines[] = '';
            $lines[] = SupportStatus::label($status).':';
            foreach ($group as $request) {
                if ($shown++ >= self::QUEUE_LIMIT) {
                    break;
                }
                $lines[] = ($request->type === 'subscription' ? '🧾 ' : '').$request->ticket_no.' · '.($request->subject ?: 'Support request').' · '.($request->school?->name ?? '—').' · '.($request->assigned_name ? $this->firstName($request->assigned_name) : 'unassigned').' · '.$request->created_at->diffForHumans(null, true, true);
            }
        }
        if ($requests->count() > self::QUEUE_LIMIT) {
            $lines[] = '';
            $lines[] = '+'.($requests->count() - self::QUEUE_LIMIT).' more';
        }

        return implode("\n", $lines);
    }

    private function firstName(string $name): string
    {
        return str_starts_with($name, '@') ? $name : (explode(' ', trim($name))[0] ?: $name);
    }
}
