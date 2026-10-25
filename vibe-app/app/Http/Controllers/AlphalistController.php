<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\CashReportHistory;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\AlphalistService;
use App\Services\DocumentNumberService;
use App\Support\SignatorySlots;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The Alphalist - Tax Remittance (Monthly Alphalist of Payees) of a school for one month, printed from the Liquidation page; every generation is kept. */
class AlphalistController extends Controller
{
    public function __invoke(Request $request, AlphalistService $alphalist)
    {
        return view('alphalist-print', $this->viewData($request, $alphalist));
    }

    /** Generates the alphalist and keeps the finished pages in the report history, then opens the kept copy. */
    public function store(Request $request, AlphalistService $alphalist)
    {
        $data = $this->viewData($request, $alphalist);
        $entry = CashReportHistory::create([
            'organization_id' => $data['school']->organization_id,
            'school_id' => $data['school']->id,
            'report' => 'alphalist',
            'period_from' => $data['month']->copy()->startOfMonth(),
            'period_to' => $data['month']->copy()->endOfMonth(),
            'period_text' => $data['period'],
            'source_of_fund' => $data['fund'] !== '' ? $data['fund'] : null,
            'total_payments' => collect($data['report']['blocks'])->flatten(1)->sum('withheld'),
            'html' => view('alphalist-print', $data)->render(),
            'params' => $request->except('_token') + ['remittance' => $this->remittance($data['report']['blocks'])],
            'generated_by' => $request->user()->id,
        ]);

        return redirect()->route('liquidation.alphalist.history', $entry);
    }

    /**
     * Each list's total and the tax of every DV in it, kept with the copy so a remittance DV is made exactly as printed.
     *
     * @return array<string, array{total: float, sources: array<int, float>}>
     */
    private function remittance(array $blocks): array
    {
        $remittance = [];
        foreach ($blocks as $form => $rows) {
            $sources = [];
            foreach ($rows as $row) {
                foreach ($row['sources'] as $id => $tax) {
                    $sources[$id] = round(($sources[$id] ?? 0) + $tax, 2);
                }
            }
            if ($rows->sum('withheld') > 0) {
                $remittance[$form] = ['total' => round((float) $rows->sum('withheld'), 2), 'sources' => $sources];
            }
        }

        return $remittance;
    }

    /**
     * Starts the DV that remits one list of a kept alphalist to the BIR (a DV with no ORS, completed in Accounting as any other), payee "{bank} FAO BIR {school} {TIN} ({form})".
     * One DV per list: pressing again opens the one already made.
     */
    public function createDv(Request $request, CashReportHistory $history, string $form)
    {
        abort_unless($request->user()->hasPermission('accounting.approve'), 403);
        abort_unless($history->report === 'alphalist' && $this->schoolIds($request)->contains($history->school_id), 404);
        $block = $history->params['remittance'][$form] ?? null;
        abort_unless($block && $block['total'] > 0, 404);

        $made = LiquidationReport::query()->where('school_id', $history->school_id)->whereNotNull('remittance')->get()
            ->first(fn (LiquidationReport $report) => (int) ($report->remittance['history_id'] ?? 0) === $history->id && ($report->remittance['form'] ?? null) === $form);
        if ($made) {
            return redirect()->route('accounting', ['tab' => $made->dv_number ? 'with_dv' : 'for_dv', 'open_dv' => $made->id])->with('success', "The DV of this {$form} list was already started ({$made->report_number}).");
        }

        $school = $history->school;
        $label = AlphalistService::FORMS[$form];
        $payee = trim(implode(' ', array_filter([$school->bank_name, 'FAO BIR', $school->name, $history->params['school_tin'] ?? null]))).' ('.$label.')';
        $report = DB::transaction(fn () => LiquidationReport::create([
            'organization_id' => $school->organization_id,
            'school_id' => $school->id,
            'payee' => $payee,
            'purpose' => "Remittance of {$label} withholding tax for the returned period {$history->period_text}",
            'amount' => $block['total'],
            'source_of_fund' => $history->source_of_fund,
            'remittance' => ['history_id' => $history->id, 'form' => $form, 'period' => $history->period_text, 'sources' => $block['sources']],
            'submitted_by' => $request->user()->id,
            'report_number' => app(DocumentNumberService::class)->next((int) $school->organization_id, 'liquidation_report', 'LR'),
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]));
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $school->id, 'action' => 'dv_remittance_started', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $report->id, 'metadata' => ['alphalist' => $history->id, 'form' => $form, 'amount' => $block['total']]]);

        return redirect()->route('accounting', ['tab' => 'for_dv', 'open_dv' => $report->id])->with('success', "{$report->report_number} started for the {$label} remittance. Complete its disbursement voucher.");
    }

    public function show(Request $request, CashReportHistory $history)
    {
        abort_unless($history->report === 'alphalist' && $this->schoolIds($request)->contains($history->school_id), 404);

        return response($history->html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request, AlphalistService $alphalist): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
            'source_of_fund' => ['nullable', 'string', 'max:50'],
            'school_tin' => ['nullable', 'string', 'max:50'],
            'signatory_name' => ['nullable', 'string', 'max:150'],
            'signatory_position' => ['nullable', 'string', 'max:150'],
        ]);
        abort_unless($this->schoolIds($request)->contains((int) $data['school_id']), 403);

        $school = School::findOrFail($data['school_id']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $fund = trim((string) ($data['source_of_fund'] ?? ''));
        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first();
        $head = SignatorySlots::defaults('disbursement_voucher', SchoolStaff::active()->where('school_id', $school->id)->get())['head'] ?? null;

        return [
            'school' => $school,
            'month' => $month,
            'department' => (string) ($agency?->department_name ?: 'Department of Education'),
            'division' => (string) ($agency?->division_name ?: $school->division),
            'period' => mb_strtoupper($month->format('F Y')),
            'fund' => $fund,
            'schoolTin' => (string) ($data['school_tin'] ?? ''),
            'signatoryName' => (string) (($data['signatory_name'] ?? '') ?: $head?->name),
            'signatoryPosition' => (string) (($data['signatory_position'] ?? '') ?: ($head?->position ?: 'School Head')),
            // The month chosen is the returned period: it lists the tax withheld from the payments of the month before it.
            'report' => $alphalist->build($school, $month->copy()->subMonthNoOverflow(), $fund !== '' ? $fund : null),
        ];
    }

    private function schoolIds(Request $request)
    {
        $user = $request->user();

        return $user->seesAllSchools() || $user->organization_id ? School::query()->pluck('id') : School::query()->whereKey($user->school_id)->pluck('id');
    }
}
