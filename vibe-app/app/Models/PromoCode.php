<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** A promo code the master creates; a school types it to pay less for the Annual plan. */
class PromoCode extends Model
{
    protected $fillable = ['code', 'type', 'value', 'starts_at', 'ends_at', 'max_uses', 'used_count', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'starts_at' => 'date', 'ends_at' => 'date', 'max_uses' => 'integer', 'used_count' => 'integer', 'active' => 'boolean'];
    }

    /** A code is kept in upper case, so "save" and "SAVE" are the same code. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /** Active, inside its dates (the end date itself counts) and not used up. */
    public function usableAt(?CarbonInterface $at = null): bool
    {
        $day = ($at ?? now())->copy()->startOfDay();

        return $this->active
            && (! $this->starts_at || $this->starts_at->startOfDay()->lte($day))
            && (! $this->ends_at || $this->ends_at->startOfDay()->gte($day))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    /** The price of the list price with this code taken off. */
    public function priceFor(float $listPrice): float
    {
        $off = $this->type === 'percent' ? $listPrice * ((float) $this->value / 100) : (float) $this->value;

        return round($listPrice - $off, 2);
    }
}
