<?php

namespace App\Services;

use App\Models\CseCatalogItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Loads the items of one Procurement Service catalog year. A year that is already loaded is never loaded again. */
class CseCatalogLoader
{
    /**
     * @param  array<int, array{group_heading?: ?string, category: string, code: string, description: string, unit: string, price: float|int|string|null, sort_order: int}>  $items
     */
    public function load(array $items, int $year): int
    {
        if (CseCatalogItem::where('catalog_year', $year)->exists()) {
            throw new RuntimeException("The {$year} catalog is already loaded.");
        }

        return DB::transaction(function () use ($items, $year) {
            $now = now();
            foreach (array_chunk($items, 100) as $chunk) {
                CseCatalogItem::insert(array_map(fn (array $item) => [
                    'catalog_year' => $year,
                    'group_heading' => isset($item['group_heading']) && $item['group_heading'] !== '' ? (string) $item['group_heading'] : null,
                    'category' => (string) $item['category'],
                    'code' => (string) $item['code'],
                    'description' => (string) $item['description'],
                    'unit' => (string) $item['unit'],
                    'price' => isset($item['price']) && is_numeric($item['price']) ? round((float) $item['price'], 2) : null,
                    'sort_order' => (int) $item['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return count($items);
        });
    }
}
