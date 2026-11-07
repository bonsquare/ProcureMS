<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** The page margins (mm) a school prints with: no doc_key is its default, a doc_key is one document. */
class PrintSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'doc_key', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
