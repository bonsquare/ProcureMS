<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementRequest extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['organization_id', 'school_id', 'master_transaction_id', 'requested_by', 'request_number', 'title', 'description', 'transaction_description', 'amount', 'entity_name', 'department_name', 'section', 'sai_number', 'sai_date', 'responsibility_center_code', 'source_of_fund', 'extra_blank_rows', 'status', 'requested_at', 'approved_at', 'budget_allocation_id', 'signatories'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'sai_date' => 'date', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'signatories' => 'array'];
    }

    /**
     * The awarded supplier (payee) for this PR: Purchase Order first, then Notice to Award / Proceed,
     * with address and TIN taken from the award metadata or the supplier master list.
     */
    public function awardedPayee(): ?array
    {
        $docs = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $doc = collect(['purchase_order', 'notice_to_award', 'notice_to_proceed'])
            ->map(fn ($type) => $docs->first(fn ($d) => $d->document_type === $type && $d->supplier_or_recipient))
            ->filter()->first();

        if (! $doc) {
            return null;
        }

        $meta = $doc->metadata ?? [];
        $supplier = Supplier::where('business_name', $doc->supplier_or_recipient)->first();

        return [
            'name' => $doc->supplier_or_recipient,
            'address' => $meta['supplier_address'] ?? $supplier?->business_address,
            'tin' => $meta['supplier_tin'] ?? $meta['tin'] ?? $supplier?->tin,
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function budgetAllocation()
    {
        return $this->belongsTo(BudgetAllocation::class);
    }

    public function transaction()
    {
        return $this->belongsTo(MasterTransaction::class, 'master_transaction_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function liquidationReports()
    {
        return $this->hasMany(LiquidationReport::class);
    }

    public function items()
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }

    public function documents()
    {
        return $this->hasMany(ProcurementDocument::class);
    }
}
