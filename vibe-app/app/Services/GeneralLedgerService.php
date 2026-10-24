<?php

namespace App\Services;

use App\Models\CashFundReceipt;
use App\Models\LiquidationReport;
use App\Models\OpeningBalance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Builds the books on the fly: opening balances, cash fund receipts and approved disbursement vouchers become postings, and every financial report reads them. */
class GeneralLedgerService
{
    public const DEFAULT_FUND = 'General Fund';

    /** UACS groups accounts by their first digit. shortcut: custom accounts outside 1-4 count as expenses, upgrade if a school adds its own classes. */
    public function accountCategory(string $code): string
    {
        return match (substr($code, 0, 1)) {
            '1' => 'Assets', '2' => 'Liabilities', '3' => 'Equity', '4' => 'Revenue',
            default => 'Expense',
        };
    }

    /**
     * @param  array<int, int>  $schoolIds
     * @return Collection<int, array<string, mixed>>
     */
    public function postings(array $schoolIds, ?string $fundCluster, ?Carbon $to = null): Collection
    {
        $postings = collect();

        foreach (OpeningBalance::query()->whereIn('school_id', $schoolIds)->get() as $row) {
            $postings->push($this->posting('opening', $row->as_of, $row->account_code, $row->account_title, $row->debit, $row->credit, $row->school_id, $row->fund_cluster, 'Opening balance', 'Opening balance', 'Opening balance'));
        }

        $credits = config('financial_reports.receipt_credit');
        $cash = config('financial_reports.cash_account');
        $receipts = CashFundReceipt::query()->whereIn('school_id', $schoolIds)->whereIn('kind', array_keys($credits))->get();
        foreach ($receipts as $receipt) {
            $credit = $credits[$receipt->kind];
            $fund = $receipt->fund_cluster ?: self::DEFAULT_FUND;
            $ref = $receipt->reference ?: $receipt->kindLabel();
            $sub = $receipt->source_of_fund ?: $receipt->kindLabel();
            $postings->push($this->posting('receipt', $receipt->received_at, $cash['code'], $cash['title'], $receipt->amount, 0, $receipt->school_id, $fund, $ref, $receipt->kindLabel(), $sub));
            $postings->push($this->posting('receipt', $receipt->received_at, $credit['code'], $credit['title'], 0, $receipt->amount, $receipt->school_id, $fund, $ref, $receipt->kindLabel(), $sub));
        }

        $reports = LiquidationReport::query()->whereIn('school_id', $schoolIds)->where('status', 'approved')->whereNotNull('dv_number')->whereNotNull('dv_date')->with(['journalLines', 'breakdownLines'])->get();
        foreach ($reports as $report) {
            foreach ($report->journalLines as $line) {
                $breakdown = $report->breakdownLines->where('journal_line_no', $line->line_no)->map(fn ($b) => ['account_code' => $b->account_code, 'account_title' => $b->account_title, 'debit' => (string) $b->debit, 'credit' => (string) $b->credit])->values()->all();
                $postings->push($this->posting('dv', $report->dv_date, $line->account_code, $line->account_title, $line->debit, $line->credit, $report->school_id, $report->dv_fund_cluster ?: self::DEFAULT_FUND, $report->dv_number, (string) $report->purpose, (string) $report->payee, $breakdown));
            }
        }

        if ($fundCluster) {
            $postings = $postings->where('fund_cluster', $fundCluster);
        }
        if ($to) {
            $postings = $postings->filter(fn (array $p) => $p['date']->lte($to));
        }
        $order = ['opening' => 0, 'receipt' => 1, 'dv' => 2];

        return $postings->sortBy(fn (array $p) => $p['date']->format('Y-m-d').$order[$p['source']])->values();
    }

    /** @return array<string, mixed> */
    private function posting(string $source, $date, ?string $code, ?string $title, $debit, $credit, int $schoolId, string $fund, string $ref, string $particulars, string $sub, array $breakdown = []): array
    {
        return [
            'source' => $source, 'date' => Carbon::parse($date)->startOfDay(), 'account_code' => $code, 'account_title' => $title,
            'debit' => number_format((float) $debit, 2, '.', ''), 'credit' => number_format((float) $credit, 2, '.', ''),
            'school_id' => $schoolId, 'fund_cluster' => $fund, 'ref' => $ref, 'particulars' => $particulars, 'sub' => $sub, 'breakdown' => $breakdown,
        ];
    }
}
