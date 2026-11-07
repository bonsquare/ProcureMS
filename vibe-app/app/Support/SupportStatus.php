<?php

namespace App\Support;

/** The life of a support request: new, being worked, waiting, approved, finished. Resolved ends the chat. */
final class SupportStatus
{
    public const ALL = ['pending', 'for_process', 'on_hold', 'approved', 'resolved'];

    private const LABELS = ['pending' => 'Pending', 'for_process' => 'On process', 'on_hold' => 'On hold', 'approved' => 'Approved', 'resolved' => 'Resolved'];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? 'Pending';
    }

    public static function isOpen(string $status): bool
    {
        return $status !== 'resolved';
    }

    /** What the three statuses used before this version are called now. */
    public static function fromLegacy(string $status): string
    {
        return ['open' => 'pending', 'answered' => 'for_process', 'closed' => 'resolved'][$status] ?? $status;
    }
}
