<?php

namespace Tests\Unit;

use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcurementWorkspaceServiceTest extends TestCase
{
    public static function workflowCases(): array
    {
        return [
            'draft request' => ['draft', [], 'request', 'Complete request'],
            'submitted request' => ['submitted', [], 'approval', 'Review approval'],
            'pending approval request' => ['pending_approval', [], 'approval', 'Review approval'],
            'request for canvass' => ['for_canvass', [], 'canvass', 'Create or review quotations'],
            'RFQ starts canvass' => ['approved', ['request_for_quotation'], 'canvass', 'Create or review quotations'],
            'abstract starts award' => ['approved', ['abstract_of_bids_quotation'], 'award', 'Record award'],
            'notice starts award' => ['approved', ['notice_to_award'], 'award', 'Record award'],
            'purchase order starts ordering' => ['approved', ['purchase_order'], 'purchase_order', 'Prepare receiving'],
            'inspection report starts receiving' => ['approved', ['purchase_order', 'inspection_acceptance_report'], 'receiving', 'Reconcile delivery'],
            'completed request' => ['completed', ['purchase_order', 'inspection_acceptance_report'], 'complete', 'Review completed record'],
        ];
    }

    #[DataProvider('workflowCases')]
    public function test_it_derives_the_current_stage_and_next_action(
        string $status,
        array $documentTypes,
        string $expectedStage,
        string $expectedActionLabel,
    ): void {
        $request = $this->request($status, $documentTypes);

        $workspace = (new ProcurementWorkspaceService)->present($request);

        $this->assertSame($expectedStage, $workspace['current_stage']);
        $this->assertSame($expectedActionLabel, $workspace['next_action']['label']);
        $this->assertNotEmpty($workspace['next_action']['action']);
        $this->assertSame(
            ['request', 'approval', 'canvass', 'award', 'purchase_order', 'receiving', 'complete'],
            array_column($workspace['stages'], 'key'),
        );
        $this->assertSame(1, collect($workspace['stages'])->where('state', 'current')->count());
        $this->assertNotEmpty(collect($workspace['stages'])->firstWhere('key', $expectedStage)['label']);
        $this->assertNotEmpty(collect($workspace['stages'])->firstWhere('key', $expectedStage)['tone']);
    }

    public function test_it_marks_prior_and_later_stages_without_skipping_the_current_stage(): void
    {
        $workspace = (new ProcurementWorkspaceService)->present(
            $this->request('approved', ['purchase_order']),
        );

        $states = collect($workspace['stages'])->pluck('state', 'key')->all();

        $this->assertSame('complete', $states['request']);
        $this->assertSame('complete', $states['approval']);
        $this->assertSame('complete', $states['canvass']);
        $this->assertSame('complete', $states['award']);
        $this->assertSame('current', $states['purchase_order']);
        $this->assertSame('pending', $states['receiving']);
        $this->assertSame('pending', $states['complete']);
    }

    public function test_it_marks_a_returned_request_as_blocked_with_an_accessible_label(): void
    {
        $workspace = (new ProcurementWorkspaceService)->present($this->request('returned', []));
        $requestStage = collect($workspace['stages'])->firstWhere('key', 'request');

        $this->assertSame('request', $workspace['current_stage']);
        $this->assertSame('blocked', $requestStage['state']);
        $this->assertSame('Request blocked', $requestStage['accessible_label']);
        $this->assertSame('exception', $requestStage['tone']);
    }

    public function test_it_reports_required_document_completeness(): void
    {
        $workspace = (new ProcurementWorkspaceService)->present(
            $this->request('approved', [
                'request_for_quotation',
                'abstract_of_bids_quotation',
                'notice_to_award',
                'purchase_order',
            ]),
        );

        $this->assertSame(4, $workspace['documents']['completed']);
        $this->assertSame(6, $workspace['documents']['total']);
        $this->assertSame(
            ['notice_to_proceed', 'inspection_acceptance_report'],
            array_column($workspace['documents']['missing'], 'type'),
        );
    }

    public function test_it_summarizes_partial_delivery_from_item_and_iar_metadata(): void
    {
        $request = $this->request('approved', ['purchase_order']);
        $request->setRelation('items', new Collection([
            $this->item(11, 10),
            $this->item(12, 5),
        ]));
        $request->setRelation('documents', new Collection([
            $this->document('purchase_order'),
            $this->document('inspection_acceptance_report', [
                'received_items' => ['11' => 8, '12' => 5],
            ]),
        ]));

        $delivery = (new ProcurementWorkspaceService)->present($request)['delivery'];

        $this->assertSame(15.0, $delivery['ordered_quantity']);
        $this->assertSame(13.0, $delivery['received_quantity']);
        $this->assertSame(2.0, $delivery['balance_quantity']);
        $this->assertSame('partial', $delivery['status']);
        $this->assertSame('Partial delivery', $delivery['label']);
        $this->assertSame('attention', $delivery['tone']);
    }

    private function request(string $status, array $documentTypes): ProcurementRequest
    {
        $request = new ProcurementRequest(['status' => $status]);
        $request->setRelation('documents', new Collection(array_map(
            fn (string $type) => $this->document($type),
            $documentTypes,
        )));
        $request->setRelation('items', new Collection);

        return $request;
    }

    private function document(string $type, array $metadata = []): ProcurementDocument
    {
        return new ProcurementDocument([
            'document_type' => $type,
            'metadata' => $metadata,
        ]);
    }

    private function item(int $id, float $quantity): ProcurementRequestItem
    {
        $item = new ProcurementRequestItem(['quantity' => $quantity]);
        $item->setAttribute('id', $id);

        return $item;
    }
}
