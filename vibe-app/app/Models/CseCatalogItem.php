<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Procurement Service catalog item of a catalog year. Shared reference data: not tenant-scoped and never edited by a school. */
class CseCatalogItem extends Model
{
    protected $fillable = ['organization_id', 'catalog_year', 'group_heading', 'category', 'code', 'description', 'unit', 'price', 'sort_order'];

    /** The shared catalog plus the items this organization typed by hand for Part I. */
    public function scopeVisibleTo($query, ?int $organizationId)
    {
        return $query->where(fn ($where) => $where->whereNull('organization_id')->when($organizationId, fn ($inner) => $inner->orWhere('organization_id', $organizationId)));
    }

    protected function casts(): array
    {
        return ['catalog_year' => 'integer', 'sort_order' => 'integer', 'price' => 'decimal:2'];
    }
}
