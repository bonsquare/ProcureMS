<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class MasterTransaction extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'school_id', 'transaction_number', 'fiscal_year', 'title', 'description', 'status', 'created_by'];

    public function events()
    {
        return $this->hasMany(TransactionEvent::class)->latest();
    }

    public function aip()
    {
        return $this->hasOne(Aip::class);
    }

    public function sipProjects()
    {
        return $this->hasMany(SipProject::class, 'master_transaction_id');
    }

    public function budgetAllocations()
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function procurementRequests()
    {
        return $this->hasMany(ProcurementRequest::class);
    }

    public function liquidationReports()
    {
        return $this->hasMany(LiquidationReport::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function recordEvent(string $module, string $action, ?string $previous = null, ?string $next = null, ?string $remarks = null, array $metadata = []): TransactionEvent
    {
        return $this->events()->create([
            'organization_id' => $this->organization_id,
            'user_id' => auth()->id(),
            'module' => $module,
            'action' => $action,
            'previous_status' => $previous,
            'new_status' => $next,
            'remarks' => $remarks,
            'metadata' => $metadata ?: null,
        ]);
    }
}
