<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['procurement_request_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function procurementRequest() { return $this->belongsTo(ProcurementRequest::class); }
}
