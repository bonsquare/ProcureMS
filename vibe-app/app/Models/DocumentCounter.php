<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{
    protected $fillable = ['organization_id', 'document_type', 'fiscal_year', 'last_number'];
}
