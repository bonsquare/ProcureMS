<?php

namespace App\Services;

use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\MasterTransaction;
use App\Models\School;
use App\Models\SipPlan;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Support\AppCategories;
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
        $this->requireEditable($plan);

        return $this->revise($plan, fn () => SobItem::create(['organization_id' => $plan->organization_id, 'sob_plan_id' => $plan->id] + $this->itemValues($plan, $data)), collect());
    }

    /**
     * Adds an Item: several particulars under one activity and account, with an optional free note and any other lines. All are saved or none.
     *
     * @param  array<int, array<string, mixed>>  $lines  particulars, frequency, quantity, unit and unit_cost of each
     * @param  array<int, array<string, mixed>>  $extras  label (particulars), unit and amount of each other line
     * @return array<int, SobItem>
     */
    public function addItems(SobPlan $plan, int|string $activityId, int|string $accountId, array $lines, ?string $note = null, array $extras = [], ?string $summaryLabel = null): array
    {
        $this->requireEditable($plan);
        $lines = $this->filledRows($lines);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one particulars.']);
        }
        $note = $this->cleanNote($note);
        $label = ['summary_label' => $this->cleanSummaryLabel($summaryLabel)];

        return $this->revise($plan, fn () => DB::transaction(function () use ($plan, $activityId, $accountId, $lines, $note, $extras, $label) {
            $created = [];
            foreach ($lines as $line) {
                $created[] = $this->writeRow($plan, null, $this->itemValues($plan, ['aip_activity_id' => $activityId, 'chart_of_account_id' => $accountId] + $line) + $label, $note, false);
            }
            foreach ($this->filledExtras($extras) as $extra) {
                $created[] = $this->writeRow($plan, null, $this->extraValues($activityId, $accountId, $extra) + $label, $note, true);
            }

            return $created;
        }), collect());
    }

    /**
     * Saves a whole Item at once (the activity and account it is filed under, its note, particulars and other lines): rows carrying their id are
     * updated, new rows are added and rows left out are removed. All or nothing.
     *
     * @param  array{aip_activity_id?: mixed, chart_of_account_id?: mixed, note?: ?string, lines?: array<int, array<string, mixed>>, extras?: array<int, array<string, mixed>>}  $data
     */
    public function saveGroup(SobPlan $plan, int $activityId, int $accountId, array $data): void
    {
        $this->requireEditable($plan);
        $existing = $plan->items()->where('aip_activity_id', $activityId)->where('chart_of_account_id', $accountId)->get();
        if ($existing->isEmpty()) {
            $this->fail('That item no longer exists.');
        }
        $lines = $this->filledRows($data['lines'] ?? []);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'An item needs at least one particulars.']);
        }
        $note = $this->cleanNote($data['note'] ?? null);
        $label = ['summary_label' => $this->cleanSummaryLabel($data['summary_label'] ?? null)];
        $target = ['aip_activity_id' => $data['aip_activity_id'] ?? null, 'chart_of_account_id' => $data['chart_of_account_id'] ?? null];

        $this->revise($plan, fn () => DB::transaction(function () use ($plan, $existing, $lines, $data, $note, $target, $label) {
            $keep = [];
            foreach ($lines as $line) {
                $row = $existing->first(fn (SobItem $item) => ! $item->is_extra && (int) $item->id === (int) ($line['id'] ?? 0));
                $keep[] = $this->writeRow($plan, $row, $this->itemValues($plan, $target + $line) + $label, $note, false)->id;
            }
            foreach ($this->filledExtras($data['extras'] ?? []) as $extra) {
                $row = $existing->first(fn (SobItem $item) => $item->is_extra && (int) $item->id === (int) ($extra['id'] ?? 0));
                $keep[] = $this->writeRow($plan, $row, $this->extraValues($target['aip_activity_id'], $target['chart_of_account_id'], $extra) + $label, $note, true)->id;
            }
            $plan->items()->whereIn('id', $existing->pluck('id'))->whereNotIn('id', $keep)->delete();
        }), $existing);
    }

    public function deleteGroup(SobPlan $plan, int $activityId, int $accountId): void
    {
        $this->requireEditable($plan);
        $rows = $plan->items()->where('aip_activity_id', $activityId)->where('chart_of_account_id', $accountId)->get();
        $this->revise($plan, fn () => $plan->items()->whereIn('id', $rows->pluck('id'))->delete(), $rows);
    }

    /** @param  array<string, mixed>  $values */
    private function writeRow(SobPlan $plan, ?SobItem $row, array $values, ?string $note, bool $extra): SobItem
    {
        $values += ['note' => $note, 'is_extra' => $extra];
        if ($row) {
            $row->update($values);

            return $row;
        }

        return SobItem::create(['organization_id' => $plan->organization_id, 'sob_plan_id' => $plan->id] + $values);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>> the submitted rows that are arrays
     */
    private function filledRows(array $rows): array
    {
        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param  array<int, mixed>  $extras
     * @return array<int, array<string, mixed>> the other lines in which something was typed; an untouched blank row is ignored
     */
    private function filledExtras(array $extras): array
    {
        return array_values(array_filter($extras, fn ($extra) => is_array($extra) && (trim((string) ($extra['particulars'] ?? '')) !== '' || trim((string) ($extra['amount'] ?? '')) !== '')));
    }

    private function cleanSummaryLabel(?string $label): ?string
    {
        $label = trim((string) $label);
        Validator::make(['summary_label' => $label], ['summary_label' => ['max:120']])->validate();

        return $label === '' ? null : $label;
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);
        Validator::make(['note' => $note], ['note' => ['max:255']])->validate();

        return $note === '' ? null : $note;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed> an other line as a row: the label as particulars, quantity 1, the typed amount as the cost
     */
    private function extraValues(mixed $activityId, mixed $accountId, array $extra): array
    {
        $values = Validator::make($extra + ['aip_activity_id' => $activityId, 'chart_of_account_id' => $accountId], [
            'particulars' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'aip_activity_id' => ['required', 'integer'],
            'chart_of_account_id' => ['required', 'integer'],
        ], ['particulars.required' => 'Name the other line (for example 35% Labor).', 'amount.required' => 'Type the amount of the other line.'])->validate();
        $amount = round((float) $values['amount'], 2);

        return [
            'aip_activity_id' => (int) $values['aip_activity_id'], 'chart_of_account_id' => (int) $values['chart_of_account_id'], 'particulars' => trim($values['particulars']),
            'frequency' => 1, 'quantity' => 1, 'unit' => trim((string) ($values['unit'] ?? '')), 'unit_cost' => $amount, 'amount' => $amount, 'category' => null,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function updateItem(SobItem $item, array $data): SobItem
    {
        $plan = $item->plan()->firstOrFail();
        $this->requireEditable($plan);
        $this->revise($plan, fn () => $item->update($this->itemValues($plan, $data)), collect([$item]));

        return $item->fresh();
    }

    public function deleteItem(SobItem $item): void
    {
        $plan = $item->plan()->firstOrFail();
        $this->requireEditable($plan);
        $this->revise($plan, fn () => $item->delete(), collect([$item]));
    }

    /** A draft is deleted as it is. An approved SOB first releases its Budget allotments and is refused while a Purchase Request or an approved APP uses its items. */
    public function deletePlan(SobPlan $plan): void
    {
        $this->requireEditable($plan);
        if ($plan->status !== 'approved') {
            DB::transaction(fn () => $this->removePlan($plan, 'draft'));

            return;
        }

        DB::transaction(function () use ($plan) {
            $locked = SobPlan::withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            $locked->update(['allotment_snapshot' => $locked->allotment_snapshot ?: $this->snapshotOf($locked)]);
            $this->guardApprovedChange($locked, $locked->items()->get());
            $this->releaseStaleLines($locked->fresh(), []);
            $this->removePlan($locked, 'approved');
        });
    }

    private function removePlan(SobPlan $plan, string $status): void
    {
        $this->audit($plan, auth()->user(), 'sob_deleted');
        $plan->transaction?->recordEvent('sob', $status === 'draft' ? 'draft_deleted' : 'deleted', $status, null, $this->title($plan), ['sob_plan_id' => $plan->id]);
        $plan->items()->delete();
        $plan->delete();
    }

    /** @param  array<string, mixed>  $data  fund_source, quarter, school_id_number and the six signatory fields */
    public function updateHeader(SobPlan $plan, array $data): SobPlan
    {
        $this->requireEditable($plan);
        $rules = ['fund_source' => ['sometimes', 'required', 'string', 'max:100'], 'quarter' => ['sometimes', 'required', 'integer', 'between:1,4'], 'school_id_number' => ['nullable', 'string', 'max:50']];
        foreach (['prepared_by', 'recommended_by', 'approved_by'] as $prefix) {
            $rules[$prefix.'_name'] = ['nullable', 'string', 'max:255'];
            $rules[$prefix.'_position'] = ['nullable', 'string', 'max:255'];
            $rules[$prefix.'_role'] = ['nullable', 'string', 'max:255'];
        }
        $values = Validator::make($data, $rules)->validate();
        $fund = $values['fund_source'] ?? $plan->fund_source;
        $quarter = (int) ($values['quarter'] ?? $plan->quarter);
        if (($fund !== $plan->fund_source || $quarter !== (int) $plan->quarter) && $this->exists($plan->school_id, $plan->fiscal_year, $quarter, $fund)) {
            $this->fail('The '.SobPlan::QUARTERS[$quarter].' SOB of FY '.$plan->fiscal_year.' ('.$fund.') already exists.');
        }
        if ($plan->status === 'approved' && ($fund !== $plan->fund_source || $quarter !== (int) $plan->quarter)) {
            // The fund and the quarter decide the Budget lines (and the fund is copied to the APP lines), so they are revised like the items.
            $this->revise($plan, fn () => $plan->update($values), $fund !== $plan->fund_source ? $plan->items()->get() : collect());
        } else {
            $plan->update($values);
        }

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

            $transaction = $this->transactions->forAip(Aip::withoutGlobalScopes()->findOrFail($locked->aip_id), $user);
            ['created' => $created, 'updated' => $updated, 'keys' => $keys] = $this->writeBudgetLines($locked, $items, $user, $transaction);

            $locked->update([
                'status' => 'approved', 'approved_at' => now(), 'master_transaction_id' => $transaction->id,
                'allotment_snapshot' => ['fund' => $locked->fund_source, 'quarter' => (int) $locked->quarter, 'groups' => $keys],
            ]);
            $transaction->recordEvent('sob', 'approved', 'draft', 'approved', $this->title($locked), ['sob_plan_id' => $locked->id, 'created_allocations' => $created, 'updated_allocations' => $updated]);
            $this->audit($locked, $user, 'sob_approved', ['created' => $created, 'updated' => $updated]);
            $this->placeInApp($locked, $items, $transaction, $user);

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * Writes what the items give to the Budget allotment lines of the SOB's fund and quarter (one line per account and program), and releases
     * the lines the previous approval wrote that these items no longer reach.
     *
     * @param  Collection<int, SobItem>  $items
     * @return array{created: int, updated: int, keys: array<int, string>}
     */
    private function writeBudgetLines(SobPlan $plan, Collection $items, User $user, MasterTransaction $transaction): array
    {
        $school = School::withoutGlobalScopes()->findOrFail($plan->school_id);
        $created = $updated = 0;

        foreach ($items->groupBy(fn (SobItem $i) => $this->groupKey($i)) as $group) {
            $first = $group->first();
            $program = $first->activity->kra?->program;
            $total = round((float) $group->sum('amount'), 2);
            $line = BudgetAllocation::withoutGlobalScopes()
                ->where('aip_id', $plan->aip_id)->where('source_of_fund', $plan->fund_source)
                ->where('chart_of_account_id', $first->chart_of_account_id)->where('fiscal_year', $plan->fiscal_year)
                ->where(fn ($q) => $program ? $q->where('program', $program) : $q->whereNull('program')->orWhere('program', ''))
                ->first();

            if ($line) {
                $quarters = [];
                foreach ([1, 2, 3, 4] as $number) {
                    $quarters["q{$number}_amount"] = $number === $plan->quarter ? $total : $line->quarterAmount($number);
                }
                $newAmount = round(array_sum($quarters), 2);
                $obligated = $this->budget->matrix(collect([$line]))->first()['obligated'];
                if ($newAmount + 0.001 < $obligated) {
                    $this->fail(sprintf('"%s" (%s) is already obligated ₱%s, which is more than this SOB would leave allotted (₱%s).', $program ?: 'the line', $plan->fund_source, number_format($obligated, 2), number_format($newAmount, 2)));
                }
                $line->update($quarters + ['amount' => $newAmount, 'master_transaction_id' => $transaction->id]);
                $updated++;
            } else {
                BudgetAllocation::create([
                    'organization_id' => $plan->organization_id, 'aip_id' => $plan->aip_id, 'school_id' => $plan->school_id, 'master_transaction_id' => $transaction->id,
                    'office' => $school->name, 'fiscal_year' => $plan->fiscal_year, 'start_date' => $plan->fiscal_year.'-01-01', 'end_date' => $plan->fiscal_year.'-12-31',
                    'source_of_fund' => $plan->fund_source, 'program' => $program, 'chart_of_account_id' => $first->chart_of_account_id,
                    'uacs_code' => $first->account->code, 'particulars' => $first->account->title, 'amount' => $total,
                    'q1_amount' => $plan->quarter === 1 ? $total : 0, 'q2_amount' => $plan->quarter === 2 ? $total : 0, 'q3_amount' => $plan->quarter === 3 ? $total : 0, 'q4_amount' => $plan->quarter === 4 ? $total : 0,
                    'created_by' => $user->id, 'budget_ref_no' => $this->numbers->next((int) $plan->organization_id, 'budget_allocation', 'BA', (int) $plan->fiscal_year),
                    'remarks' => 'From SOB FY '.$plan->fiscal_year.' Q'.$plan->quarter,
                ]);
                $created++;
            }
        }

        $keys = $items->map(fn (SobItem $i) => $this->groupKey($i))->unique()->values()->all();
        $updated += $this->releaseStaleLines($plan, $keys);

        return ['created' => $created, 'updated' => $updated, 'keys' => $keys];
    }

    /**
     * Turns an approved SOB back into a draft so it can be revised. The budget lines stay as they are until it is approved again,
     * which updates them and releases what the revision no longer includes. Refused when the plan it fed can no longer follow.
     */
    public function reopen(SobPlan $plan, User $user): SobPlan
    {
        return DB::transaction(function () use ($plan, $user) {
            $locked = SobPlan::withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            if ($locked->status !== 'approved') {
                $this->fail('Only an approved SOB can be reopened.');
            }
            $this->fiscalYears->assertOpen((int) $locked->organization_id, (int) $locked->fiscal_year);

            $appItems = AppItem::withoutGlobalScopes()->whereIn('sob_item_id', $locked->items()->pluck('id'))->with('plan')->get();
            if ($appItems->contains(fn (AppItem $appItem) => $appItem->plan?->status === 'approved')) {
                $this->fail('The items of this SOB are in the approved Annual Procurement Plan of FY '.$locked->fiscal_year.'. Revising the SOB would leave that plan out of date, so it cannot be reopened.');
            }
            if ($appItems->contains(fn (AppItem $appItem) => $appItem->requestItems()->exists())) {
                $this->fail('One or more Purchase Requests already draw from the items of this SOB through the APP, so it cannot be reopened.');
            }

            // A draft APP would keep stale copies of the items being revised; they come back when the APP is generated again.
            if ($appItems->isNotEmpty()) {
                AppItem::withoutGlobalScopes()->whereIn('id', $appItems->pluck('id'))->delete();
                $locked->transaction?->recordEvent('app', 'removed_from_app', 'planned', null, $this->title($locked), ['sob_plan_id' => $locked->id, 'items' => $appItems->count()]);
            }

            $locked->update(['status' => 'draft', 'approved_at' => null, 'allotment_snapshot' => $locked->allotment_snapshot ?: $this->snapshotOf($locked)]);
            $locked->transaction?->recordEvent('sob', 'reopened', 'approved', 'draft', $this->title($locked), ['sob_plan_id' => $locked->id]);
            $this->audit($locked, $user, 'sob_reopened');

            return $locked->fresh();
        });
    }

    /** The budget line a SOB item feeds: its account and the program of its AIP activity. */
    private function groupKey(SobItem $item): string
    {
        return $item->chart_of_account_id.'|'.($item->activity->kra?->program ?? '');
    }

    /** @return array{fund: string, quarter: int, groups: array<int, string>} what an approved SOB's items wrote to the budget lines, rebuilt from its (locked, so unchanged) items */
    private function snapshotOf(SobPlan $plan): array
    {
        return [
            'fund' => $plan->fund_source, 'quarter' => (int) $plan->quarter,
            'groups' => $plan->items()->with('activity.kra')->get()->map(fn (SobItem $item) => $this->groupKey($item))->unique()->values()->all(),
        ];
    }

    /**
     * After a revision: the lines the previous approval wrote that this approval no longer writes (an account that was removed, or every line
     * when the fund or the quarter changed) lose their amount for that quarter. A line cannot drop below what is already obligated on it.
     */
    private function releaseStaleLines(SobPlan $plan, array $currentKeys): int
    {
        $previous = $plan->allotment_snapshot;
        if (! is_array($previous) || empty($previous['groups'])) {
            return 0;
        }
        $oldQuarter = (int) ($previous['quarter'] ?? $plan->quarter);
        $moved = ($previous['fund'] ?? null) !== $plan->fund_source || $oldQuarter !== (int) $plan->quarter;
        $stale = $moved ? $previous['groups'] : array_values(array_diff($previous['groups'], $currentKeys));

        $released = 0;
        foreach ($stale as $key) {
            [$accountId, $program] = array_pad(explode('|', (string) $key, 2), 2, '');
            $line = BudgetAllocation::withoutGlobalScopes()
                ->where('aip_id', $plan->aip_id)->where('source_of_fund', $previous['fund'])
                ->where('chart_of_account_id', (int) $accountId)->where('fiscal_year', $plan->fiscal_year)
                ->where(fn ($q) => $program !== '' ? $q->where('program', $program) : $q->whereNull('program')->orWhere('program', ''))
                ->first();
            if (! $line || (float) $line->{'q'.$oldQuarter.'_amount'} == 0.0) {
                continue;
            }

            $quarters = [];
            foreach ([1, 2, 3, 4] as $number) {
                $quarters["q{$number}_amount"] = $number === $oldQuarter ? 0.0 : $line->quarterAmount($number);
            }
            $newAmount = round(array_sum($quarters), 2);
            $obligated = $this->budget->matrix(collect([$line]))->first()['obligated'];
            if ($newAmount + 0.001 < $obligated) {
                $this->fail(sprintf('"%s" (%s) is already obligated ₱%s, which is more than this revision would leave allotted (₱%s).', $program ?: 'the line', $previous['fund'], number_format($obligated, 2), number_format($newAmount, 2)));
            }
            $line->update($quarters + ['amount' => $newAmount]);
            $released++;
        }

        return $released;
    }

    /**
     * The programs whose SOB lines total more than the AIP gives that program in the quarter (all its activities together); a program with no
     * amount yet is never over. The SOB goes by the program, so a change to the program's activities in the AIP does not make it wrong.
     *
     * @return array<int, array{pillar: string, program: string, sob: float, aip: float}>
     */
    public function overAip(SobPlan $plan): array
    {
        $over = [];
        foreach ($this->grouped($plan) as $pillar) {
            foreach ($pillar['programs'] as $program) {
                if ($program['over']) {
                    $over[] = ['pillar' => $pillar['pillar'], 'program' => $program['program'], 'sob' => $program['total'], 'aip' => $program['aip_amount']];
                }
            }
        }

        return $over;
    }

    /** @return Collection<int, array{code: string, title: string, total: float}> the summary per object of expenditure, by account code */
    public function summaryByAccount(SobPlan $plan): Collection
    {
        return $plan->items()->with('account')->get()
            ->groupBy(fn (SobItem $item) => filled($item->summary_label) ? 'label:'.mb_strtolower(trim($item->summary_label)) : 'account:'.$item->chart_of_account_id)
            ->map(fn (Collection $items) => ['code' => $items->first()->account->code, 'title' => $items->first()->summary_label ?: $items->first()->account->title, 'total' => round((float) $items->sum('amount'), 2)])
            ->sortBy('code')->values();
    }

    /**
     * The lines grouped for the page and the print: pillar, then program, then AIP activity.
     *
     * A program's aip_amount is what the AIP gives all of the program's activities in the quarter; over is true when the SOB spends more.
     *
     * @return array<int, array{pillar: string, total: float, programs: array<int, array{program: string, total: float, aip_amount: float, over: bool, activities: array<int, array{activity: AipActivity, aip_amount: float, total: float, items: Collection}>}>}>
     */
    public function grouped(SobPlan $plan): array
    {
        $items = $plan->items()->with(['activity.kra', 'account'])->orderBy('id')->get();
        $aipByProgram = [];
        foreach ($plan->aip()->with('kras.activities')->first()?->kras ?? [] as $kra) {
            $key = self::pillarName($kra->pillar).'|'.($kra->program ?? '');
            $aipByProgram[$key] = ($aipByProgram[$key] ?? 0) + (float) $kra->activities->sum(fn (AipActivity $activity) => (float) $activity->{"q{$plan->quarter}_amount"});
        }
        $pillars = [];
        foreach ($items->groupBy(fn (SobItem $i) => self::pillarName($i->activity->kra?->pillar)) as $pillar => $pillarItems) {
            $programs = [];
            foreach ($pillarItems->groupBy(fn (SobItem $i) => $i->activity->kra?->program ?? '') as $program => $programItems) {
                $activities = [];
                foreach ($programItems->groupBy('aip_activity_id') as $activityItems) {
                    $activity = $activityItems->first()->activity;
                    $activities[] = ['activity' => $activity, 'aip_amount' => (float) $activity->{"q{$plan->quarter}_amount"}, 'total' => round((float) $activityItems->sum('amount'), 2), 'items' => $activityItems->values(), 'accounts' => $this->accountGroups($activityItems)];
                }
                $total = round((float) $programItems->sum('amount'), 2);
                $aip = round((float) ($aipByProgram[$pillar.'|'.$program] ?? 0), 2);
                $programs[] = ['program' => (string) $program, 'total' => $total, 'aip_amount' => $aip, 'over' => $aip > 0 && $total > $aip + 0.001, 'activities' => $activities];
            }
            $pillars[] = ['pillar' => (string) $pillar, 'total' => round((float) $pillarItems->sum('amount'), 2), 'programs' => $programs];
        }
        $order = array_flip(self::PILLARS);
        usort($pillars, fn ($a, $b) => ($order[$a['pillar']] ?? 99) <=> ($order[$b['pillar']] ?? 99));

        return $pillars;
    }

    /**
     * @param  Collection<int, SobItem>  $items  the rows of one activity
     * @return array<int, array{account: ?ChartOfAccount, note: ?string, items: Collection, extras: Collection, items_total: float, total: float}> one Item per account, in the order first entered
     */
    private function accountGroups(Collection $items): array
    {
        return $items->groupBy('chart_of_account_id')->map(function (Collection $rows) {
            $purchased = $rows->reject->is_extra->values();
            $extras = $rows->filter->is_extra->values();

            return [
                'account' => $rows->first()->account, 'note' => $rows->pluck('note')->filter()->first(), 'summary_label' => $rows->pluck('summary_label')->filter()->first(), 'items' => $purchased, 'extras' => $extras,
                'items_total' => round((float) $purchased->sum('amount'), 2), 'total' => round((float) $rows->sum('amount'), 2),
            ];
        })->values()->all();
    }

    /** The pillars as the SOB shows them, in the order of the school's workbook. */
    public const PILLARS = ['Access', 'Quality', 'Equity', 'Resiliency and Well-Being', 'Governance'];

    /** The AIP keeps Resiliency and Well-Being apart and calls the last pillar the Enabling Mechanism; the SOB shows them as the workbook does. */
    public static function pillarName(?string $pillar): string
    {
        return match (mb_strtolower(trim((string) $pillar))) {
            'resiliency', 'well-being', 'well being', 'wellbeing', 'resiliency and well-being' => 'Resiliency and Well-Being',
            'enabling mechanism', 'governance' => 'Governance',
            default => trim((string) $pillar),
        };
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
            'category' => ['nullable', 'string', Rule::in(AppCategories::ALL)],
        ], ['aip_activity_id.exists' => 'Choose an activity of this school\'s AIP.', 'chart_of_account_id.exists' => 'Choose an account from the chart of accounts.'])->validate();

        $frequency = round((float) $values['frequency'], 2);
        $quantity = round((float) $values['quantity'], 2);
        $cost = round((float) $values['unit_cost'], 2);

        return [
            'aip_activity_id' => (int) $values['aip_activity_id'], 'chart_of_account_id' => (int) $values['chart_of_account_id'], 'particulars' => trim($values['particulars']),
            'frequency' => $frequency, 'quantity' => $quantity, 'unit' => trim($values['unit']), 'unit_cost' => $cost, 'amount' => round($frequency * $quantity * $cost, 2),
            // The form always sends the category: "-" (empty) means no category, so no APP line. A line that is made without one (by code) is filed under Other Categories.
            'category' => array_key_exists('category', $data) ? (filled($values['category'] ?? null) ? $values['category'] : null) : AppCategories::DEFAULT,
        ];
    }

    /**
     * Makes a change to an SOB. A draft is changed as it is. An approved SOB first checks that nothing it fed is locked or in use, then the change
     * is made and its Budget allotments and APP lines are brought in line, all in one step: it is refused as a whole if a line would drop below
     * what is already obligated.
     *
     * @param  Collection<int, SobItem>|null  $affected  the existing rows the change touches (none for an addition)
     */
    private function revise(SobPlan $plan, callable $change, ?Collection $affected = null): mixed
    {
        if ($plan->status !== 'approved') {
            return $change();
        }

        return DB::transaction(function () use ($plan, $change, $affected) {
            $locked = SobPlan::withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            $locked->update(['allotment_snapshot' => $locked->allotment_snapshot ?: $this->snapshotOf($locked)]);
            $this->guardApprovedChange($locked, $affected ?? $locked->items()->get());
            $outsideApp = $locked->items()->where('is_extra', false)->whereNull('category')->pluck('id')->all();

            $result = $change();

            $this->resyncApproved($locked->fresh(), $outsideApp);

            return $result;
        });
    }

    /** An approved SOB's items may be changed only while no Purchase Request draws from them and they are not in an approved APP. */
    private function guardApprovedChange(SobPlan $plan, Collection $rows): void
    {
        $appItems = AppItem::withoutGlobalScopes()->whereIn('sob_item_id', $rows->pluck('id'))->with('plan')->get();
        foreach ($appItems as $appItem) {
            if ($appItem->requestItems()->exists()) {
                $this->fail('A Purchase Request already draws from "'.$appItem->procurement_item.'" through the APP, so it cannot be changed or deleted.');
            }
        }
        if ($appItems->contains(fn (AppItem $appItem) => $appItem->plan?->status === 'approved')) {
            $this->fail('These items are in the approved Annual Procurement Plan of FY '.$plan->fiscal_year.'. Unlock the APP first, then change the SOB.');
        }
    }

    /**
     * Every item of an approved SOB goes into the school's APP of the fiscal year by itself, under its category, with its particulars,
     * quantity, unit and unit cost (the APP is made when there is none). An item that is already there is left to the refresh of resyncApproved();
     * an approved APP that receives a new item goes back to a draft, as when items are chosen for it by hand.
     *
     * @param  Collection<int, SobItem>  $items
     */
    private function placeInApp(SobPlan $plan, Collection $items, MasterTransaction $transaction, ?User $user): int
    {
        // An other line (such as Labor) and a line with no category ("-", such as a travel budget) are not goods: they are not put in the APP.
        $items = $items->reject->is_extra->filter(fn (SobItem $item) => filled($item->category))->values();
        if ($items->isEmpty()) {
            return 0;
        }
        $appPlan = AppPlan::withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $plan->school_id, 'fiscal_year' => $plan->fiscal_year],
            ['organization_id' => $plan->organization_id, 'status' => 'draft', 'created_by' => $user?->id ?? $plan->created_by],
        );
        $added = 0;
        foreach ($items as $item) {
            $appItem = AppItem::withoutGlobalScopes()->firstOrCreate(
                ['app_plan_id' => $appPlan->id, 'sob_item_id' => $item->id],
                [
                    'organization_id' => $plan->organization_id,
                    'procurement_item' => $item->particulars,
                    'specifications' => $item->activity?->activity,
                    'quantity' => round((float) $item->frequency * (float) $item->quantity, 2),
                    'unit' => $item->unit,
                    'estimated_unit_cost' => $item->unit_cost,
                    'estimated_total_cost' => $item->amount,
                    'fund_source' => $plan->fund_source,
                    'category' => $item->category,
                ],
            );
            if ($appItem->wasRecentlyCreated) {
                $added++;
                $transaction->recordEvent('app', 'included_in_app', null, 'planned', $item->particulars, ['app_plan_id' => $appPlan->id]);
            }
        }
        if ($added > 0 && $appPlan->status === 'approved') {
            $appPlan->update(['status' => 'draft']);
        }

        return $added;
    }

    /** After a change to an approved SOB: the Budget allotments, the APP lines, the audit log and the transaction history follow. */
    /** @param  array<int, int>  $outsideApp  the ids of the lines that had no category (so were not in the APP) before this change */
    private function resyncApproved(SobPlan $plan, array $outsideApp = []): void
    {
        $user = auth()->user() ?? User::withoutGlobalScopes()->find($plan->created_by);
        $items = $plan->items()->with(['activity.kra', 'account'])->get();
        $transaction = $this->transactions->forAip(Aip::withoutGlobalScopes()->findOrFail($plan->aip_id), $user);
        ['created' => $created, 'updated' => $updated, 'keys' => $keys] = $this->writeBudgetLines($plan, $items, $user, $transaction);

        $plan->update(['allotment_snapshot' => ['fund' => $plan->fund_source, 'quarter' => (int) $plan->quarter, 'groups' => $keys]]);
        foreach (AppItem::withoutGlobalScopes()->whereIn('sob_item_id', $items->reject->is_extra->pluck('id'))->get() as $appItem) {
            $item = $items->firstWhere('id', $appItem->sob_item_id);
            if (blank($item->category)) {
                // The line was changed to "-": it leaves the APP (the change was refused before if a Purchase Request or an approved APP holds it).
                $transaction->recordEvent('app', 'removed_from_app', 'planned', null, $item->particulars, ['app_plan_id' => $appItem->app_plan_id]);
                $appItem->delete();

                continue;
            }
            $appItem->update([
                'procurement_item' => $item->particulars, 'specifications' => $item->activity?->activity, 'quantity' => round((float) $item->frequency * (float) $item->quantity, 2),
                'unit' => $item->unit, 'estimated_unit_cost' => $item->unit_cost, 'estimated_total_cost' => $item->amount, 'fund_source' => $plan->fund_source,
                'category' => $item->category,
            ]);
        }
        // Only an item added after the approval goes in (an item the user took out of the APP is not brought back by an edit).
        $this->placeInApp($plan, $items->filter(fn (SobItem $item) => in_array($item->id, $outsideApp, true) || ($plan->approved_at && $item->created_at && $item->created_at->gte($plan->approved_at))), $transaction, $user);
        $this->audit($plan, $user, 'sob_revised', ['created' => $created, 'updated' => $updated]);
        $transaction->recordEvent('sob', 'revised', 'approved', 'approved', $this->title($plan), ['sob_plan_id' => $plan->id, 'created_allocations' => $created, 'updated_allocations' => $updated]);
    }

    /** Items and details can be changed while the fiscal year is open, whether the SOB is a draft or approved. */
    private function requireEditable(SobPlan $plan): void
    {
        $this->fiscalYears->assertOpen((int) $plan->organization_id, (int) $plan->fiscal_year);
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
