<?php

namespace App\Services;

use App\Models\AppItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppItemLinkService
{
    /** PR statuses that release the quantity and cost they reserved against an APP item. */
    private const RELEASED_STATUSES = ['returned', 'rejected'];

    /** Approved APP items of a school that a PR line can be charged to, with what is still unrequested. */
    public function available(int $schoolId, ?int $ignorePrId = null): Collection
    {
        return AppItem::with('plan')
            ->whereHas('plan', fn ($query) => $query->where('school_id', $schoolId)->where('status', 'approved'))
            ->orderBy('procurement_item')
            ->get()
            ->map(function (AppItem $item) use ($ignorePrId) {
                [$quantity, $cost] = $this->requested($item, $ignorePrId);
                $item->remaining_quantity = round((float) $item->quantity - $quantity, 2);
                $item->remaining_cost = round((float) $item->estimated_total_cost - $cost, 2);

                return $item;
            });
    }

    /**
     * Every linked line must point to an approved APP item of the PR's school and stay within
     * the quantity and estimated cost the APP has left.
     */
    public function assertWithinApp(int $schoolId, array $items, ?int $ignorePrId = null): void
    {
        $lines = collect($items)->filter(fn ($item) => ! empty($item['app_item_id']))->groupBy('app_item_id');
        foreach ($lines as $appItemId => $group) {
            $key = 'items';
            $appItem = AppItem::with('plan')->find($appItemId);
            if (! $appItem || $appItem->plan->school_id !== $schoolId || $appItem->plan->status !== 'approved') {
                throw ValidationException::withMessages([$key => 'Linked items must come from an approved APP of this school.']);
            }
            [$usedQuantity, $usedCost] = $this->requested($appItem, $ignorePrId);
            $quantity = $group->sum(fn ($line) => (float) $line['quantity']);
            $cost = $group->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']);
            if (round($usedQuantity + $quantity, 2) > round((float) $appItem->quantity, 2)) {
                throw ValidationException::withMessages([$key => "{$appItem->procurement_item}: quantity exceeds the APP ({$appItem->quantity} {$appItem->unit} planned, ".round($usedQuantity, 2).' already requested).']);
            }
            if (round($usedCost + $cost, 2) > round((float) $appItem->estimated_total_cost, 2)) {
                throw ValidationException::withMessages([$key => "{$appItem->procurement_item}: cost exceeds the APP estimate of ₱".number_format((float) $appItem->estimated_total_cost, 2).'.']);
            }
        }
    }

    /** Log the PR on the planning transaction of every APP item it draws from. */
    public function recordLinks(ProcurementRequest $procurementRequest): void
    {
        $procurementRequest->load('items.appItem.ppmpItem.plan.transaction');
        foreach ($procurementRequest->items->filter(fn ($line) => $line->appItem)->groupBy('app_item_id') as $lines) {
            $appItem = $lines->first()->appItem;
            $appItem->ppmpItem?->plan?->transaction?->recordEvent(
                'app', 'pr_linked', null, $procurementRequest->status,
                $procurementRequest->request_number.' · '.$appItem->procurement_item,
                ['procurement_request_id' => $procurementRequest->id, 'app_item_id' => $appItem->id, 'quantity' => (float) $lines->sum('quantity')],
            );
        }
    }

    /** @return array{0: float, 1: float} quantity and cost already requested against an APP item */
    private function requested(AppItem $item, ?int $ignorePrId): array
    {
        $lines = ProcurementRequestItem::where('app_item_id', $item->id)
            ->whereHas('procurementRequest', function ($query) use ($ignorePrId) {
                $query->whereNotIn('status', self::RELEASED_STATUSES);
                if ($ignorePrId) {
                    $query->where('id', '!=', $ignorePrId);
                }
            })->get();

        return [(float) $lines->sum('quantity'), (float) $lines->sum('total')];
    }
}
