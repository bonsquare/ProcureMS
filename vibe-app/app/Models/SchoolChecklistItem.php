<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** A paper a school adds to the liquidation checklist of one kind of expense, on top of the standard papers. */
class SchoolChecklistItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'ors_kind', 'stage', 'label', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /** The key under which its tick is kept in liquidation_document_checks. */
    public function key(): string
    {
        return 'school_'.$this->id;
    }
}
