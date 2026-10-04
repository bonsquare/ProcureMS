<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOrganization;

class ProcurementRequestItem extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = ['organization_id', 'procurement_request_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function procurementRequest() { return $this->belongsTo(ProcurementRequest::class); }
}
