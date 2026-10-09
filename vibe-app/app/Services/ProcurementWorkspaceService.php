<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use Illuminate\Support\Collection;

class ProcurementWorkspaceService
{
    private const STAGES = [
        'request' => 'Request',
        'approval' => 'Approval',
        'canvass' => 'Canvass',
        'award' => 'Award',
        'purchase_order' => 'Purchase Order',
        'receiving' => 'Receiving',
        'complete' => 'Complete',
    ];

    /** The required documents in the order they are prepared. */
    public const REQUIRED_DOCUMENTS = [
        'request_for_quotation' => 'Request for Quotation',
        'abstract_of_bids_quotation' => 'Abstract of Bids or Quotation',
        'notice_to_award' => 'Notice to Award',
        'notice_to_proceed' => 'Notice to Proceed',
        'purchase_order' => 'Purchase Order',
        'inspection_acceptance_report' => 'Inspection and Acceptance Report',
        'inventory_acknowledgement_receipt_supplies' => 'Inventory and Acknowledgement Receipt of Supplies',
        'requisition_issuance_slip' => 'Requisition and Issuance Slip',
    ];

    /** Prepared only when needed; they never hold up completion. */
    public const OPTIONAL_DOCUMENTS = [
        'inventory_custodian_slip' => 'Inventory Custodian Slip',
        'property_acknowledgement_receipt' => 'Property Acknowledgement Receipt',
    ];

    /** Each document can be prepared only after the one before it. */
    public const PREREQUISITES = [
        'abstract_of_bids_quotation' => 'request_for_quotation',
        'notice_to_award' => 'abstract_of_bids_quotation',
        'notice_to_proceed' => 'notice_to_award',
        'purchase_order' => 'notice_to_proceed',
        'inspection_acceptance_report' => 'purchase_order',
        'inventory_acknowledgement_receipt_supplies' => 'inspection_acceptance_report',
        'requisition_issuance_slip' => 'inventory_acknowledgement_receipt_supplies',
        'inventory_custodian_slip' => 'requisition_issuance_slip',
        'property_acknowledgement_receipt' => 'requisition_issuance_slip',
    ];

    /** Documents prepared once the delivery arrives; they live under the Receiving tab. */
    public const RECEIVING_DOCUMENT_TYPES = [
        'inspection_acceptance_report',
        'inventory_acknowledgement_receipt_supplies',
        'requisition_issuance_slip',
        'inventory_custodian_slip',
        'property_acknowledgement_receipt',
    ];

    /**
     * The single source of truth for where a request stands: its stage and its document progress.
     * Every list that shows "stage" or "progress" must read this, never compute its own.
     *
     * @return array{stage: string, stage_label: string, steps_done: int, steps_total: int, delivered: bool}
     */
    public function summary(ProcurementRequest $request): array
    {
        $documents = $request->relationLoaded('documents') ? $request->documents : $request->documents()->get();
        $stage = $this->currentStage($request, $documents);
        $completeness = $this->documentCompleteness($documents);

        return [
            'stage' => $stage,
            'stage_label' => self::STAGES[$stage],
            'steps_done' => $completeness['completed'],
            'steps_total' => $completeness['total'],
            'delivered' => $stage === 'complete',
        ];
    }

    /**
     * Why a document cannot be prepared yet, or null when it can. Order: approved request, then the document before it.
     *
     * @param  Collection<int, ProcurementDocument>|null  $documents
     */
    public function blockedReason(ProcurementRequest $request, string $type, ?Collection $documents = null): ?string
    {
        if (! in_array((string) $request->status, ['approved', 'for_canvass', 'completed'], true)) {
            return 'Approve the purchase request first.';
        }

        $required = self::PREREQUISITES[$type] ?? null;
        if ($required === null) {
            return null;
        }

        $documents ??= $request->documents()->get();
        if ($documents->contains('document_type', $required)) {
            return null;
        }

        return 'Prepare the '.(self::REQUIRED_DOCUMENTS[$required] ?? self::OPTIONAL_DOCUMENTS[$required] ?? 'previous document').' first.';
    }

    public function present(ProcurementRequest $request): array
    {
        $documents = $request->relationLoaded('documents') ? $request->documents : collect();
        $currentStage = $this->currentStage($request, $documents);

        return [
            'current_stage' => $currentStage,
            'stages' => $this->stages($request, $currentStage),
            'next_action' => $this->nextAction($currentStage, (string) $request->status),
            'documents' => $this->documentCompleteness($documents),
            'delivery' => $this->deliverySummary($request),
        ];
    }

    public function receiving(ProcurementRequest $request): array
    {
        $documents = $request->relationLoaded('documents') ? $request->documents : collect();
        $items = $request->relationLoaded('items') ? $request->items : collect();
        $purchaseOrder = $documents->firstWhere('document_type', 'purchase_order');
        $iar = $documents->firstWhere('document_type', 'inspection_acceptance_report');
        $receivedItems = $iar?->metadata['received_items'] ?? [];
        $poMetadata = $purchaseOrder?->metadata ?? [];

        $rows = $items->map(function ($item) use ($iar, $receivedItems, $poMetadata) {
            $ordered = (float) $item->quantity;
            $received = $iar ? ($receivedItems[(string) $item->id] ?? $receivedItems[$item->id] ?? $ordered) : 0;
            $received = $received === '' || $received === null ? $ordered : (float) $received;
            $balance = max(0, $ordered - $received);
            $unitCost = is_numeric(data_get($poMetadata, 'awarded_item_prices.'.$item->id))
                ? (float) data_get($poMetadata, 'awarded_item_prices.'.$item->id)
                : (float) $item->unit_price;

            return [
                'stock_no' => $item->id,
                'description' => trim($item->name.($item->description ? ' ('.$item->description.')' : '')),
                'unit' => $item->unit,
                'po_quantity' => $ordered,
                'received_quantity' => $received,
                'balance' => $balance,
                'unit_cost' => $unitCost,
                'po_amount' => $ordered * $unitCost,
                'received_amount' => $received * $unitCost,
                'status' => $balance > 0 ? 'Partial' : 'Complete',
            ];
        })->values();

        $status = ! $purchaseOrder && ! $iar ? 'missing_both' : (! $purchaseOrder ? 'missing_po' : (! $iar ? 'missing_iar' : ($rows->sum('balance') > 0 ? 'partial' : 'complete')));

        return [
            'rows' => $rows,
            'ordered_quantity' => (float) $rows->sum('po_quantity'),
            'received_quantity' => (float) $rows->sum('received_quantity'),
            'balance_quantity' => (float) $rows->sum('balance'),
            'total_po_amount' => (float) $rows->sum('po_amount'),
            'total_received_amount' => (float) $rows->sum('received_amount'),
            'status' => $status,
            'label' => match ($status) {
                'complete' => 'Delivery complete',
                'partial' => 'Partial delivery',
                'missing_po' => 'Missing PO',
                'missing_iar' => 'Missing IAR',
                default => 'Missing PO and IAR',
            },
            'tone' => match ($status) {
                'complete' => 'verified', 'partial' => 'attention', default => 'neutral'
            },
            'supplier' => $purchaseOrder?->supplier_or_recipient,
            'purchase_order' => $purchaseOrder,
            'latest_receiving_document' => $iar,
        ];
    }

    private function currentStage(ProcurementRequest $request, Collection $documents): string
    {
        $status = (string) $request->status;
        $types = $documents->pluck('document_type');

        // A request only counts as complete once every required document exists; a status flipped early is not enough.
        if ($status === 'completed' && $this->documentCompleteness($documents)['is_complete']) {
            return 'complete';
        }

        if ($types->intersect(['inspection_acceptance_report', 'inventory_acknowledgement_receipt_supplies', 'requisition_issuance_slip'])->isNotEmpty()) {
            return 'receiving';
        }

        if ($types->contains('purchase_order')) {
            return 'purchase_order';
        }

        if ($types->intersect(['abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed'])->isNotEmpty()) {
            return 'award';
        }

        if ($status === 'for_canvass' || $status === 'approved' || $types->contains('request_for_quotation')) {
            return 'canvass';
        }

        if (in_array($status, ['submitted', 'pending_approval'], true)) {
            return 'approval';
        }

        return 'request';
    }

    private function stages(ProcurementRequest $request, string $currentStage): array
    {
        $keys = array_keys(self::STAGES);
        $currentIndex = array_search($currentStage, $keys, true);
        $blocked = in_array((string) $request->status, ['returned', 'rejected'], true);

        return collect(self::STAGES)->map(function (string $label, string $key) use ($keys, $currentIndex, $blocked, $currentStage) {
            $index = array_search($key, $keys, true);
            $state = $index < $currentIndex ? 'complete' : ($key === $currentStage ? 'current' : 'pending');

            if ($blocked && $key === $currentStage) {
                $state = 'blocked';
            }

            $tone = match ($state) {
                'complete' => 'verified',
                'current' => 'action',
                'blocked' => 'exception',
                default => 'neutral',
            };

            return [
                'key' => $key,
                'label' => $label,
                'state' => $state,
                'tone' => $tone,
                'accessible_label' => $label.' '.match ($state) {
                    'complete' => 'completed',
                    'current' => 'current stage',
                    'blocked' => 'blocked',
                    default => 'pending',
                },
            ];
        })->values()->all();
    }

    private function nextAction(string $stage, string $status): array
    {
        if (in_array($status, ['returned', 'rejected'], true)) {
            return [
                'action' => 'edit_request',
                'label' => 'Resolve request issues',
                'tone' => 'exception',
            ];
        }

        return match ($stage) {
            'request' => ['action' => 'edit_request', 'label' => 'Complete request', 'tone' => 'action'],
            'approval' => ['action' => 'review_request', 'label' => 'Review approval', 'tone' => 'attention'],
            'canvass' => ['action' => 'manage_documents', 'label' => 'Create or review quotations', 'tone' => 'action'],
            'award' => ['action' => 'manage_documents', 'label' => 'Record award', 'tone' => 'action'],
            'purchase_order' => ['action' => 'manage_documents', 'label' => 'Prepare receiving', 'tone' => 'action'],
            'receiving' => ['action' => 'manage_receiving', 'label' => 'Reconcile delivery', 'tone' => 'attention'],
            default => ['action' => 'view_documents', 'label' => 'Review completed record', 'tone' => 'verified'],
        };
    }

    private function documentCompleteness(Collection $documents): array
    {
        $existingTypes = $documents->pluck('document_type')->unique();
        $missing = collect(self::REQUIRED_DOCUMENTS)
            ->reject(fn (string $label, string $type) => $existingTypes->contains($type))
            ->map(fn (string $label, string $type) => ['type' => $type, 'label' => $label])
            ->values();

        return [
            'completed' => count(self::REQUIRED_DOCUMENTS) - $missing->count(),
            'total' => count(self::REQUIRED_DOCUMENTS),
            'missing' => $missing->all(),
            'is_complete' => $missing->isEmpty(),
        ];
    }

    private function deliverySummary(ProcurementRequest $request): array
    {
        $summary = $this->receiving($request);

        return collect($summary)->only(['ordered_quantity', 'received_quantity', 'balance_quantity', 'status', 'label', 'tone'])->all();
    }
}
