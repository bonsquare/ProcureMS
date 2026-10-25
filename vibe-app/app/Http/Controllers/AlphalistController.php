<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\CashReportHistory;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\AlphalistService;
use App\Support\SignatorySlots;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
            'params' => $request->except('_token'),
            'generated_by' => $request->user()->id,
        ]);

        return redirect()->route('liquidation.alphalist.history', $entry);
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
