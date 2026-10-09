<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToOrganization;

    /** Units every school starts with; they cannot be removed. */
    public const DEFAULTS = ['piece', 'box', 'pack', 'ream', 'set', 'liter'];

    protected $fillable = ['organization_id', 'name'];

    /**
     * Every unit available in item dropdowns: the built-in ones plus the organization's own.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::DEFAULTS)
            ->merge(self::query()->orderBy('name')->pluck('name'))
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values()
            ->all();
    }
}
