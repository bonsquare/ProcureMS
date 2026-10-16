<?php

namespace App\Services;

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
     * @return array{opening: float, receipts: Collection, lines: Collection, total_paid: float, total_received: float, ending: float, accounts: array<string, string>}
     */
    public function build(School $school, CarbonInterface $from, CarbonInterface $to): array
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
            ->where('school_id', $school->id)
            ->whereNotNull('paid_at')
            ->get()
            ->map(fn (CancelledCheck $check) => $this->cancelledLine($check));

        $all = $paidLines->concat($cancelledLines);
        $receipts = CashFundReceipt::query()->where('school_id', $school->id)->orderBy('received_at')->orderBy('id')->get();

        $before = fn ($date) => $date->lt($from);
        $opening = round(
            (float) $receipts->filter(fn ($receipt) => $before($receipt->received_at))->sum('amount')
            - (float) $all->filter(fn (array $line) => $before($line['date']))->sum(fn (array $line) => $line['amount'] ?? 0),
            2
        );

        $periodReceipts = $receipts->filter(fn ($receipt) => ! $before($receipt->received_at) && $receipt->received_at->lte($to))->values();
        $lines = $all
            ->filter(fn (array $line) => ! $before($line['date']) && $line['date']->lte($to))
            ->sortBy(fn (array $line) => $line['date']->format('Ymd').'|'.$line['check_no'])
            ->values();

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

        $accounts = $lines->filter(fn (array $line) => ! $line['cancelled'] && $line['uacs_code'] !== '')
            ->mapWithKeys(fn (array $line) => [$line['uacs_code'] => $line['nature']])
            ->sortKeys()
            ->all();

        return [
            'opening' => $opening,
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
            'responsibility_center' => (string) $report->responsibility_center_code,
            'payee' => (string) $report->payee,
            'uacs_code' => $code,
            'nature' => $title,
            'amount' => round((float) $report->amount, 2),
            'mode' => $report->payment_mode === 'ADA' ? 'ada' : 'check',
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
            'responsibility_center' => '',
            'payee' => 'Cancelled Check',
            'uacs_code' => '',
            'nature' => '',
            'amount' => null,
            'mode' => 'check',
            'cancelled' => true,
        ];
    }
}
