<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\LiquidationReport;
use App\Models\MasterTransaction;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\School;
use App\Models\TransactionEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompleteWorkflowDemoSeeder extends Seeder
{
    private const YEAR = 2026;

    public function run(): void
    {
        $school = School::withoutGlobalScopes()->where('code', 'SCH-TEST')->first();
        $user = $school
            ? User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', 'school_admin')->first()
            : null;

        if (! $school || ! $user) {
            $this->command?->warn('Test School (SCH-TEST) and its admin must exist before seeding workflow data.');

            return;
        }

        Auth::login($user);
        ChartOfAccount::ensureDefaults($school->organization_id);

        DB::transaction(function () use ($school, $user) {
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where('organization_id', $school->organization_id)
                ->where('code', '5020301000')
                ->firstOrFail();

            $allocation = BudgetAllocation::withoutGlobalScopes()->updateOrCreate(
                ['school_id' => $school->id, 'budget_ref_no' => 'DEMO-BA-2026-MOOE'],
                [
                    'organization_id' => $school->organization_id,
                    'created_by' => $user->id,
                    'fiscal_year' => self::YEAR,
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'source_of_fund' => 'MOOE',
                    'fund_name' => 'Maintenance and Other Operating Expenses',
                    'program' => 'Workflow demonstration',
                    'chart_of_account_id' => $account->id,
                    'uacs_code' => $account->code,
                    'particulars' => $account->title,
                    'description' => 'Reusable allocation for the complete workflow demonstration.',
                    'amount' => 200000,
                    'q1_amount' => 50000,
                    'q2_amount' => 50000,
                    'q3_amount' => 50000,
                    'q4_amount' => 50000,
                ],
            );

            foreach ($this->scenarios() as $scenario) {
                $transaction = MasterTransaction::withoutGlobalScopes()->updateOrCreate(
                    [
                        'organization_id' => $school->organization_id,
                        'transaction_number' => 'DEMO-TXN-2026-'.$scenario['sequence'],
                    ],
                    [
                        'school_id' => $school->id,
                        'fiscal_year' => self::YEAR,
                        'title' => $scenario['title'],
                        'description' => 'Seeded workflow stage: '.$scenario['label'],
                        'status' => $scenario['transaction_status'],
                        'created_by' => $user->id,
                    ],
                );

                $request = ProcurementRequest::withoutGlobalScopes()->updateOrCreate(
                    ['request_number' => 'DEMO-PR-2026-'.$scenario['sequence']],
                    [
                        'organization_id' => $school->organization_id,
                        'school_id' => $school->id,
                        'master_transaction_id' => $transaction->id,
                        'requested_by' => $user->id,
                        'budget_allocation_id' => $allocation->id,
                        'title' => $scenario['title'],
                        'description' => 'Clearly labelled synthetic record for local workflow validation.',
                        'transaction_description' => $scenario['label'],
                        'amount' => $scenario['amount'],
                        'source_of_fund' => 'MOOE',
                        'status' => $scenario['pr_status'],
                        'requested_at' => now()->subDays(5),
                        'approved_at' => $scenario['pr_status'] === 'pending_approval' ? null : now()->subDays(4),
                    ],
                );

                ProcurementRequestItem::withoutGlobalScopes()->updateOrCreate(
                    [
                        'procurement_request_id' => $request->id,
                        'name' => 'Demo office supplies '.$scenario['sequence'],
                    ],
                    [
                        'organization_id' => $school->organization_id,
                        'description' => $scenario['label'],
                        'quantity' => 10,
                        'unit' => 'piece',
                        'unit_price' => $scenario['amount'] / 10,
                        'total' => $scenario['amount'],
                    ],
                );

                $report = $this->seedLiquidationReport($school, $user, $allocation, $transaction, $request, $scenario);

                if ($scenario['paid']) {
                    $this->seedCompletionDocuments($request, $user);
                }

                $this->seedTimeline($transaction, $user, $request, $report, $scenario);
            }
        });

        Auth::logout();
    }

    /** @return array<int, array<string, mixed>> */
    private function scenarios(): array
    {
        return [
            ['sequence' => '001', 'label' => 'PR pending approval', 'title' => 'Demo pending procurement request', 'amount' => 1000, 'pr_status' => 'pending_approval', 'ors_status' => null, 'has_dv' => false, 'paid' => false, 'transaction_status' => 'procurement'],
            ['sequence' => '002', 'label' => 'ORS awaiting accounting review', 'title' => 'Demo ORS for accounting review', 'amount' => 2000, 'pr_status' => 'approved', 'ors_status' => 'for_review', 'has_dv' => false, 'paid' => false, 'transaction_status' => 'liquidation'],
            ['sequence' => '003', 'label' => 'Approved ORS awaiting DV', 'title' => 'Demo approved ORS awaiting DV', 'amount' => 3000, 'pr_status' => 'approved', 'ors_status' => 'approved', 'has_dv' => false, 'paid' => false, 'transaction_status' => 'accounting'],
            ['sequence' => '004', 'label' => 'DV awaiting cash payment', 'title' => 'Demo DV awaiting payment', 'amount' => 4000, 'pr_status' => 'approved', 'ors_status' => 'approved', 'has_dv' => true, 'paid' => false, 'transaction_status' => 'ready_for_payment'],
            ['sequence' => '005', 'label' => 'Completed and paid', 'title' => 'Demo completed procurement and payment', 'amount' => 5000, 'pr_status' => 'completed', 'ors_status' => 'approved', 'has_dv' => true, 'paid' => true, 'transaction_status' => 'paid'],
        ];
    }

    /** @param array<string, mixed> $scenario */
    private function seedLiquidationReport(School $school, User $user, BudgetAllocation $allocation, MasterTransaction $transaction, ProcurementRequest $request, array $scenario): ?LiquidationReport
    {
        if (! $scenario['ors_status']) {
            return null;
        }

        return LiquidationReport::withoutGlobalScopes()->updateOrCreate(
            ['report_number' => 'DEMO-LR-2026-'.$scenario['sequence']],
            [
                'organization_id' => $school->organization_id,
                'school_id' => $school->id,
                'master_transaction_id' => $transaction->id,
                'procurement_request_id' => $request->id,
                'submitted_by' => $user->id,
                'budget_allocation_id' => $allocation->id,
                'ors_number' => 'DEMO-ORS-2026-'.$scenario['sequence'],
                'source_of_fund' => 'MOOE',
                'payee' => 'Demo Office Supplies Co.',
                'payee_address' => 'Test City',
                'payee_tin' => '000-000-000-000',
                'purpose' => $scenario['title'],
                'amount' => $scenario['amount'],
                'status' => $scenario['ors_status'],
                'notes' => 'Synthetic local workflow demonstration.',
                'accounting_remarks' => $scenario['ors_status'] === 'for_review' ? null : 'Demo review completed.',
                'dv_number' => $scenario['has_dv'] ? 'DEMO-DV-2026-'.$scenario['sequence'] : null,
                'dv_date' => $scenario['has_dv'] ? now()->subDay()->toDateString() : null,
                'dv_particulars' => $scenario['has_dv'] ? $scenario['title'] : null,
                'payment_mode' => $scenario['paid'] ? 'MDS Check' : null,
                'payment_reference' => $scenario['paid'] ? 'DEMO-CHECK-2026-'.$scenario['sequence'] : null,
                'paid_at' => $scenario['paid'] ? now()->toDateString() : null,
                'paid_by' => $scenario['paid'] ? $user->id : null,
                'submitted_at' => now()->subDays(3),
                'approved_at' => $scenario['ors_status'] === 'approved' ? now()->subDays(2) : null,
            ],
        );
    }

    private function seedCompletionDocuments(ProcurementRequest $request, User $user): void
    {
        foreach ([['purchase_order', 'DEMO-PO-2026-005'], ['inspection_acceptance_report', 'DEMO-IAR-2026-005']] as [$type, $number]) {
            ProcurementDocument::withoutGlobalScopes()->updateOrCreate(
                ['procurement_request_id' => $request->id, 'document_type' => $type],
                [
                    'organization_id' => $request->organization_id,
                    'created_by' => $user->id,
                    'document_number' => $number,
                    'document_date' => now()->subDays(2)->toDateString(),
                    'supplier_or_recipient' => 'Demo Office Supplies Co.',
                    'notes' => 'Synthetic local workflow demonstration.',
                    'status' => 'completed',
                ],
            );
        }
    }

    /** @param array<string, mixed> $scenario */
    private function seedTimeline(MasterTransaction $transaction, User $user, ProcurementRequest $request, ?LiquidationReport $report, array $scenario): void
    {
        $events = [['procurement', 'pr_created', null, 'pending_approval', $request->request_number]];

        if ($scenario['pr_status'] !== 'pending_approval') {
            $events[] = ['procurement', 'pr_approved', 'pending_approval', 'approved', $request->request_number];
        }
        if ($report) {
            $events[] = ['liquidation', 'ors_submitted', null, 'for_review', $report->ors_number];
        }
        if ($scenario['ors_status'] === 'approved') {
            $events[] = ['accounting', 'ors_reviewed', 'for_review', 'approved', $report?->ors_number];
        }
        if ($scenario['has_dv']) {
            $events[] = ['accounting', 'dv_created', null, 'ready_for_payment', $report?->dv_number];
        }
        if ($scenario['paid']) {
            $events[] = ['cash', 'payment_recorded', 'unpaid', 'paid', $report?->payment_reference];
        }

        $transaction->events()->whereNotIn('action', collect($events)->pluck(1))->delete();

        foreach ($events as [$module, $action, $previous, $next, $remarks]) {
            TransactionEvent::withoutGlobalScopes()->updateOrCreate(
                ['master_transaction_id' => $transaction->id, 'action' => $action],
                [
                    'organization_id' => $transaction->organization_id,
                    'user_id' => $user->id,
                    'module' => $module,
                    'previous_status' => $previous,
                    'new_status' => $next,
                    'remarks' => $remarks,
                ],
            );
        }
    }
}
