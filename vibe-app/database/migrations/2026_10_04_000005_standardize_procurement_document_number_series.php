<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $codes = [
            'request_for_quotation' => 'RFQ',
            'abstract_of_bids_quotation' => 'ABQ',
            'notice_to_award' => 'NOA',
            'purchase_order' => 'PO',
            'notice_to_proceed' => 'NTP',
            'inspection_acceptance_report' => 'IAR',
            'requisition_issuance_slip' => 'RIS',
            'inventory_acknowledgement_receipt_supplies' => 'IARS',
            'inventory_custodian_slip' => 'ICS',
            'property_acknowledgement_receipt' => 'PAR',
        ];

        DB::transaction(function () use ($codes) {
            $requests = DB::table('procurement_requests')
                ->orderBy('requested_at')
                ->orderBy('id')
                ->get();
            foreach ($requests as $request) {
                DB::table('procurement_requests')->where('id', $request->id)->update(['request_number' => "TMP-PR-{$request->id}"]);
            }
            $requestSequences = [];
            foreach ($requests as $request) {
                $year = substr((string) ($request->requested_at ?: $request->created_at), 0, 4) ?: now()->format('Y');
                $sequence = ($requestSequences[$year] ?? 0) + 1;
                $requestSequences[$year] = $sequence;
                DB::table('procurement_requests')->where('id', $request->id)->update(['request_number' => sprintf('PR-%s-%03d', $year, $sequence)]);
            }

            $documents = DB::table('procurement_documents')
                ->orderBy('document_type')
                ->orderBy('document_date')
                ->orderBy('id')
                ->get();
            foreach ($documents as $document) {
                DB::table('procurement_documents')->where('id', $document->id)->update(['document_number' => "TMP-DOC-{$document->id}"]);
            }
            $documentSequences = [];
            foreach ($documents as $document) {
                $year = substr((string) ($document->document_date ?: $document->created_at), 0, 4) ?: now()->format('Y');
                $code = $codes[$document->document_type] ?? strtoupper($document->document_type);
                $key = "{$code}-{$year}";
                $sequence = ($documentSequences[$key] ?? 0) + 1;
                $documentSequences[$key] = $sequence;
                DB::table('procurement_documents')->where('id', $document->id)->update(['document_number' => sprintf('%s-%s-%03d', $code, $year, $sequence)]);
            }
        });
    }

    public function down(): void
    {
        // Existing document numbers cannot be safely reconstructed after standardization.
    }
};
