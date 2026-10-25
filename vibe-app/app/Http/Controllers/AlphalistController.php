<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\AlphalistService;
use App\Support\SignatorySlots;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** The Alphalist - Tax Remittance (Monthly Alphalist of Payees) of a school for one month, printed from the Liquidation page. */
class AlphalistController extends Controller
{
    public function __invoke(Request $request, AlphalistService $alphalist)
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
            'source_of_fund' => ['nullable', 'string', 'max:100'],
            'school_tin' => ['nullable', 'string', 'max:50'],
            'signatory_name' => ['nullable', 'string', 'max:150'],
            'signatory_position' => ['nullable', 'string', 'max:150'],
        ]);
        $user = $request->user();
        $schoolIds = $user->seesAllSchools() || $user->organization_id ? School::query()->pluck('id') : School::query()->whereKey($user->school_id)->pluck('id');
        abort_unless($schoolIds->contains((int) $data['school_id']), 403);

        $school = School::findOrFail($data['school_id']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $fund = trim((string) ($data['source_of_fund'] ?? ''));
        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first();
        $head = SignatorySlots::defaults('disbursement_voucher', SchoolStaff::active()->where('school_id', $school->id)->get())['head'] ?? null;

        return view('alphalist-print', [
            'school' => $school,
            'department' => (string) ($agency?->department_name ?: 'Department of Education'),
            'division' => (string) ($agency?->division_name ?: $school->division),
            'period' => mb_strtoupper($month->format('F Y')),
            'fund' => $fund,
            'schoolTin' => (string) ($data['school_tin'] ?? ''),
            'signatoryName' => (string) (($data['signatory_name'] ?? '') ?: $head?->name),
            'signatoryPosition' => (string) (($data['signatory_position'] ?? '') ?: ($head?->position ?: 'School Head')),
            'report' => $alphalist->build($school, $month, $fund !== '' ? $fund : null),
        ]);
    }
}
