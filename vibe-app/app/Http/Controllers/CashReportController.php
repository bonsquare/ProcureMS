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
use Illuminate\Http\JsonResponse;
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
        $data['fund_cluster'] = (($data['fund_cluster'] ?? null) ?: 'General Fund');

        return $data;
    }

    private function back(int $schoolId, string $message): RedirectResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('cash', ['tab' => 'reports', 'school_id' => $schoolId])->with('success', $message);
    }

    private function audit(Request $request, string $action, CashFundReceipt $receipt): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $receipt->school_id, 'action' => $action, 'auditable_type' => CashFundReceipt::class, 'auditable_id' => $receipt->id, 'metadata' => ['kind' => $receipt->kind, 'amount' => $receipt->amount, 'received_at' => $receipt->received_at?->toDateString()]]);
    }

    public function storeReceipt(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validatedReceipt($request);
        $school = School::findOrFail($data['school_id']);
        $receipt = CashFundReceipt::create($data + ['organization_id' => $school->organization_id, 'created_by' => $request->user()->id]);
        $this->audit($request, 'cash_fund_received', $receipt);

        return $this->back($receipt->school_id, 'The fund received was recorded.');
    }

    public function updateReceipt(Request $request, CashFundReceipt $cashFundReceipt): RedirectResponse|JsonResponse
    {
        abort_unless($this->schoolIds()->contains($cashFundReceipt->school_id), 403);
        $data = $this->validatedReceipt($request);
        abort_unless((int) $data['school_id'] === $cashFundReceipt->school_id, 403);
        $cashFundReceipt->update($data);
        $this->audit($request, 'cash_fund_updated', $cashFundReceipt);

        return $this->back($cashFundReceipt->school_id, 'The fund received was updated.');
    }

    public function destroyReceipt(Request $request, CashFundReceipt $cashFundReceipt): RedirectResponse|JsonResponse
    {
        abort_unless($this->schoolIds()->contains($cashFundReceipt->school_id), 403);
        $this->audit($request, 'cash_fund_deleted', $cashFundReceipt);
        $cashFundReceipt->delete();

        return $this->back($cashFundReceipt->school_id, 'The fund received was deleted.');
    }

    /**
     * The school, the period and the numbers typed on a report page; refuses a school the user may not report on.
     *
     * @return array{school: School, from: Carbon, to: Carbon, report_no: string, sheet_no: string, fund_cluster: string, bank_name: string, bank_account: string, remarks: string, officer_id: ?int, received_by_id: ?int, statement_balance: ?float, outstanding_checks: float}
     */
    private function reportContext(Request $request): array
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'report_no' => ['nullable', 'string', 'max:50'],
            'sheet_no' => ['nullable', 'string', 'max:50'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'officer_id' => ['nullable', 'integer'],
            'received_by_id' => ['nullable', 'integer'],
            'statement_balance' => ['nullable', 'numeric', 'min:-999999999999', 'max:999999999999'],
            'outstanding_checks' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);

        return [
            'school' => School::findOrFail($data['school_id']),
            'from' => Carbon::parse($data['from'])->startOfDay(),
            'to' => Carbon::parse($data['to'])->startOfDay(),
            'report_no' => (string) ($data['report_no'] ?? ''),
            'sheet_no' => (string) ($data['sheet_no'] ?? ''),
            'fund_cluster' => (string) ($data['fund_cluster'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? ''),
            'bank_account' => (string) ($data['bank_account'] ?? ''),
            'remarks' => (string) ($data['remarks'] ?? ''),
            'officer_id' => $data['officer_id'] ?? null,
            'received_by_id' => $data['received_by_id'] ?? null,
            'statement_balance' => isset($data['statement_balance']) ? (float) $data['statement_balance'] : null,
            'outstanding_checks' => (float) ($data['outstanding_checks'] ?? 0),
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
        $bankName = $context['bank_name'] !== '' ? $context['bank_name'] : (string) ($school->bank_name ?: $latestPayment?->bank_name);
        $bankAccount = $context['bank_account'] !== '' ? $context['bank_account'] : (string) ($school->bank_account_number ?: $latestPayment?->bank_account_number);
        $bank = trim($bankName.' / '.$bankAccount, ' /');

        $staffOf = fn (?int $id) => $id ? SchoolStaff::active()->where('school_id', $school->id)->find($id) : null;

        return [
            'school' => $school,
            'agency' => AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first(),
            'officer' => $staffOf($context['officer_id']) ?? SchoolStaff::active()->where('school_id', $school->id)->get()->first(fn (SchoolStaff $staff) => $staff->hasRole('document_role', 'Disbursing Officer')),
            'receivedBy' => $staffOf($context['received_by_id']),
            'bank' => $bank,
            'bankName' => $bankName,
            'bankAccount' => $bankAccount,
            'fundCluster' => $context['fund_cluster'] !== '' ? $context['fund_cluster'] : ($latestPayment?->payment_fund_cluster ?: 'General Fund'),
            'remarks' => $context['remarks'],
            'statementBalance' => $context['statement_balance'],
            'outstandingChecks' => $context['outstanding_checks'],
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
        $report = $this->reportViewData($request);
        // Report No. is the year and month of the day the report is made, then the sequence: 2026-10-001. A number typed in the window replaces it.
        if ($report['reportNo'] === '') {
            $report['reportNo'] = now()->format('Y-m').'-001';
        }

        return view('cash-report-roci', ['report' => $report]);
    }

    public function checksAdvices(Request $request)
    {
        return view('cash-report-checks-advices', ['report' => $this->reportViewData($request)]);
    }

    public function register(Request $request)
    {
        return view('cash-report-register', ['report' => $this->reportViewData($request)]);
    }

    public function bank(Request $request)
    {
        return view('cash-report-bank-recon', ['report' => $this->reportViewData($request)]);
    }

    /** The downloaded cash (fund received) of a school in a period, for the Bank Reconciliation window. */
    public function receipts(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'integer'], 'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);

        return response()->json(CashFundReceipt::query()->where('school_id', $data['school_id'])
            ->whereBetween('received_at', [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay()])
            ->orderBy('received_at')->orderBy('id')->get()
            ->map(fn (CashFundReceipt $receipt) => [
                'id' => $receipt->id, 'received_at' => $receipt->received_at->toDateString(), 'date_label' => $receipt->received_at->format('M d, Y'),
                'kind' => $receipt->kind, 'kind_label' => $receipt->kindLabel(), 'amount' => (string) $receipt->amount, 'amount_label' => number_format((float) $receipt->amount, 2),
                'reference' => (string) $receipt->reference, 'note' => (string) $receipt->note, 'fund_cluster' => (string) $receipt->fund_cluster, 'bank_account_number' => (string) $receipt->bank_account_number,
            ])->values());
    }
}
