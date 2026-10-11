<?php

use App\Models\CseCatalogItem;
use App\Services\CseCatalogLoader;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Loads the 2026 Procurement Service catalog (from the owner's APP-CSE workbook) so a deploy fills it. A later year is loaded with `php artisan cse:import-catalog`. */
    public function up(): void
    {
        if (CseCatalogItem::where('catalog_year', 2026)->exists()) {
            return;
        }

        app(CseCatalogLoader::class)->load(json_decode((string) file_get_contents(database_path('data/cse-catalog-2026.json')), true, 512, JSON_THROW_ON_ERROR), 2026);
    }

    public function down(): void
    {
        CseCatalogItem::where('catalog_year', 2026)->delete();
    }
};
