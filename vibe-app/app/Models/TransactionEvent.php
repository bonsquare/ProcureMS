<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TransactionEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'master_transaction_id', 'user_id', 'module', 'action', 'previous_status', 'new_status', 'remarks', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
