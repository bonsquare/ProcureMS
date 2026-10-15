<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementRequest extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['organization_id', 'school_id', 'master_transaction_id', 'requested_by', 'request_number', 'title', 'description', 'transaction_description', 'amount', 'entity_name', 'department_name', 'section', 'sai_number', 'sai_date', 'responsibility_center_code', 'source_of_fund', 'extra_blank_rows', 'status', 'requested_at', 'approved_at', 'budget_allocation_id', 'aip_activity_id', 'signatories', 'not_required_documents'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'sai_date' => 'date', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'signatories' => 'array', 'not_required_documents' => 'array'];
    }

    /** The Purpose of the Purchase Request as it is printed on it (the transaction description for the project title); the ORS takes the same text. */
    public function purposeText(): string
    {
        $docs = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $description = $this->transaction_description ?: data_get($docs->firstWhere('document_type', 'request_for_quotation')?->metadata, 'transaction_description');

        return collect([$description, $this->title])->filter()->implode(' for ');
    }

    /** The Particulars of the ORS made from the Purchase Order: the Purpose of the request, "as per PO No." and the number of the PO without its letters. */
    public function orsParticulars(): string
    {
        $number = preg_replace('/^PO-?/i', '', (string) $this->purchaseOrder()?->document_number);

        return $this->purposeText().($number !== '' ? ' as per PO No. '.$number : '');
    }

    /** The Purchase Order of this request: the obligation (ORS) is made from it. */
    public function purchaseOrder(): ?ProcurementDocument
    {
        $docs = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $docs->firstWhere('document_type', 'purchase_order');
    }

    /** What the Purchase Order obligates: the awarded (winning) amount of the PO, or the request's amount while there is no PO. */
    public function obligationAmount(): float
    {
        $meta = $this->purchaseOrder()?->metadata ?? [];

        return (float) ($meta['winning_bid_amount'] ?? $this->amount);
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

    /** The SOB activity this request is made for. */
    public function activity()
    {
        return $this->belongsTo(AipActivity::class, 'aip_activity_id');
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
