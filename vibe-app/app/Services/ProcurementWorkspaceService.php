<?php

namespace App\Services;

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

    private const REQUIRED_DOCUMENTS = [
        'request_for_quotation' => 'Request for Quotation',
        'abstract_of_bids_quotation' => 'Abstract of Bids or Quotation',
        'notice_to_award' => 'Notice to Award',
        'notice_to_proceed' => 'Notice to Proceed',
        'purchase_order' => 'Purchase Order',
        'inspection_acceptance_report' => 'Inspection and Acceptance Report',
    ];

    public function present(ProcurementRequest $request): array
    {
        $documents = $request->relationLoaded('documents') ? $request->documents : collect();
        $currentStage = $this->currentStage($request, $documents);

        return [
            'current_stage' => $currentStage,
            'stages' => $this->stages($request, $currentStage),
            'next_action' => $this->nextAction($currentStage, (string) $request->status),
            'documents' => $this->documentCompleteness($documents),
            'delivery' => $this->deliverySummary($request, $documents),
        ];
    }

    private function currentStage(ProcurementRequest $request, Collection $documents): string
    {
        $status = (string) $request->status;
        $types = $documents->pluck('document_type');

        if ($status === 'completed') {
            return 'complete';
        }

        if ($types->contains('inspection_acceptance_report') || $types->contains('inventory_acknowledgement_receipt_supplies')) {
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

    private function deliverySummary(ProcurementRequest $request, Collection $documents): array
    {
        $items = $request->relationLoaded('items') ? $request->items : collect();
        $iar = $documents->firstWhere('document_type', 'inspection_acceptance_report');
        $ordered = (float) $items->sum(fn ($item) => (float) $item->quantity);

        if (! $iar || $items->isEmpty()) {
            return [
                'ordered_quantity' => $ordered,
                'received_quantity' => 0.0,
                'balance_quantity' => $ordered,
                'status' => 'not_started',
                'label' => 'Not yet received',
                'tone' => 'neutral',
            ];
        }

        $receivedItems = $iar->metadata['received_items'] ?? [];
        $received = (float) $items->sum(function ($item) use ($receivedItems) {
            $quantity = $receivedItems[(string) $item->id] ?? $receivedItems[$item->id] ?? $item->quantity;

            return $quantity === '' || $quantity === null ? (float) $item->quantity : (float) $quantity;
        });
        $balance = max(0.0, $ordered - $received);

        return [
            'ordered_quantity' => $ordered,
            'received_quantity' => $received,
            'balance_quantity' => $balance,
            'status' => $balance > 0 ? 'partial' : 'complete',
            'label' => $balance > 0 ? 'Partial delivery' : 'Delivery complete',
            'tone' => $balance > 0 ? 'attention' : 'verified',
        ];
    }
}
