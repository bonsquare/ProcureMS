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

    public const SCREEN_OFF_SECTIONS = 'screen_off_sections';

    /** @return array<int, string> the tabs the master user has switched off */
    public static function sectionsOff(): array
    {
        try {
            $list = json_decode((string) static::get(self::SCREEN_OFF_SECTIONS, '[]'), true);
        } catch (QueryException) {
            return [];
        }

        return is_array($list) ? array_values(array_intersect(\App\Support\ScreenSections::keys(), $list)) : [];
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
