<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['organization_code', 'name', 'slug', 'status', 'fiscal_year', 'default_fund_source', 'numbering_preferences'];

    protected function casts(): array
    {
        return ['fiscal_year' => 'integer', 'numbering_preferences' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function schools()
    {
        return $this->hasMany(School::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
