<?php

namespace App\Services;

use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\CheckLiquidation;
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

        // An approved liquidation of a check recognizes the expense: Dr each expense account, Cr Advances for Operating Expenses (the cash was credited when the check was issued).
        $advances = $credits['cash_advance'];
        foreach (CheckLiquidation::query()->whereIn('school_id', $schoolIds)->where('status', 'approved')->with(['lines', 'report'])->get() as $liquidation) {
            $report = $liquidation->report;
            $fund = $report->dv_fund_cluster ?: self::DEFAULT_FUND;
            $ref = 'Liquidation of check '.$report->payment_reference;
            // An expense is debited; a payable (liability, code 2...) such as Due to BIR is credited, so the advance is credited for the expenses less the payables.
            $net = 0.0;
            foreach ($liquidation->lines as $line) {
                $payable = CheckLiquidationService::isPayableAccount($line->account_code);
                $net += $payable ? -(float) $line->amount : (float) $line->amount;
                $postings->push($this->posting('liquidation', $liquidation->approved_at, $line->account_code, $line->account_title, $payable ? 0 : $line->amount, $payable ? $line->amount : 0, $liquidation->school_id, $fund, $ref, (string) $report->purpose, (string) $report->payee));
            }
            $postings->push($this->posting('liquidation', $liquidation->approved_at, $advances['code'], $advances['title'], 0, round($net, 2), $liquidation->school_id, $fund, $ref, (string) $report->purpose, (string) $report->payee));
        }

        if ($fundCluster) {
            $postings = $postings->where('fund_cluster', $fundCluster);
        }
        if ($to) {
            $postings = $postings->filter(fn (array $p) => $p['date']->lte($to));
        }
        $order = ['opening' => 0, 'receipt' => 1, 'dv' => 2, 'liquidation' => 3];

        return $postings->sortBy(fn (array $p) => $p['date']->format('Y-m-d').$order[$p['source']])->values();
    }

    /**
     * Per account: balance before $from, movement from $from to $to, and the ending balance, each on its debit or credit side.
     *
     * @param  array<int, int>  $schoolIds
     * @return array{rows: Collection<int, array<string, string>>, totals: array{debit: string, credit: string}, balanced: bool}
     */
    public function trialBalance(array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to): array
    {
        $rows = $this->postings($schoolIds, $fundCluster, $to)->groupBy('account_code')->map(function (Collection $lines, string $code) use ($from) {
            $before = $lines->filter(fn (array $p) => $p['date']->lt($from));
            $period = $lines->reject(fn (array $p) => $p['date']->lt($from));
            $opening = $this->net($before);
            $debit = $period->sum(fn (array $p) => $this->cents($p['debit']));
            $credit = $period->sum(fn (array $p) => $this->cents($p['credit']));
            $ending = $opening + $debit - $credit;

            return [
                'account_code' => $code, 'account_title' => $lines->first()['account_title'],
                'opening_debit' => $this->money(max($opening, 0)), 'opening_credit' => $this->money(max(-$opening, 0)),
                'period_debit' => $this->money($debit), 'period_credit' => $this->money($credit),
                'ending_debit' => $this->money(max($ending, 0)), 'ending_credit' => $this->money(max(-$ending, 0)),
            ];
        })->filter(fn (array $row) => collect($row)->except(['account_code', 'account_title'])->contains(fn ($v) => $v !== '0.00'))->sortKeys()->values();

        $debit = $rows->sum(fn (array $r) => $this->cents($r['ending_debit']));
        $credit = $rows->sum(fn (array $r) => $this->cents($r['ending_credit']));

        return ['rows' => $rows, 'totals' => ['debit' => $this->money($debit), 'credit' => $this->money($credit)], 'balanced' => $debit === $credit];
    }

    /**
     * One account's postings from $from to $to with a running balance (debit positive).
     *
     * @param  array<int, int>  $schoolIds
     * @return array{opening: string, rows: Collection<int, array<string, string>>, closing: string}
     */
    public function ledger(string $accountCode, array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to): array
    {
        $lines = $this->postings($schoolIds, $fundCluster, $to)->where('account_code', $accountCode);
        $balance = $opening = $this->net($lines->filter(fn (array $p) => $p['date']->lt($from)));

        $rows = $lines->reject(fn (array $p) => $p['date']->lt($from))->map(function (array $p) use (&$balance) {
            $balance += $this->cents($p['debit']) - $this->cents($p['credit']);

            return ['date' => $p['date']->format('Y-m-d'), 'ref' => $p['ref'], 'particulars' => $p['particulars'], 'debit' => $p['debit'], 'credit' => $p['credit'], 'balance' => $this->money($balance)];
        })->values();

        return ['opening' => $this->money($opening), 'rows' => $rows, 'closing' => $this->money($balance)];
    }

    /**
     * One account split by payee, source of fund or, where a DV line has a breakdown, by the breakdown accounts.
     *
     * @param  array<int, int>  $schoolIds
     * @return Collection<int, array<string, string>>
     */
    public function subsidiary(string $accountCode, array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to): Collection
    {
        $lines = $this->postings($schoolIds, $fundCluster, $to)->where('account_code', $accountCode);
        $entries = collect();
        $forward = $this->net($lines->filter(fn (array $p) => $p['date']->lt($from)));
        if ($forward !== 0) {
            $entries->push(['sub' => 'Balance brought forward', 'debit' => $this->money(max($forward, 0)), 'credit' => $this->money(max(-$forward, 0))]);
        }
        foreach ($lines->reject(fn (array $p) => $p['date']->lt($from)) as $p) {
            if ($p['breakdown']) {
                foreach ($p['breakdown'] as $b) {
                    $entries->push(['sub' => $b['account_title'], 'debit' => $b['debit'], 'credit' => $b['credit']]);
                }
            } else {
                $entries->push(['sub' => $p['sub'], 'debit' => $p['debit'], 'credit' => $p['credit']]);
            }
        }

        return $entries->groupBy('sub')->map(function (Collection $group, string $sub) {
            $debit = $group->sum(fn (array $e) => $this->cents($e['debit']));
            $credit = $group->sum(fn (array $e) => $this->cents($e['credit']));

            return ['sub' => $sub, 'debit' => $this->money($debit), 'credit' => $this->money($credit), 'balance' => $this->money($debit - $credit)];
        })->values();
    }

    /**
     * Revenue less expenses for $from to $to, each grouped by the chart's category.
     *
     * @param  array<int, int>  $schoolIds
     * @return array{revenue: Collection, expenses: Collection, totalRevenue: string, totalExpenses: string, surplus: string}
     */
    public function incomeStatement(array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to): array
    {
        $lines = $this->postings($schoolIds, $fundCluster, $to)->reject(fn (array $p) => $p['date']->lt($from));
        $revenue = $this->statementRows($lines, 'Revenue', 'credit');
        $expenses = $this->statementRows($lines, 'Expense', 'debit');
        $totalRevenue = $revenue->flatten(1)->sum(fn (array $r) => $this->cents($r['amount']));
        $totalExpenses = $expenses->flatten(1)->sum(fn (array $r) => $this->cents($r['amount']));

        return ['revenue' => $revenue, 'expenses' => $expenses, 'totalRevenue' => $this->money($totalRevenue), 'totalExpenses' => $this->money($totalExpenses), 'surplus' => $this->money($totalRevenue - $totalExpenses)];
    }

    /**
     * Assets, liabilities and equity as of $asOf; the surplus of every posting up to then is added to equity.
     *
     * @param  array<int, int>  $schoolIds
     * @return array{assets: Collection, liabilities: Collection, equity: Collection, totalAssets: string, totalLiabilities: string, totalEquity: string, balanced: bool}
     */
    public function balanceSheet(array $schoolIds, ?string $fundCluster, Carbon $asOf): array
    {
        $lines = $this->postings($schoolIds, $fundCluster, $asOf);
        $assets = $this->accountRows($lines, 'Assets', 'debit');
        $liabilities = $this->accountRows($lines, 'Liabilities', 'credit');
        $equity = $this->accountRows($lines, 'Equity', 'credit');
        $surplus = $this->statementRows($lines, 'Revenue', 'credit')->flatten(1)->sum(fn (array $r) => $this->cents($r['amount']))
            - $this->statementRows($lines, 'Expense', 'debit')->flatten(1)->sum(fn (array $r) => $this->cents($r['amount']));
        $equity->push(['account_code' => '', 'account_title' => 'Current surplus/(deficit)', 'amount' => $this->money($surplus)]);

        $total = fn (Collection $rows) => $rows->sum(fn (array $r) => $this->cents($r['amount']));

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
            'totalAssets' => $this->money($total($assets)), 'totalLiabilities' => $this->money($total($liabilities)), 'totalEquity' => $this->money($total($equity)),
            'balanced' => $total($assets) === $total($liabilities) + $total($equity),
        ];
    }

    /** Rows of one account class, amount on the natural side ('debit' or 'credit'), zero rows left out. */
    private function accountRows(Collection $lines, string $category, string $natural): Collection
    {
        return $lines->filter(fn (array $p) => $this->accountCategory($p['account_code']) === $category)->groupBy('account_code')->map(function (Collection $group, string $code) use ($natural) {
            $net = $this->net($group);

            return ['account_code' => $code, 'account_title' => $group->first()['account_title'], 'amount' => $this->money($natural === 'debit' ? $net : -$net)];
        })->reject(fn (array $row) => $row['amount'] === '0.00')->sortKeys()->values();
    }

    /** accountRows() grouped by the chart's category (for example MOOE); an account missing from the chart goes under Other. */
    private function statementRows(Collection $lines, string $category, string $natural): Collection
    {
        $rows = $this->accountRows($lines, $category, $natural);
        $categories = ChartOfAccount::withoutGlobalScopes()->whereIn('code', $rows->pluck('account_code'))->pluck('category', 'code');

        return $rows->groupBy(fn (array $row) => $categories[$row['account_code']] ?? 'Other')->sortKeys();
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Debits minus credits of the postings, in cents. */
    private function net(Collection $lines): int
    {
        return $lines->sum(fn (array $p) => $this->cents($p['debit']) - $this->cents($p['credit']));
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
