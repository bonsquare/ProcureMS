<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\CheckLiquidation;
use App\Models\LiquidationReport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Liquidation of a check issued as an advance: the actual expense accounts are recognized here, never from the DV journal (its debit is Advances to Operating Expenses). */
class CheckLiquidationService
{
    /** The advance a liquidation settles; a liquidation line can never use it. */
    private const ADVANCES_CODE = '1990101000';

    /**
     * The checks of the schools that can still be liquidated: paid, not cancelled (a cancelled check loses its payment), not already approved.
     *
     * @param  iterable<int>  $schoolIds
     */
    public function eligibleChecks($schoolIds): Collection
    {
        return LiquidationReport::query()->with('checkLiquidation')
            ->whereIn('school_id', $schoolIds)->whereNotNull('paid_at')->whereNotNull('payment_reference')
            ->whereDoesntHave('checkLiquidation', fn ($query) => $query->where('status', 'approved'))
            ->orderBy('payment_reference')->get();
    }

    /**
     * The expense lines to start from: the split lines of the ORS, else its main budget account, else none ("Needs classification").
     *
     * @return array{lines: array<int, array{account_code: string, account_title: string, amount: float, source: string}>, source: string}
     */
    public function suggestedLines(LiquidationReport $report): array
    {
        $split = $report->lines()->count() >= 2;
        $lines = [];
        foreach ($report->chargedLines() as $charged) {
            $account = $charged['allocation']?->account;
            $code = (string) ($account?->code ?? $charged['allocation']?->uacs_code ?? '');
            if ($code === '' || ! $this->isExpenseAccount($code)) {
                return ['lines' => [], 'source' => 'needs_classification'];
            }
            $lines[$code] = ['account_code' => $code, 'account_title' => (string) ($account?->title ?? ''), 'amount' => round(($lines[$code]['amount'] ?? 0) + $charged['amount'], 2), 'source' => $split ? 'ors_split' : 'ors_main'];
        }

        return ['lines' => array_values($lines), 'source' => $lines === [] ? 'needs_classification' : ($split ? 'ors_split' : 'ors_main')];
    }

    /**
     * Saves the draft of a check liquidation (a new one, or the draft being edited). The same account is combined into one line.
     *
     * @param  array<int, array{account_code: string, amount: mixed}>  $lines
     */
    public function saveDraft(LiquidationReport $report, User $user, array $lines, float $refund = 0, ?string $refundReference = null): CheckLiquidation
    {
        if (! $report->paid_at || ! $report->payment_reference) {
            throw ValidationException::withMessages(['check' => 'Only a paid check can be liquidated.']);
        }
        if ($report->breakdownLines->contains(fn ($part) => (float) $part->debit > 0 && $part->account_code !== self::ADVANCES_CODE && $report->journalLines->contains(fn ($line) => $line->line_no === $part->journal_line_no && $line->account_code === self::ADVANCES_CODE && (float) $line->debit > 0))) {
            throw ValidationException::withMessages(['check' => 'The DV of this check already splits the advance by expense account, so the expense is recognized there.']);
        }
        $prepared = $this->preparedLines($report, $lines, $refund);

        return DB::transaction(function () use ($report, $user, $prepared, $refund, $refundReference) {
            $liquidation = CheckLiquidation::query()->where('liquidation_report_id', $report->id)->lockForUpdate()->first();
            if ($liquidation?->isApproved()) {
                throw ValidationException::withMessages(['check' => 'This check is already liquidated. Reopen the liquidation to change it.']);
            }
            $liquidation ??= new CheckLiquidation(['organization_id' => $report->organization_id, 'school_id' => $report->school_id, 'liquidation_report_id' => $report->id, 'prepared_by' => $user->id]);
            $liquidation->fill(['status' => 'draft', 'check_amount' => $report->amount, 'refund_amount' => $refund, 'refund_reference' => $refundReference])->save();
            $liquidation->lines()->delete();
            foreach ($prepared as $number => $line) {
                $liquidation->lines()->create($line + ['organization_id' => $report->organization_id, 'line_no' => $number + 1]);
            }
            $this->audit($user, $report, 'check_liquidation_saved', ['version' => $liquidation->version, 'lines' => $prepared, 'refund' => $refund]);

            return $liquidation->load('lines');
        });
    }

    /** Approves the draft; approving again changes nothing, so a retry cannot duplicate the adjustment. */
    public function approve(CheckLiquidation $liquidation, User $user): CheckLiquidation
    {
        return DB::transaction(function () use ($liquidation, $user) {
            $locked = CheckLiquidation::query()->whereKey($liquidation->id)->lockForUpdate()->firstOrFail();
            if ($locked->isApproved()) {
                return $locked;
            }
            $report = $locked->report;
            $this->preparedLines($report, $locked->lines->map(fn ($line) => ['account_code' => $line->account_code, 'amount' => $line->amount])->all(), (float) $locked->refund_amount);
            $locked->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            // The refund of the unspent advance is cash received back into the fund (Dr Cash, Cr Advances in the books), never an expense.
            if ((float) $locked->refund_amount > 0) {
                CashFundReceipt::create([
                    'organization_id' => $locked->organization_id, 'school_id' => $locked->school_id, 'received_at' => now()->toDateString(), 'kind' => 'refund',
                    'source_of_fund' => trim((string) $report->source_of_fund) ?: null, 'fund_cluster' => $report->dv_fund_cluster ?: 'General Fund', 'amount' => $locked->refund_amount,
                    'reference' => $locked->refund_reference ?: 'Refund of check '.$report->payment_reference, 'note' => 'Unspent balance of check '.$report->payment_reference, 'created_by' => $user->id, 'check_liquidation_id' => $locked->id,
                ]);
            }
            $this->audit($user, $report, 'check_liquidation_approved', ['version' => $locked->version, 'total' => (float) $locked->lines->sum('amount'), 'refund' => (float) $locked->refund_amount]);

            return $locked;
        });
    }

    /** Takes an approved liquidation back to draft with the reason on record; the adjustment it made leaves the books until it is approved again. */
    public function reopen(CheckLiquidation $liquidation, User $user, string $reason): CheckLiquidation
    {
        return DB::transaction(function () use ($liquidation, $user, $reason) {
            $locked = CheckLiquidation::query()->whereKey($liquidation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isApproved()) {
                return $locked;
            }
            $this->audit($user, $locked->report, 'check_liquidation_reopened', ['version' => $locked->version, 'reason' => $reason, 'lines' => $locked->lines->map->only(['account_code', 'amount'])->all(), 'refund' => (float) $locked->refund_amount]);
            CashFundReceipt::query()->where('check_liquidation_id', $locked->id)->delete();
            $locked->update(['status' => 'draft', 'version' => $locked->version + 1, 'reopened_by' => $user->id, 'reopen_reason' => $reason, 'approved_by' => null, 'approved_at' => null]);

            return $locked;
        });
    }

    /**
     * Validates the lines and the refund against the check and returns the lines with the same accounts combined.
     *
     * @return array<int, array{account_code: string, account_title: string, amount: float, source: string}>
     */
    private function preparedLines(LiquidationReport $report, array $lines, float $refund): array
    {
        $combined = [];
        $suggested = collect($this->suggestedLines($report)['lines'])->keyBy('account_code');
        $titles = ChartOfAccount::query()->where('organization_id', $report->organization_id)->whereIn('code', collect($lines)->pluck('account_code')->filter())->pluck('title', 'code');
        foreach ($lines as $line) {
            $code = trim((string) ($line['account_code'] ?? ''));
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if (! $titles->has($code) || ! ($this->isExpenseAccount($code) || $this->isPayableAccount($code))) {
                throw ValidationException::withMessages(['lines' => "Account {$code} is not an expense or payable account of the chart of accounts."]);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['lines' => "The amount of account {$code} must be more than zero."]);
            }
            $sum = round(($combined[$code]['amount'] ?? 0) + $amount, 2);
            // A line keeps the ORS source only while it still carries the amount the ORS charged to that account.
            $same = $suggested->has($code) && round((float) $suggested[$code]['amount'], 2) === $sum;
            $combined[$code] = ['account_code' => $code, 'account_title' => (string) $titles[$code], 'amount' => $sum, 'source' => $same ? $suggested[$code]['source'] : 'manual'];
        }
        if ($refund < 0) {
            throw ValidationException::withMessages(['refund' => 'The refund cannot be negative.']);
        }
        $combined = array_values($combined);
        $expenses = round(collect($combined)->reject(fn (array $line) => $this->isPayableAccount($line['account_code']))->sum('amount'), 2);
        $payables = round(collect($combined)->filter(fn (array $line) => $this->isPayableAccount($line['account_code']))->sum('amount'), 2);
        if ($expenses <= 0) {
            throw ValidationException::withMessages(['lines' => 'Add at least one expense account.']);
        }
        $total = round($expenses - $payables + $refund, 2);
        if ($total !== round((float) $report->amount, 2)) {
            throw ValidationException::withMessages(['lines' => 'The expenses (₱'.number_format($expenses, 2).') less the payables (₱'.number_format($payables, 2).') plus the refund (₱'.number_format($refund, 2).') must equal the check amount (₱'.number_format((float) $report->amount, 2).').']);
        }

        return $combined;
    }

    /** An expense or property account: not cash, a liability, equity or revenue, and never the advance itself. */
    private function isExpenseAccount(string $code): bool
    {
        return $code !== self::ADVANCES_CODE && ! in_array(substr($code, 0, 1), ['2', '3', '4'], true) && ! str_starts_with($code, '101');
    }

    /** A payable (liability, code 2...) such as Due to BIR: it is credited by the liquidation and comes off the advance. */
    public static function isPayableAccount(string $code): bool
    {
        return str_starts_with($code, '2');
    }

    private function audit(User $user, LiquidationReport $report, string $action, array $metadata): void
    {
        AuditLog::create(['user_id' => $user->id, 'school_id' => $report->school_id, 'action' => $action, 'auditable_type' => LiquidationReport::class, 'auditable_id' => $report->id, 'metadata' => $metadata + ['check' => $report->payment_reference]]);
    }
}
