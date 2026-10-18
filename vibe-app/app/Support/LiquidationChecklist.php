<?php

namespace App\Support;

/**
 * The standard papers of a liquidation, by kind of expense. A paper is needed at a stage: `ors` before the ORS is approved, `dv` before the DV is created
 * (what only exists after delivery or the trip). `doc` is the procurement document that proves it by itself; a paper without one is ticked by hand.
 */
class LiquidationChecklist
{
    public const KINDS = ['goods' => 'Goods (from a Purchase Request)', 'direct' => 'Direct payment (utilities, services)', 'travel' => 'Travel', 'other' => 'Other'];

    public const STAGES = ['ors' => 'Before the ORS is approved', 'dv' => 'Before the DV is created'];

    /** @return array<int, array{key: string, label: string, stage: string, doc: ?string}> */
    public static function standard(string $kind): array
    {
        $doc = fn (string $type, string $label, string $stage = 'ors') => ['key' => $type, 'label' => $label, 'stage' => $stage, 'doc' => $type];
        $hand = fn (string $key, string $label, string $stage) => ['key' => $key, 'label' => $label, 'stage' => $stage, 'doc' => null];

        return match ($kind) {
            'goods' => [
                $doc('request_for_quotation', 'Request for Quotation'),
                $doc('abstract_of_bids_quotation', 'Abstract of Bids or Quotation'),
                $doc('notice_to_award', 'Notice to Award'),
                $doc('notice_to_proceed', 'Notice to Proceed'),
                $doc('purchase_order', 'Purchase Order'),
                $doc('inspection_acceptance_report', 'Inspection and Acceptance Report', 'dv'),
                $hand('sales_invoice', 'Sales invoice or official receipt', 'dv'),
                $hand('delivery_receipt', 'Delivery receipt', 'dv'),
            ],
            'direct' => [
                $hand('billing_statement', 'Billing statement or official receipt', 'ors'),
                $hand('proof_of_service', 'Proof of service rendered', 'dv'),
            ],
            'travel' => [
                $hand('travel_order', 'Travel order', 'ors'),
                $hand('itinerary_of_travel', 'Itinerary of travel', 'ors'),
                $hand('certificate_of_appearance', 'Certificate of appearance', 'dv'),
                $hand('travel_receipts', 'Receipts or approved trip ticket', 'dv'),
            ],
            default => [
                $hand('supporting_paper', 'Supporting paper', 'ors'),
            ],
        };
    }
}
