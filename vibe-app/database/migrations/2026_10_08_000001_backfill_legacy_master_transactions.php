<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('budget_allocations')->whereNull('master_transaction_id')->orderBy('id')->get() as $budget) {
                $aip = $budget->aip_id ? DB::table('aips')->find($budget->aip_id) : null;
                $transactionId = $aip && (int) $aip->organization_id === (int) $budget->organization_id
                    && (int) $aip->school_id === (int) $budget->school_id
                    ? $aip->master_transaction_id
                    : null;

                if (! $transactionId) {
                    $transactionId = $this->createTransaction(
                        (int) $budget->organization_id,
                        (int) $budget->school_id,
                        (int) $budget->fiscal_year,
                        $budget->program ?: $budget->particulars ?: 'Legacy Budget Allocation',
                        'budget',
                        $budget->created_by,
                        'budget',
                        'legacy_budget_linked',
                        ['budget_allocation_id' => $budget->id],
                    );
                }

                DB::table('budget_allocations')->where('id', $budget->id)->update(['master_transaction_id' => $transactionId]);
            }

            foreach (DB::table('procurement_requests')->whereNull('master_transaction_id')->orderBy('id')->get() as $request) {
                $budget = $request->budget_allocation_id ? DB::table('budget_allocations')->find($request->budget_allocation_id) : null;
                $transactionId = $budget
                    && (int) $budget->organization_id === (int) $request->organization_id
                    && (int) $budget->school_id === (int) $request->school_id
                    ? $budget->master_transaction_id
                    : null;

                if (! $transactionId) {
                    $year = (int) substr((string) ($request->requested_at ?? $request->created_at), 0, 4);
                    $transactionId = $this->createTransaction(
                        (int) $request->organization_id,
                        (int) $request->school_id,
                        $year ?: (int) now()->year,
                        $request->title ?: 'Legacy Procurement Request',
                        'procurement',
                        $request->requested_by,
                        'procurement',
                        'legacy_pr_linked',
                        ['procurement_request_id' => $request->id, 'request_number' => $request->request_number],
                    );
                }

                DB::table('procurement_requests')->where('id', $request->id)->update(['master_transaction_id' => $transactionId]);
                DB::table('master_transactions')->where('id', $transactionId)->update(['status' => 'procurement', 'updated_at' => now()]);
            }

            foreach (DB::table('liquidation_reports')->whereNull('master_transaction_id')->orderBy('id')->get() as $report) {
                $request = $report->procurement_request_id ? DB::table('procurement_requests')->find($report->procurement_request_id) : null;
                $budget = $report->budget_allocation_id ? DB::table('budget_allocations')->find($report->budget_allocation_id) : null;
                $parent = $request && (int) $request->organization_id === (int) $report->organization_id
                    && (int) $request->school_id === (int) $report->school_id
                    ? $request
                    : ($budget && (int) $budget->organization_id === (int) $report->organization_id
                        && (int) $budget->school_id === (int) $report->school_id ? $budget : null);
                $transactionId = $parent?->master_transaction_id;

                if (! $transactionId) {
                    $year = (int) substr((string) $report->created_at, 0, 4);
                    $transactionId = $this->createTransaction(
                        (int) $report->organization_id,
                        (int) $report->school_id,
                        $year ?: (int) now()->year,
                        $report->purpose ?: 'Legacy Liquidation Report',
                        $report->paid_at ? 'completed' : 'liquidation',
                        $report->submitted_by,
                        'liquidation',
                        'legacy_ors_linked',
                        ['liquidation_report_id' => $report->id, 'ors_number' => $report->ors_number ?: $report->report_number],
                    );
                }

                DB::table('liquidation_reports')->where('id', $report->id)->update(['master_transaction_id' => $transactionId]);
                DB::table('master_transactions')->where('id', $transactionId)->update([
                    'status' => $report->paid_at ? 'completed' : 'liquidation',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function createTransaction(
        int $organizationId,
        int $schoolId,
        int $year,
        string $title,
        string $status,
        ?int $createdBy,
        string $module,
        string $action,
        array $metadata,
    ): int {
        $counterKey = [
            'organization_id' => $organizationId,
            'document_type' => 'master_transaction',
            'fiscal_year' => $year,
        ];
        $lastNumber = (int) (DB::table('document_counters')->where($counterKey)->value('last_number') ?? 0);
        $existingMaximum = DB::table('master_transactions')
            ->where('organization_id', $organizationId)
            ->where('fiscal_year', $year)
            ->pluck('transaction_number')
            ->map(fn (string $number) => preg_match('/-(\d+)$/', $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;
        $number = max($lastNumber, $existingMaximum) + 1;
        $now = now();

        DB::table('document_counters')->updateOrInsert($counterKey, [
            'last_number' => $number,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transactionId = DB::table('master_transactions')->insertGetId([
            'organization_id' => $organizationId,
            'school_id' => $schoolId,
            'transaction_number' => sprintf('TXN-%d-%06d', $year, $number),
            'fiscal_year' => $year,
            'title' => Str::limit($title, 255, ''),
            'description' => 'Legacy business records linked during transaction-history backfill.',
            'status' => $status,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('transaction_events')->insert([
            'organization_id' => $organizationId,
            'master_transaction_id' => $transactionId,
            'user_id' => $createdBy,
            'module' => $module,
            'action' => $action,
            'new_status' => $status,
            'remarks' => 'Existing record connected to its transaction history.',
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $transactionId;
    }

    public function down(): void
    {
        // This data backfill is intentionally retained on rollback so historical links and audit events are not erased.
    }
};
