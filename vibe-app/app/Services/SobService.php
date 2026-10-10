<?php

namespace App\Services;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\School;
use App\Models\SipPlan;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Every rule of the School Operating Budget: it is built from the school's approved AIP, one per quarter,
 * and approving it creates the quarter's Budget allotments.
 */
class SobService
{
    public function __construct(
        private MasterTransactionService $transactions,
        private FiscalYearService $fiscalYears,
        private BudgetService $budget,
        private DocumentNumberService $numbers,
    ) {}

    public function create(School $school, int $fiscalYear, int $quarter, string $fund, User $user): SobPlan
    {
        $fund = trim($fund);
        if ($quarter < 1 || $quarter > 4) {
            $this->fail('The quarter must be 1 to 4.');
        }
        if ($fund === '' || mb_strlen($fund) > 100) {
            $this->fail('Enter the fund of the SOB (for example MOOE).');
        }
        $aip = Aip::withoutGlobalScopes()->where('school_id', $school->id)->where('fiscal_year', $fiscalYear)->first();
        if (! $aip || $aip->status !== 'approved') {
            $this->fail('Approve the AIP for FY '.$fiscalYear.' before preparing its SOB.');
        }
        $this->fiscalYears->assertOpen((int) $school->organization_id, $fiscalYear);
        if ($this->exists($school->id, $fiscalYear, $quarter, $fund)) {
            $this->fail('The '.SobPlan::QUARTERS[$quarter].' SOB of FY '.$fiscalYear.' ('.$fund.') already exists.');
        }

        $sip = SipPlan::withoutGlobalScopes()->where('school_id', $school->id)->where('start_year', '<=', $fiscalYear)->where('start_year', '>', $fiscalYear - 3)->orderByDesc('start_year')->first();

        return DB::transaction(function () use ($school, $fiscalYear, $quarter, $fund, $user, $aip, $sip) {
            $transaction = $this->transactions->forAip($aip, $user);
            $plan = SobPlan::create([
                'organization_id' => $school->organization_id, 'school_id' => $school->id, 'aip_id' => $aip->id, 'master_transaction_id' => $transaction->id,
                'fiscal_year' => $fiscalYear, 'quarter' => $quarter, 'fund_source' => $fund, 'status' => 'draft', 'created_by' => $user->id,
                'prepared_by_name' => $sip?->prepared_by_name, 'prepared_by_position' => $sip?->prepared_by_position,
                'recommended_by_name' => $sip?->recommended_by_name, 'recommended_by_position' => $sip?->recommended_by_position,
                'approved_by_name' => $sip?->approved_by_name, 'approved_by_position' => $sip?->approved_by_position,
            ]);
            $transaction->recordEvent('sob', 'draft_created', null, 'draft', $this->title($plan), ['sob_plan_id' => $plan->id]);
            $this->audit($plan, $user, 'sob_created');

            return $plan;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function addItem(SobPlan $plan, array $data): SobItem
    {
        $this->requireDraft($plan);

        return SobItem::create(['organization_id' => $plan->organization_id, 'sob_plan_id' => $plan->id] + $this->itemValues($plan, $data));
    }

    /** @param  array<string, mixed>  $data */
    public function updateItem(SobItem $item, array $data): SobItem
    {
        $plan = $item->plan()->firstOrFail();
        $this->requireDraft($plan);
        $item->update($this->itemValues($plan, $data));

        return $item->fresh();
    }

    public function deleteItem(SobItem $item): void
    {
        $this->requireDraft($item->plan()->firstOrFail());
        $item->delete();
    }

    public function deletePlan(SobPlan $plan): void
    {
        $this->requireDraft($plan);
        DB::transaction(function () use ($plan) {
            $this->audit($plan, auth()->user(), 'sob_deleted');
            $plan->transaction?->recordEvent('sob', 'draft_deleted', 'draft', null, $this->title($plan), ['sob_plan_id' => $plan->id]);
            $plan->items()->delete();
            $plan->delete();
        });
    }

    /** @param  array<string, mixed>  $data  fund_source and the six signatory fields */
    public function updateHeader(SobPlan $plan, array $data): SobPlan
    {
        $this->requireDraft($plan);
        $rules = ['fund_source' => ['sometimes', 'required', 'string', 'max:100']];
        foreach (['prepared_by', 'recommended_by', 'approved_by'] as $prefix) {
            $rules[$prefix.'_name'] = ['nullable', 'string', 'max:255'];
            $rules[$prefix.'_position'] = ['nullable', 'string', 'max:255'];
        }
        $values = Validator::make($data, $rules)->validate();
        if (isset($values['fund_source']) && $values['fund_source'] !== $plan->fund_source && $this->exists($plan->school_id, $plan->fiscal_year, $plan->quarter, $values['fund_source'])) {
            $this->fail('The '.$plan->quarterLabel().' SOB of FY '.$plan->fiscal_year.' ('.$values['fund_source'].') already exists.');
        }
        $plan->update($values);

        return $plan->fresh();
    }

    /** @return array{created: int, updated: int} */
    public function approve(SobPlan $plan, User $user): array
    {
        return DB::transaction(function () use ($plan, $user) {
            $locked = SobPlan::withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            $this->requireDraft($locked, 'This SOB is already approved.');
            $items = $locked->items()->with(['activity.kra', 'account'])->get();
            if ($items->isEmpty()) {
                $this->fail('Add at least one item before approving.');
            }

            $school = School::withoutGlobalScopes()->findOrFail($locked->school_id);
            $transaction = $this->transactions->forAip(Aip::withoutGlobalScopes()->findOrFail($locked->aip_id), $user);
            $created = $updated = 0;

            foreach ($items->groupBy(fn (SobItem $i) => $i->chart_of_account_id.'|'.($i->activity->kra?->program ?? '')) as $group) {
                $first = $group->first();
                $program = $first->activity->kra?->program;
                $total = round((float) $group->sum('amount'), 2);
                $line = BudgetAllocation::withoutGlobalScopes()
                    ->where('aip_id', $locked->aip_id)->where('source_of_fund', $locked->fund_source)
                    ->where('chart_of_account_id', $first->chart_of_account_id)->where('fiscal_year', $locked->fiscal_year)
                    ->where(fn ($q) => $program ? $q->where('program', $program) : $q->whereNull('program')->orWhere('program', ''))
                    ->first();

                if ($line) {
                    $quarters = [];
                    foreach ([1, 2, 3, 4] as $number) {
                        $quarters["q{$number}_amount"] = $number === $locked->quarter ? $total : $line->quarterAmount($number);
                    }
                    $newAmount = round(array_sum($quarters), 2);
                    $obligated = $this->budget->matrix(collect([$line]))->first()['obligated'];
                    if ($newAmount + 0.001 < $obligated) {
                        $this->fail(sprintf('"%s" (%s) is already obligated ₱%s, which is more than this SOB would leave allotted (₱%s).', $program ?: 'the line', $locked->fund_source, number_format($obligated, 2), number_format($newAmount, 2)));
                    }
                    $line->update($quarters + ['amount' => $newAmount, 'master_transaction_id' => $transaction->id]);
                    $updated++;
                } else {
                    BudgetAllocation::create([
                        'organization_id' => $locked->organization_id, 'aip_id' => $locked->aip_id, 'school_id' => $locked->school_id, 'master_transaction_id' => $transaction->id,
                        'office' => $school->name, 'fiscal_year' => $locked->fiscal_year, 'start_date' => $locked->fiscal_year.'-01-01', 'end_date' => $locked->fiscal_year.'-12-31',
                        'source_of_fund' => $locked->fund_source, 'program' => $program, 'chart_of_account_id' => $first->chart_of_account_id,
                        'uacs_code' => $first->account->code, 'particulars' => $first->account->title, 'amount' => $total,
                        'q1_amount' => $locked->quarter === 1 ? $total : 0, 'q2_amount' => $locked->quarter === 2 ? $total : 0, 'q3_amount' => $locked->quarter === 3 ? $total : 0, 'q4_amount' => $locked->quarter === 4 ? $total : 0,
                        'created_by' => $user->id, 'budget_ref_no' => $this->numbers->next((int) $locked->organization_id, 'budget_allocation', 'BA', (int) $locked->fiscal_year),
                        'remarks' => 'From SOB FY '.$locked->fiscal_year.' Q'.$locked->quarter,
                    ]);
                    $created++;
                }
            }

            $locked->update(['status' => 'approved', 'approved_at' => now(), 'master_transaction_id' => $transaction->id]);
            $transaction->recordEvent('sob', 'approved', 'draft', 'approved', $this->title($locked), ['sob_plan_id' => $locked->id, 'created_allocations' => $created, 'updated_allocations' => $updated]);
            $this->audit($locked, $user, 'sob_approved', ['created' => $created, 'updated' => $updated]);

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /** @return array<int, array{aip_activity_id: int, sob: float, aip: float}> activities whose SOB lines total more than the AIP gives the quarter */
    public function overAip(SobPlan $plan): array
    {
        $over = [];
        foreach ($plan->items()->with('activity')->get()->groupBy('aip_activity_id') as $activityId => $items) {
            $sob = round((float) $items->sum('amount'), 2);
            $aip = (float) $items->first()->activity->{"q{$plan->quarter}_amount"};
            if ($sob > $aip + 0.001) {
                $over[] = ['aip_activity_id' => (int) $activityId, 'sob' => $sob, 'aip' => $aip];
            }
        }

        return $over;
    }

    /** @return Collection<int, array{code: string, title: string, total: float}> the summary per object of expenditure, by account code */
    public function summaryByAccount(SobPlan $plan): Collection
    {
        return $plan->items()->with('account')->get()->groupBy('chart_of_account_id')
            ->map(fn (Collection $items) => ['code' => $items->first()->account->code, 'title' => $items->first()->account->title, 'total' => round((float) $items->sum('amount'), 2)])
            ->sortBy('code')->values();
    }

    /**
     * The lines grouped for the page and the print: pillar, then program, then AIP activity.
     *
     * @return array<int, array{pillar: string, total: float, programs: array<int, array{program: string, total: float, activities: array<int, array{activity: AipActivity, aip_amount: float, total: float, items: Collection}>}>}>
     */
    public function grouped(SobPlan $plan): array
    {
        $items = $plan->items()->with('activity.kra')->orderBy('id')->get();
        $pillars = [];
        foreach ($items->groupBy(fn (SobItem $i) => $i->activity->kra?->pillar ?? '') as $pillar => $pillarItems) {
            $programs = [];
            foreach ($pillarItems->groupBy(fn (SobItem $i) => $i->activity->kra?->program ?? '') as $program => $programItems) {
                $activities = [];
                foreach ($programItems->groupBy('aip_activity_id') as $activityItems) {
                    $activity = $activityItems->first()->activity;
                    $activities[] = ['activity' => $activity, 'aip_amount' => (float) $activity->{"q{$plan->quarter}_amount"}, 'total' => round((float) $activityItems->sum('amount'), 2), 'items' => $activityItems->values()];
                }
                $programs[] = ['program' => (string) $program, 'total' => round((float) $programItems->sum('amount'), 2), 'activities' => $activities];
            }
            $pillars[] = ['pillar' => (string) $pillar, 'total' => round((float) $pillarItems->sum('amount'), 2), 'programs' => $programs];
        }
        $order = array_flip(Aip::PILLARS);
        usort($pillars, fn ($a, $b) => ($order[$a['pillar']] ?? 99) <=> ($order[$b['pillar']] ?? 99));

        return $pillars;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the validated columns with the amount computed on the server
     */
    private function itemValues(SobPlan $plan, array $data): array
    {
        $data['frequency'] = ($data['frequency'] ?? '') === '' || ($data['frequency'] ?? null) === null ? 1 : $data['frequency'];
        $values = Validator::make($data, [
            'aip_activity_id' => ['required', 'integer', Rule::exists('aip_activities', 'id')->where('aip_id', $plan->aip_id)],
            'chart_of_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $plan->organization_id)],
            'particulars' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ], ['aip_activity_id.exists' => 'Choose an activity of this school\'s AIP.', 'chart_of_account_id.exists' => 'Choose an account from the chart of accounts.'])->validate();

        $frequency = round((float) $values['frequency'], 2);
        $quantity = round((float) $values['quantity'], 2);
        $cost = round((float) $values['unit_cost'], 2);

        return [
            'aip_activity_id' => (int) $values['aip_activity_id'], 'chart_of_account_id' => (int) $values['chart_of_account_id'], 'particulars' => trim($values['particulars']),
            'frequency' => $frequency, 'quantity' => $quantity, 'unit' => trim($values['unit']), 'unit_cost' => $cost, 'amount' => round($frequency * $quantity * $cost, 2),
        ];
    }

    private function requireDraft(SobPlan $plan, string $message = 'An approved SOB is locked.'): void
    {
        if ($plan->status !== 'draft') {
            $this->fail($message);
        }
        $this->fiscalYears->assertOpen((int) $plan->organization_id, (int) $plan->fiscal_year);
    }

    private function exists(int $schoolId, int $fiscalYear, int $quarter, string $fund): bool
    {
        return SobPlan::withoutGlobalScopes()->where('school_id', $schoolId)->where('fiscal_year', $fiscalYear)->where('quarter', $quarter)->where('fund_source', $fund)->exists();
    }

    private function title(SobPlan $plan): string
    {
        return 'SOB '.$plan->quarterLabel().' FY '.$plan->fiscal_year;
    }

    /** @param  array<string, mixed>  $metadata */
    private function audit(SobPlan $plan, ?User $user, string $action, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $user?->id, 'school_id' => $plan->school_id, 'action' => $action, 'auditable_type' => SobPlan::class, 'auditable_id' => $plan->id, 'metadata' => $metadata ?: null]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['planning' => $message]);
    }
}
