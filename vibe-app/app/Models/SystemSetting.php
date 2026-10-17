<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** A switch of the whole system (key and value), set by the master user. */
class SystemSetting extends Model
{
    public const SCREEN_OFF = 'screen_off';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** True while the master user has turned the screen off. */
    public static function screenIsOff(): bool
    {
        try {
            return static::get(self::SCREEN_OFF) === '1';
        } catch (QueryException) {
            // The table does not exist yet (before the migration ran): the screen is on.
            return false;
        }
    }
}
