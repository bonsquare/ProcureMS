<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Procurement Service catalog item of a catalog year. Shared reference data: not tenant-scoped and never edited by a school. */
class CseCatalogItem extends Model
{
    protected $fillable = ['catalog_year', 'group_heading', 'category', 'code', 'description', 'unit', 'price', 'sort_order'];

    protected function casts(): array
    {
        return ['catalog_year' => 'integer', 'sort_order' => 'integer', 'price' => 'decimal:2'];
    }
}
