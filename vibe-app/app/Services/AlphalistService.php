<?php

namespace App\Services;

use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Monthly Alphalist of Payees (MAP) for the tax remittance: the withholding tax of the DVs paid in a month, in three lists by BIR form,
 * 1600PT (Non-VAT percentage tax), 0619E / 1601EQ (expanded) and 1600VT (VAT), from the rates of each payee in the Supplier Manager.
 */
class AlphalistService
{
    /** The three lists in print order. */
    public const FORMS = ['1600PT' => '1600PT', '0619E' => '0619E / 1601EQ', '1600VT' => '1600VT'];

    /**
     * @return array{blocks: array<string, Collection<int, array{tin: string, payee: string, atc: string, nature: string, base: float, rate: float, withheld: float}>>, missing: array<int, string>}
     */
    public function build(School $school, CarbonInterface $month, ?string $fund = null): array
    {
        $reports = LiquidationReport::query()->with('procurementRequest')
            ->where('school_id', $school->id)->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()
            ->filter(fn (LiquidationReport $report) => (float) ($report->dv_deductions['withholding_tax'] ?? 0) > 0)
            ->filter(fn (LiquidationReport $report) => $fund === null || strcasecmp(trim((string) $report->source_of_fund), $fund) === 0);

        $suppliers = Supplier::withoutGlobalScopes()->where('organization_id', $school->organization_id)->get()->keyBy(fn (Supplier $supplier) => mb_strtolower(trim((string) $supplier->business_name)));
        $rows = [];
        $missing = [];
        foreach ($reports as $report) {
            $payee = trim((string) ($report->payee ?: $report->procurementRequest?->supplier_name));
            $supplier = $suppliers->get(mb_strtolower($payee));
            // A supplier saved before the rates were split has only a total: it cannot be put in the right list, so it is named instead of guessed.
            if (! $supplier || ! in_array($supplier->tax_type, ['vat', 'non_vat'], true) || ($supplier->tax_rate_1600 === null && $supplier->tax_rate_0619e === null)) {
                $missing[] = $payee.' (DV '.$report->dv_number.')';

                continue;
            }
            $vat = $supplier->tax_type === 'vat';
            $goods = $report->ors_kind === 'goods';
            $base = $vat ? (float) $report->amount / 1.12 : (float) $report->amount;
            // The codes and the names of the income payment follow the owner's template.
            $parts = [
                $vat ? '1600VT' : '1600PT' => [(float) $supplier->tax_rate_1600, $vat ? ($goods ? 'WV010' : 'WV020') : 'WB084', $vat ? ($goods ? 'VAT Withholding on Purchase of Goods' : 'VAT Withholding on Purchase of Services') : ($goods ? 'NONVAT Withholding on Purchase of Goods' : 'Government Payments to Suppliers of Services')],
                '0619E' => [(float) $supplier->tax_rate_0619e, 'WB084', $goods ? ($vat ? 'VAT' : 'NONVAT').' Withholding on Purchase of Goods' : 'Government Payments to Suppliers of Services'],
            ];
            foreach ($parts as $form => [$rate, $atc, $nature]) {
                if ($rate <= 0) {
                    continue;
                }
                // One row per payee, code and rate in a list: the payments of the month are added up, as in the template.
                $key = $form.'|'.$supplier->id.'|'.$atc.'|'.$rate.'|'.$nature;
                $rows[$key] ??= ['form' => $form, 'tin' => (string) $supplier->tin, 'payee' => mb_strtoupper((string) $supplier->business_name), 'atc' => $atc, 'nature' => $nature, 'base' => 0.0, 'rate' => $rate];
                $rows[$key]['base'] += $base;
            }
        }

        $blocks = [];
        foreach (array_keys(self::FORMS) as $form) {
            $blocks[$form] = collect($rows)->where('form', $form)->map(function (array $row) {
                $row['base'] = round($row['base'], 2);
                $row['withheld'] = round($row['base'] * $row['rate'] / 100, 2);

                return $row;
            })->sortBy('payee')->values();
        }

        return ['blocks' => $blocks, 'missing' => array_values(array_unique($missing))];
    }
}
