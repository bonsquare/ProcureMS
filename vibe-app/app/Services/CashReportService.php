<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\LiquidationReport;
use App\Models\School;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** The figures of the three cash reports of one school for a period, read from the paid ORS and DV, the cancelled checks and the fund received. */
class CashReportService
{
    /**
     * @return array{opening: float, opening_bank: float, receipts: Collection, lines: Collection, total_paid: float, total_received: float, ending: float, accounts: array<string, string>}
     */
    public function build(School $school, CarbonInterface $from, CarbonInterface $to, ?string $fund = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $paidLines = LiquidationReport::query()
            ->with(['budgetAllocation.account', 'procurementRequest.budgetAllocation.account', 'journalLines'])
            ->where('school_id', $school->id)
            ->whereNotNull('paid_at')
            ->get()
            ->map(fn (LiquidationReport $report) => $this->paidLine($report));

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

        // The accounts of the payments (of the chosen fund only), and, for one fund, the accounts budgeted for it in the SOB even when nothing was paid from them.
        $accounts = $lines->filter(fn (array $line) => ! $line['cancelled'] && $line['uacs_code'] !== '' && $line['amount'] !== null)
            ->mapWithKeys(fn (array $line) => [$line['uacs_code'] => $line['nature']])
            ->all();
        if ($fund !== null) {
            $budgeted = BudgetAllocation::query()->with('account')->where('school_id', $school->id)->where('fiscal_year', $from->year)->whereRaw('lower(source_of_fund) = ?', [strtolower($fund)])->get();
            // The register is per quarter, not consolidated: only the accounts with an allotment in the quarter(s) of the period.
            $quarters = $from->year === $to->year ? range($from->quarter, $to->quarter) : [1, 2, 3, 4];
            foreach ($budgeted->filter(fn (BudgetAllocation $allocation) => collect($quarters)->contains(fn (int $quarter) => (float) $allocation->quarterAmount($quarter) > 0)) as $allocation) {
                $code = (string) ($allocation->account?->code ?? $allocation->uacs_code ?? '');
                if ($code !== '' && ! isset($accounts[$code])) {
                    $accounts[$code] = (string) ($allocation->account?->title ?? $allocation->particulars ?? '');
                }
            }
        }
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
            'amount' => round((float) $report->amount, 2),
            'mode' => $this->modeOf($report->payment_mode),
            'cancelled' => false,
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
