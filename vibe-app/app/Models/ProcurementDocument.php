<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class ProcurementDocument extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'procurement_request_id',
        'created_by',
        'document_type',
        'document_number',
        'document_date',
        'supplier_or_recipient',
        'notes',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'metadata' => 'array'];
    }

    public function procurementRequest()
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
