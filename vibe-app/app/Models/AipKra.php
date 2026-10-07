<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AipKra extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'aip_id', 'pillar', 'kra', 'intermediate_outcome', 'strategy', 'five_point_agenda', 'program'];

    public function aip()
    {
        return $this->belongsTo(Aip::class);
    }

    public function activities()
    {
        return $this->hasMany(AipActivity::class, 'aip_kra_id')->orderBy('id');
    }
}
