<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'code', 'title', 'category'];

    public const STANDARD_CATEGORIES = ['Personnel Services', 'MOOE', 'Financial Expenses', 'Direct Costs', 'Non-Cash Expenses', 'Capital Outlay'];

    /** Expense accounts from the Revised Chart of Accounts (UACS, COA Annex A Vol. III). */
    public static function standardAccounts(): array
    {
        return json_decode(file_get_contents(database_path('chart_of_accounts.json')), true);
    }

    public static function ensureDefaults(?int $organizationId): void
    {
        if (static::withoutGlobalScopes()->where('organization_id', $organizationId)->exists()) {
            return;
        }

        $now = now();
        foreach (array_chunk(self::standardAccounts(), 200) as $chunk) {
            static::withoutGlobalScopes()->insert(array_map(
                fn (array $account) => $account + ['organization_id' => $organizationId, 'created_at' => $now, 'updated_at' => $now],
                $chunk
            ));
        }
    }

    /** @return array<int, string> */
    public static function categories(?int $organizationId): array
    {
        return collect(self::STANDARD_CATEGORIES)
            ->merge(static::withoutGlobalScopes()->where('organization_id', $organizationId)->distinct()->pluck('category'))
            ->unique()->values()->all();
    }
}
