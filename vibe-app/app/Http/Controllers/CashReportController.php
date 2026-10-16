<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\CashFundReceipt;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashReportController extends Controller
{
    /** The schools this user may report on (the same rule as the Cash page). */
    private function schoolIds()
    {
        $user = request()->user();

        return $user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    /** @return array<string, mixed> */
    private function validatedReceipt(Request $request): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'received_at' => ['required', 'date'],
            'kind' => ['required', Rule::in(array_keys(CashFundReceipt::KINDS))],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);
        $data['fund_cluster'] = $data['fund_cluster'] ?: 'General Fund';

        return $data;
    }

    private function back(int $schoolId, string $message): RedirectResponse
    {
        return redirect()->route('cash', ['tab' => 'reports', 'school_id' => $schoolId])->with('success', $message);
    }

    private function audit(Request $request, string $action, CashFundReceipt $receipt): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $receipt->school_id, 'action' => $action, 'auditable_type' => CashFundReceipt::class, 'auditable_id' => $receipt->id, 'metadata' => ['kind' => $receipt->kind, 'amount' => $receipt->amount, 'received_at' => $receipt->received_at?->toDateString()]]);
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $data = $this->validatedReceipt($request);
        $school = School::findOrFail($data['school_id']);
        $receipt = CashFundReceipt::create($data + ['organization_id' => $school->organization_id, 'created_by' => $request->user()->id]);
        $this->audit($request, 'cash_fund_received', $receipt);

        return $this->back($receipt->school_id, 'The fund received was recorded.');
    }

    public function updateReceipt(Request $request, CashFundReceipt $cashFundReceipt): RedirectResponse
    {
        abort_unless($this->schoolIds()->contains($cashFundReceipt->school_id), 403);
        $data = $this->validatedReceipt($request);
        abort_unless((int) $data['school_id'] === $cashFundReceipt->school_id, 403);
        $cashFundReceipt->update($data);
        $this->audit($request, 'cash_fund_updated', $cashFundReceipt);

        return $this->back($cashFundReceipt->school_id, 'The fund received was updated.');
    }

    public function destroyReceipt(Request $request, CashFundReceipt $cashFundReceipt): RedirectResponse
    {
        abort_unless($this->schoolIds()->contains($cashFundReceipt->school_id), 403);
        $this->audit($request, 'cash_fund_deleted', $cashFundReceipt);
        $cashFundReceipt->delete();

        return $this->back($cashFundReceipt->school_id, 'The fund received was deleted.');
    }

    /**
     * The school, the period and the numbers typed on a report page; refuses a school the user may not report on.
     *
     * @return array{school: School, from: Carbon, to: Carbon, report_no: string, sheet_no: string}
     */
    private function reportContext(Request $request): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'report_no' => ['nullable', 'string', 'max:50'],
            'sheet_no' => ['nullable', 'string', 'max:50'],
        ]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);

        return [
            'school' => School::findOrFail($data['school_id']),
            'from' => Carbon::parse($data['from'])->startOfDay(),
            'to' => Carbon::parse($data['to'])->startOfDay(),
            'report_no' => (string) ($data['report_no'] ?? ''),
            'sheet_no' => (string) ($data['sheet_no'] ?? ''),
        ];
    }

    /**
     * Everything the three print pages share: the school, the agency, the Disbursing Officer, the bank, the fund cluster, the period text and the figures.
     *
     * @return array<string, mixed>
     */
    private function reportViewData(Request $request): array
    {
        $context = $this->reportContext($request);
        $school = $context['school'];
        $latestPayment = LiquidationReport::query()->where('school_id', $school->id)->whereNotNull('paid_at')->latest('paid_at')->first();
        $bank = trim(($school->bank_name ?: $latestPayment?->bank_name).' / '.($school->bank_account_number ?: $latestPayment?->bank_account_number), ' /');

        return [
            'school' => $school,
            'agency' => AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first(),
            'officer' => SchoolStaff::active()->where('school_id', $school->id)->get()->first(fn (SchoolStaff $staff) => $staff->hasRole('document_role', 'Disbursing Officer')),
            'bank' => $bank,
            'fundCluster' => $latestPayment?->payment_fund_cluster ?: 'General Fund',
            'from' => $context['from'],
            'to' => $context['to'],
            'period' => $this->periodText($context['from'], $context['to']),
            'reportNo' => $context['report_no'],
            'sheetNo' => $context['sheet_no'],
            'data' => app(CashReportService::class)->build($school, $context['from'], $context['to']),
        ];
    }

    /** "3RD Quarter C.Y. 2026" when the dates are a whole quarter, else the two dates. */
    private function periodText(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($from->copy()->startOfQuarter()) && $to->isSameDay($from->copy()->endOfQuarter()) && $from->year === $to->year) {
            return strtoupper(['1st', '2nd', '3rd', '4th'][$from->quarter - 1]).' Quarter C.Y. '.$from->year;
        }

        return $from->format('F j, Y').' to '.$to->format('F j, Y');
    }

    public function roci(Request $request)
    {
        return view('cash-report-roci', ['report' => $this->reportViewData($request)]);
    }

    public function checksAdvices(Request $request)
    {
        return view('cash-report-checks-advices', ['report' => $this->reportViewData($request)]);
    }

    public function register(Request $request)
    {
        $this->reportContext($request);
        abort(501, 'The Cash Disbursement Register is not built yet.');
    }
}
