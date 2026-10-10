<?php

namespace App\Support;

/**
 * The access checklist of a Sub-master: which work areas and management areas are on, and four switches.
 * The master edits it per account; anything not in this fixed list is never accepted or stored.
 */
class SubMasterAccess
{
    /** Work areas, with the permission families each one grants. */
    public const WORK_AREAS = [
        'planning' => 'Planning (SIP, AIP, SOB, APP)',
        'budget' => 'Budget',
        'procurement' => 'Procurement',
        'suppliers' => 'Suppliers and units',
        'accounting' => 'Accounting',
        'cash' => 'Cash',
        'liquidation' => 'Liquidation',
        'school_settings' => 'School settings',
    ];

    public const MANAGEMENT_AREAS = [
        'schools' => 'School Management',
        'subscriptions' => 'Subscriptions',
        'transfers' => 'Official Station Management',
        'users' => 'Users',
        'backup' => 'Database backup',
    ];

    public const SWITCHES = [
        'delete' => 'Delete records',
        'deactivate_user' => 'Deactivate users',
        'close_budget' => 'Close a budget',
        'edit_own_name' => 'Change own name and username',
    ];

    private const FAMILIES = [
        'planning' => ['planning.*', 'aip.*'],
        'budget' => ['budget.*'],
        'procurement' => ['procurement.*', 'inspection.*'],
        'suppliers' => ['supplier.*'],
        'accounting' => ['accounting.*'],
        'cash' => ['cash.*'],
        'liquidation' => ['liquidation.*'],
        'school_settings' => ['organization.*'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::WORK_AREAS + self::MANAGEMENT_AREAS + self::SWITCHES);
    }

    /** @return list<string> The 13 areas, no switches. */
    public static function defaults(): array
    {
        return array_keys(self::WORK_AREAS + self::MANAGEMENT_AREAS);
    }

    /** @return list<string> */
    public static function families(string $area): array
    {
        return self::FAMILIES[$area] ?? [];
    }

    /**
     * Keeps only known keys, once each, in the order given. Anything that is not a list of keys gives an empty list.
     *
     * @return list<string>
     */
    public static function sanitize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return array_values(array_unique(array_filter($input, fn ($key) => is_string($key) && in_array($key, self::keys(), true))));
    }
}
