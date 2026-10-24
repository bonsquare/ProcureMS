<?php

namespace App\Services;

use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SobItem;
use App\Models\SobPlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** The figures of the three cash reports of one school for a period, read from the paid ORS and DV, the cancelled checks and the fund received. */
class CashReportService
{
    /**
     * @return array{opening: float, opening_bank: float, receipts: Collection, lines: Collection, total_paid: float, total_received: float, ending: float, accounts: array<string, string>}
     */
    public function build(School $school, CarbonInterface $from, CarbonInterface $to, ?string $fund = null, ?array $checks = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $paidLines = LiquidationReport::query()
            ->with(['budgetAllocation.account', 'procurementRequest.budgetAllocation.account', 'journalLines', 'breakdownLines', 'lines.budgetAllocation.account'])
            ->where('school_id', $school->id)
            ->whereNotNull('paid_at')
            ->get()
            ->map(fn (LiquidationReport $report) => $this->paidLine($report));
        // A payment to the BIR with no breakdown of its own is charged to the expense accounts of the withheld taxes it remits, oldest first.
        $paidLines = $this->allocateRemittances($paidLines);

        $cancelledLines = CancelledCheck::query()
            ->with('liquidationReport')
            ->where('school_id', $school->id)
            ->whereNotNull('paid_at')
            ->get()
            ->map(fn (CancelledCheck $check) => $this->cancelledLine($check));

        // A report of one source of fund keeps the whole series of checks, but only the checks of that fund carry an amount; the cash downloaded is the fund's own.
        $sameFund = fn (?string $value) => $fund === null || strcasecmp(trim((string) $value), $fund) === 0;
        $all = $paidLines->concat($cancelledLines)->map(fn (array $line) => $sameFund($line['fund']) ? $line : ['amount' => null] + $line)->values();
        $receipts = CashFundReceipt::query()->where('school_id', $school->id)->orderBy('received_at')->orderBy('id')->get()->filter(fn (CashFundReceipt $receipt) => $sameFund($receipt->source_of_fund))->values();

        $before = fn ($date) => $date->lt($from);
        // The balance of the Bank Reconciliation (cash downloaded less what was paid) before the period starts is the beginning balance of the other reports.
        // The bank book leaves out a payment in cash (mode Others); the working fund of the register does not.
        $receiptsBefore = (float) $receipts->filter(fn ($receipt) => $before($receipt->received_at))->sum('amount');
        $paidBefore = $all->filter(fn (array $line) => $before($line['date']));
        $opening = round($receiptsBefore - (float) $paidBefore->sum(fn (array $line) => $line['amount'] ?? 0), 2);
        $openingBank = round($receiptsBefore - (float) $paidBefore->reject(fn (array $line) => $line['mode'] === 'other')->sum(fn (array $line) => $line['amount'] ?? 0), 2);

        // The same balance for each fund (source of fund) of the Bank Reconciliation.
        $fundOf = fn (?string $fund) => strtoupper(trim((string) $fund));
        $openingBankByFund = $receipts->filter(fn ($receipt) => $before($receipt->received_at))->groupBy(fn ($receipt) => $fundOf($receipt->source_of_fund))->map(fn ($group) => (float) $group->sum('amount'));
        foreach ($paidBefore->reject(fn (array $line) => $line['mode'] === 'other') as $line) {
            $key = $fundOf($line['fund']);
            $openingBankByFund[$key] = ($openingBankByFund[$key] ?? 0) - (float) ($line['amount'] ?? 0);
        }

        $periodReceipts = $receipts->filter(fn ($receipt) => ! $before($receipt->received_at) && $receipt->received_at->lte($to))->values();
        $lines = $all
            ->filter(fn (array $line) => ! $before($line['date']) && $line['date']->lte($to))
            // The Cash Disbursement Register lists only the check numbers typed in its form (a cancelled one stays in the series, marked cancelled).
            ->when($checks !== null, fn (Collection $kept) => $kept->filter(fn (array $line) => in_array($line['check_no'], $checks, true)))
            ->sortBy(fn (array $line) => $line['date']->format('Ymd').'|'.$line['check_no'])
            ->values();

        // A balance forwarded recorded in the period is the beginning balance of the report; the carried-over balance is not added to it.
        if ($periodReceipts->contains(fn ($receipt) => $receipt->kind === 'balance_forwarded')) {
            $opening = 0.0;
            $openingBank = 0.0;
            $openingBankByFund = [];
        }

        $balance = $opening;
        $pending = $periodReceipts->all();
        $lines = $lines->map(function (array $line) use (&$balance, &$pending) {
            while ($pending !== [] && $pending[0]->received_at->lte($line['date'])) {
                $balance += (float) array_shift($pending)->amount;
            }
            $balance = round($balance - ($line['amount'] ?? 0), 2);
            $line['balance'] = $balance;

            return $line;
        });

        $totalReceived = round((float) $periodReceipts->sum('amount'), 2);
        $totalPaid = round((float) $lines->sum(fn (array $line) => $line['amount'] ?? 0), 2);

        // The Breakdown of Payments and the account descriptions are the SOB's summary per object of expenditure: the accounts of the SOBs of the
        // period covered (its quarters), of the chosen source of fund, or of every fund when none is chosen.
        $plans = SobPlan::withoutGlobalScopes()->where('school_id', $school->id)
            ->when($fund !== null, fn ($query) => $query->whereRaw('lower(fund_source) = ?', [strtolower($fund)]))
            ->where(function ($query) use ($from, $to) {
                foreach (range($from->year, $to->year) as $year) {
                    $query->orWhere(fn ($same) => $same->where('fiscal_year', $year)->whereBetween('quarter', [$year === $from->year ? $from->quarter : 1, $year === $to->year ? $to->quarter : 4]));
                }
            })->pluck('id');
        $accounts = SobItem::withoutGlobalScopes()->with(['account' => fn ($query) => $query->withoutGlobalScopes()])->whereIn('sob_plan_id', $plans)->get()
            ->filter(fn (SobItem $item) => $item->account && $item->account->code !== '')
            ->mapWithKeys(fn (SobItem $item) => [(string) $item->account->code => (string) $item->account->title])
            ->all();
        ksort($accounts);

        return [
            'opening' => $opening,
            'opening_bank' => $openingBank,
            'opening_bank_by_fund' => collect($openingBankByFund)->map(fn ($amount) => round($amount, 2))->filter(fn ($amount) => $amount != 0),
            'receipts' => $periodReceipts,
            'lines' => $lines,
            'total_paid' => $totalPaid,
            'total_received' => $totalReceived,
            'ending' => round($opening + $totalReceived - $totalPaid, 2),
            'accounts' => $accounts,
        ];
    }

    /** @return array<string, float> */
    private function splitsOf(LiquidationReport $report): array
    {
        // A breakdown of a debit line of the journal (the advance split by actual expense account) replaces the split of the ORS:
        // the debit lines of the journal count, a ticked line by the net of its parts per account, any other as it is.
        if ($report->breakdownLines->contains(fn ($part) => $report->journalLines->contains(fn ($line) => $line->line_no === $part->journal_line_no && (float) $line->debit > 0))) {
            $splits = [];
            foreach ($report->journalLines->filter(fn ($line) => (float) $line->debit > 0) as $line) {
                $parts = $report->breakdownLines->where('journal_line_no', $line->line_no);
                if ($parts->isEmpty()) {
                    $splits[$line->account_code] = round(($splits[$line->account_code] ?? 0) + (float) $line->debit, 2);

                    continue;
                }
                foreach ($parts as $part) {
                    $splits[$part->account_code] = round(($splits[$part->account_code] ?? 0) + (float) $part->debit - (float) $part->credit, 2);
                }
            }

            return array_filter($splits, fn ($amount) => $amount != 0);
        }
        // With no breakdown the journal entry of the DV is the source: each debit line is a column (a liability such as Due to BIR is not one); the ORS only
        // answers when the DV has no journal.
        $debits = $report->journalLines->filter(fn ($line) => (float) $line->debit > 0 && ! str_starts_with((string) $line->account_code, '2'));
        if ($debits->isNotEmpty()) {
            $splits = [];
            foreach ($debits as $line) {
                $splits[$line->account_code] = round(($splits[$line->account_code] ?? 0) + (float) $line->debit, 2);
            }

            return $splits;
        }
        if ($report->lines->count() < 2) {
            return [];
        }
        $splits = [];
        foreach ($report->lines as $part) {
            $code = (string) ($part->budgetAllocation?->account?->code ?? $part->budgetAllocation?->uacs_code ?? '');
            $splits[$code] = round(($splits[$code] ?? 0) + (float) $part->amount, 2);
        }

        return $splits;
    }

    /** The total of the "Less:" deductions of the DV, 0 when it has none. */
    private function deductionsOf(LiquidationReport $report): float
    {
        $saved = $report->dv_deductions ?? [];
        $other = collect($saved['other'] ?? [])->sum(fn ($row) => (float) ($row['amount'] ?? 0));

        return round(collect(LiquidationReport::DV_DEDUCTIONS)->keys()->sum(fn ($key) => (float) ($saved[$key] ?? 0)) + $other, 2);
    }

    /** @return array<string, mixed> */
    private function paidLine(LiquidationReport $report): array
    {
        $account = $report->chargedLine()?->account;
        $code = (string) ($account?->code ?? $report->chargedLine()?->uacs_code ?? '');
        $title = (string) ($account?->title ?? '');
        if ($code === '') {
            $debit = $report->journalLines->first(fn ($line) => (float) $line->debit > 0);
            $code = (string) ($debit?->account_code ?? '');
            $title = (string) ($debit?->account_title ?? '');
        }

        return [
            'date' => ($report->check_date ?? $report->paid_at)->copy()->startOfDay(),
            'released' => $report->paid_at,
            'dv_date' => $report->dv_date,
            'check_no' => (string) $report->payment_reference,
            'dv_no' => (string) $report->dv_number,
            'ors_no' => (string) $report->ors_number,
            'fund' => trim((string) $report->source_of_fund),
            'responsibility_center' => (string) $report->responsibility_center_code,
            'payee' => (string) $report->payee,
            'uacs_code' => $code,
            'nature' => $title,
            // The cash paid out is the DV amount less its "Less:" deductions (withholding tax and the like, kept in Due to BIR); the gross is what the UACS columns charge.
            'amount' => round((float) $report->amount - $this->deductionsOf($report), 2),
            'gross' => round((float) $report->amount, 2),
            'tax' => $this->deductionsOf($report),
            'withheld' => round((float) ($report->dv_deductions['withholding_tax'] ?? 0), 2),
            'mode' => $this->modeOf($report->payment_mode),
            'cancelled' => false,
            // The code => amount of each UACS code when the ORS is charged to more than one (the register divides the payment among their columns).
            'splits' => $this->splitsOf($report),
        ];
    }

    /** @return array<string, mixed> */
    private function cancelledLine(CancelledCheck $check): array
    {
        return [
            'date' => $check->paid_at->copy()->startOfDay(),
            'released' => $check->paid_at,
            'dv_date' => null,
            'check_no' => (string) $check->check_number,
            'dv_no' => (string) $check->dv_number,
            'ors_no' => (string) $check->ors_number,
            'fund' => trim((string) $check->liquidationReport?->source_of_fund),
            'responsibility_center' => '',
            'payee' => 'Cancelled Check',
            'uacs_code' => '',
            'nature' => '',
            'amount' => null,
            'mode' => $this->modeOf($check->payment_mode),
            'cancelled' => true,
        ];
    }

    /**
     * Fills the split of every payment to the BIR (Due to BIR 2020101000, no breakdown) from a queue of the withholding taxes of the paid DVs, oldest check date first;
     * each tax keeps the split of the DV it was withheld from (a liability account never gets a share). A tax is used once.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function allocateRemittances(Collection $lines): Collection
    {
        $cents = fn ($amount) => (int) round(((float) $amount) * 100);
        $result = $lines->all();
        $queue = [];
        $order = $lines->sortBy(fn (array $line) => $line['date']->format('Ymd').'|'.$line['check_no'], SORT_NATURAL)->keys();
        foreach ($order as $key) {
            $line = $result[$key];
            if ($line['withheld'] > 0) {
                $weights = array_filter($line['splits'] !== [] ? $line['splits'] : [$line['uacs_code'] => 1], fn ($weight, $code) => (string) $code !== '' && ! str_starts_with((string) $code, '2') && $weight > 0, ARRAY_FILTER_USE_BOTH);
                if ($weights !== []) {
                    $queue[] = ['left' => $cents($line['withheld']), 'weights' => $weights];
                }
            }
            if ($line['uacs_code'] !== '2020101000' || $line['splits'] !== []) {
                continue;
            }
            $need = $cents($line['amount']);
            $pieces = [];
            while ($need > 0 && $queue !== []) {
                $take = min($need, $queue[0]['left']);
                $weights = $queue[0]['weights'];
                $total = array_sum($weights);
                $last = array_key_last($weights);
                $given = 0;
                foreach ($weights as $code => $weight) {
                    $part = $code === $last ? $take - $given : (int) round($take * $weight / $total);
                    $given += $part;
                    $pieces[$code] = ($pieces[$code] ?? 0) + $part;
                }
                $queue[0]['left'] -= $take;
                $need -= $take;
                if ($queue[0]['left'] <= 0) {
                    array_shift($queue);
                }
            }
            if ($pieces !== []) {
                $result[$key]['splits'] = array_map(fn ($amount) => round($amount / 100, 2), $pieces);
            }
        }

        return collect($result);
    }

    /** 'ada' for an ADA, 'other' for the Others mode (cash and the like), else 'check' (also an old record with no mode). */
    private function modeOf(?string $paymentMode): string
    {
        return match ($paymentMode) {
            'ADA' => 'ada',
            'Others' => 'other',
            default => 'check',
        };
    }
}
