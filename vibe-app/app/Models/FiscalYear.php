<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'year', 'status', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'closed_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
