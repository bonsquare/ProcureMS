<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\CashRegisterCheck;
use App\Models\CashReportHistory;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\CashReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'source_of_fund' => ['required', 'string', 'max:50', Rule::notIn(['Others'])],
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
     * @return array{school: School, from: Carbon, to: Carbon, report_no: string, sheet_no: string, fund_cluster: string, entity_name: string, district: string, municipality: string, station: string, registered_no: string, officer_name: string, officer_position: string, bank_name: string, bank_account: string, remarks: string, officer_id: ?string, received_by_id: ?string, officer_manual: array{name: string, position: string}, received_by_manual: array{name: string, position: string}, statement_balance: ?float, outstanding_checks: float}
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
            'source_of_fund' => ['nullable', 'string', 'max:50'],
            'entity_name' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:200'],
            'station' => ['nullable', 'string', 'max:150'],
            'registered_no' => ['nullable', 'string', 'max:50'],
            'officer_name' => ['nullable', 'string', 'max:150'],
            'officer_position' => ['nullable', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'officer_id' => ['nullable', 'string', 'max:20'],
            'received_by_id' => ['nullable', 'string', 'max:20'],
            'officer_manual_name' => ['nullable', 'string', 'max:150'],
            'officer_manual_position' => ['nullable', 'string', 'max:150'],
            'received_by_manual_name' => ['nullable', 'string', 'max:150'],
            'received_by_manual_position' => ['nullable', 'string', 'max:150'],
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
            'source_of_fund' => trim((string) ($data['source_of_fund'] ?? '')),
            'entity_name' => (string) ($data['entity_name'] ?? ''),
            'district' => (string) ($data['district'] ?? ''),
            'municipality' => (string) ($data['municipality'] ?? ''),
            'station' => (string) ($data['station'] ?? ''),
            'registered_no' => (string) ($data['registered_no'] ?? ''),
            'officer_name' => (string) ($data['officer_name'] ?? ''),
            'officer_position' => (string) ($data['officer_position'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? ''),
            'bank_account' => (string) ($data['bank_account'] ?? ''),
            'remarks' => (string) ($data['remarks'] ?? ''),
            'officer_id' => $data['officer_id'] ?? null,
            'received_by_id' => $data['received_by_id'] ?? null,
            'officer_manual' => ['name' => (string) ($data['officer_manual_name'] ?? ''), 'position' => (string) ($data['officer_manual_position'] ?? '')],
            'received_by_manual' => ['name' => (string) ($data['received_by_manual_name'] ?? ''), 'position' => (string) ($data['received_by_manual_position'] ?? '')],
            'statement_balance' => isset($data['statement_balance']) ? (float) $data['statement_balance'] : null,
            'outstanding_checks' => (float) ($data['outstanding_checks'] ?? 0),
        ];
    }

    /**
     * Everything the three print pages share: the school, the agency, the Disbursing Officer, the bank, the fund cluster, the period text and the figures.
     *
     * @return array<string, mixed>
     */
    /**
     * What the header of the Cash Disbursement Register starts from, read from the school's profile and the agency settings; the window can change each value.
     *
     * @return array{entity_name: string, district: string, municipality: string, registered_no: string, officer_name: string, officer_position: string}
     */
    public static function headerDefaults(School $school): array
    {
        $agency = AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first();
        $officer = SchoolStaff::active()->where('school_id', $school->id)->get()->first(fn (SchoolStaff $staff) => $staff->hasRole('document_role', 'Disbursing Officer'));

        return [
            'entity_name' => (string) $school->name,
            'district' => (string) ($school->district ?: ($agency?->district_name ?: ($agency?->division_name ?: $school->division))),
            'municipality' => (string) $school->address,
            'registered_no' => (string) ($school->deped_school_id ?: $school->code),
            'officer_name' => (string) $officer?->name,
            'officer_position' => (string) $officer?->position,
        ];
    }

    private function reportViewData(Request $request, ?string $kind = null): array
    {
        $context = $this->reportContext($request);
        $school = $context['school'];
        // The Cash Disbursement Register lists only the check numbers typed in its form; the other reports read every payment.
        $checks = $kind === 'register' ? $this->registerChecks($school, $context['source_of_fund'], $context['from'])->pluck('check_number')->all() : null;
        $latestPayment = LiquidationReport::query()->where('school_id', $school->id)->whereNotNull('paid_at')->latest('paid_at')->first();
        $bankName = $context['bank_name'] !== '' ? $context['bank_name'] : (string) ($school->bank_name ?: $latestPayment?->bank_name);
        $bankAccount = $context['bank_account'] !== '' ? $context['bank_account'] : (string) ($school->bank_account_number ?: $latestPayment?->bank_account_number);
        $bank = trim($bankName.' / '.$bankAccount, ' /');

        $defaults = self::headerDefaults($school);
        // A signatory is a staff member of the school, or a person typed by hand (the choice 'manual') who is not in the staff list.
        $staffOf = fn (?string $id, array $manual) => $id === 'manual'
            ? ($manual['name'] !== '' ? (object) $manual : null)
            : ($id !== null && ctype_digit($id) ? SchoolStaff::active()->where('school_id', $school->id)->find((int) $id) : null);
        $officer = $staffOf($context['officer_id'], $context['officer_manual']) ?? SchoolStaff::active()->where('school_id', $school->id)->get()->first(fn (SchoolStaff $staff) => $staff->hasRole('document_role', 'Disbursing Officer'));

        return [
            'school' => $school,
            'agency' => AgencySetting::withoutGlobalScopes()->where('organization_id', $school->organization_id)->first(),
            'officer' => $officer,
            'receivedBy' => $staffOf($context['received_by_id'], $context['received_by_manual']),
            'entityName' => $context['entity_name'] !== '' ? $context['entity_name'] : $defaults['entity_name'],
            'district' => $context['district'] !== '' ? $context['district'] : $defaults['district'],
            'municipality' => $context['municipality'] !== '' ? $context['municipality'] : $defaults['municipality'],
            'station' => $context['station'],
            'registeredNo' => $context['registered_no'] !== '' ? $context['registered_no'] : $defaults['registered_no'],
            'officerName' => $context['officer_name'] !== '' ? $context['officer_name'] : (string) $officer?->name,
            'officerPosition' => $context['officer_position'] !== '' ? $context['officer_position'] : (string) $officer?->position,
            'bank' => $bank,
            'bankName' => $bankName,
            'bankAccount' => $bankAccount,
            'fundCluster' => $context['fund_cluster'] !== '' ? $context['fund_cluster'] : ($latestPayment?->payment_fund_cluster ?: 'General Fund'),
            'sourceOfFund' => $context['source_of_fund'],
            'remarks' => $context['remarks'],
            'statementBalance' => $context['statement_balance'],
            'outstandingChecks' => $context['outstanding_checks'],
            'from' => $context['from'],
            'to' => $context['to'],
            'period' => $this->periodText($context['from'], $context['to']),
            'reportNo' => $context['report_no'],
            'sheetNo' => $context['sheet_no'],
            'data' => app(CashReportService::class)->build($school, $context['from'], $context['to'], $context['source_of_fund'] ?: null, $checks),
        ];
    }

    /** The check numbers typed in the register form for a school, fund and quarter. */
    private function registerChecks(School $school, string $fund, Carbon $from)
    {
        return CashRegisterCheck::query()->where('school_id', $school->id)->where('source_of_fund', $fund)->whereDate('quarter_start', $from->copy()->startOfQuarter())->get()
            ->sortBy('check_number', SORT_NATURAL)->values();
    }

    /** The school, fund and quarter of the register form's check list, from the From date of the open report. */
    private function registerScope(Request $request): array
    {
        $data = $request->validate(['school_id' => ['required', 'integer'], 'source_of_fund' => ['nullable', 'string', 'max:50'], 'from' => ['required', 'date']]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);

        return [School::findOrFail($data['school_id']), trim((string) ($data['source_of_fund'] ?? '')), Carbon::parse($data['from'])->startOfDay()];
    }

    /** The paid check (or the cancelled one) of a school with this number; a check of another fund stays in the series, its amount blank. */
    private function checkOf(School $school, string $number): ?array
    {
        $paid = LiquidationReport::query()->where('school_id', $school->id)->whereNotNull('paid_at')->where('payment_reference', $number)->first();
        if ($paid) {
            return ['status' => 'Paid', 'payee' => (string) $paid->payee, 'dv_no' => (string) $paid->dv_number, 'amount' => number_format((float) $paid->amount, 2)];
        }
        $cancelled = CancelledCheck::query()->where('school_id', $school->id)->whereNotNull('paid_at')->where('check_number', $number)->first();

        return $cancelled ? ['status' => 'Cancelled', 'payee' => 'Cancelled Check', 'dv_no' => (string) $cancelled->dv_number, 'amount' => ''] : null;
    }

    /** The saved check list of the register form with the details each number pulls. */
    public function registerCheckList(Request $request): JsonResponse
    {
        [$school, $fund, $from] = $this->registerScope($request);

        return response()->json($this->registerChecks($school, $fund, $from)->map(fn (CashRegisterCheck $row) => ['id' => $row->id, 'check_number' => $row->check_number] + ($this->checkOf($school, $row->check_number) ?? ['status' => 'Not found', 'payee' => '', 'dv_no' => '', 'amount' => '']))->values());
    }

    public function storeRegisterCheck(Request $request): JsonResponse
    {
        [$school, $fund, $from] = $this->registerScope($request);
        $number = trim((string) $request->validate(['check_number' => ['required', 'string', 'max:100']])['check_number']);
        if ($this->checkOf($school, $number) === null) {
            throw ValidationException::withMessages(['check_number' => 'Check No. '.$number.' is not a paid or cancelled check of this school.']);
        }
        CashRegisterCheck::firstOrCreate(
            ['school_id' => $school->id, 'source_of_fund' => $fund, 'quarter_start' => $from->copy()->startOfQuarter()->toDateString(), 'check_number' => $number],
            ['organization_id' => $school->organization_id, 'created_by' => $request->user()->id],
        );

        return response()->json(['message' => 'The check was added to the register.']);
    }

    public function destroyRegisterCheck(CashRegisterCheck $check): JsonResponse
    {
        abort_unless($this->schoolIds()->contains($check->school_id), 403);
        $check->delete();

        return response()->json(['message' => 'The check was removed from the register.']);
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
        return view('cash-report-register', ['report' => $this->reportViewData($request, 'register')]);
    }

    /** The pages of the four cash reports by the key kept in their history. */
    private const HISTORY_VIEWS = ['roci' => 'cash-report-roci', 'checks' => 'cash-report-checks-advices', 'register' => 'cash-report-register', 'bank' => 'cash-report-bank-recon'];

    public function storeRegisterHistory(Request $request): JsonResponse
    {
        return $this->storeHistory($request, 'register');
    }

    /** Keeps a cash report exactly as it is now, when the user presses Save in its window: the finished page, the period, the fund and the report number. */
    public function storeHistory(Request $request, string $kind): JsonResponse
    {
        abort_unless(isset(self::HISTORY_VIEWS[$kind]), 404);
        $report = $this->reportViewData($request, $kind);
        if ($kind === 'roci' && $report['reportNo'] === '') {
            $report['reportNo'] = now()->format('Y-m').'-001';
        }
        $school = $report['school'];
        // A report of a period and fund is kept once: pressing Save again does not make a second copy.
        $kept = CashReportHistory::query()->where('school_id', $school->id)->where('report', $kind)->whereDate('period_from', $report['from'])->whereDate('period_to', $report['to'])
            ->when($request->input('source_of_fund'), fn ($query, $fund) => $query->where('source_of_fund', $fund), fn ($query) => $query->whereNull('source_of_fund'))->first();
        if ($kept) {
            return response()->json(['ok' => false, 'message' => 'This report was already saved to history on '.$kept->created_at->format('M d, Y g:i A').'.', 'url' => route('cash.report.history', $kept->id)], 409);
        }
        $entry = CashReportHistory::create([
            'organization_id' => $school->organization_id,
            'school_id' => $school->id,
            'report' => $kind,
            'period_from' => $report['from'],
            'period_to' => $report['to'],
            'period_text' => $report['period'],
            'source_of_fund' => $request->input('source_of_fund') ?: null,
            'report_no' => $report['reportNo'] ?: null,
            'total_payments' => $report['data']['total_paid'],
            'html' => view(self::HISTORY_VIEWS[$kind], ['report' => $report])->render(),
            'params' => $request->query(),
            'generated_by' => $request->user()->id,
        ]);

        return response()->json(['ok' => true, 'entry' => [
            'generated' => $entry->created_at->format('M d, Y g:i A'), 'report' => $entry->label(), 'period' => $entry->period_text, 'fund' => $entry->source_of_fund ?: 'All funds', 'report_no' => $entry->report_no ?: '—',
            'payments' => number_format((float) $entry->total_payments, 2), 'by' => $request->user()->name, 'url' => route('cash.report.history', $entry->id),
            'update_url' => route('cash.report.history.update', $entry->id), 'delete_url' => route('cash.report.history.destroy', $entry->id), 'name' => $entry->label().' '.$entry->period_text, 'kind' => $entry->report, 'params' => $entry->params, 'item' => view('partials.cash-history-item', ['entry' => $entry->load('generator')])->render(),
        ]]);
    }

    /** Makes a kept report again from today's figures with the header it was saved with (the reason and the user's password are asked). */
    public function updateHistory(Request $request, CashReportHistory $history)
    {
        abort_unless($this->schoolIds()->contains($history->school_id), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'password' => ['required', 'current_password'], 'query' => ['nullable', 'string', 'max:4000']]);

        // The values of the edited form (the report window) replace the kept ones; without them the kept header is used again.
        parse_str((string) ($data['query'] ?? ''), $edited);
        $params = $edited ?: ($history->params ?: array_filter(['school_id' => $history->school_id, 'from' => $history->period_from->toDateString(), 'to' => $history->period_to->toDateString(), 'source_of_fund' => $history->source_of_fund, 'report_no' => $history->report_no]));
        $remade = $request->duplicate($params, []);
        $report = $this->reportViewData($remade, $history->report);
        if ($history->report === 'roci' && $report['reportNo'] === '') {
            $report['reportNo'] = now()->format('Y-m').'-001';
        }
        $before = ['generated' => $history->created_at->format('M d, Y g:i A'), 'payments' => (string) $history->total_payments, 'report_no' => $history->report_no];
        abort_unless((int) $report['school']->id === (int) $history->school_id, 422, 'The report of a kept copy stays with its school.');
        $history->update([
            'period_from' => $report['from'],
            'period_to' => $report['to'],
            'period_text' => $report['period'],
            'source_of_fund' => $remade->input('source_of_fund') ?: null,
            'report_no' => $report['reportNo'] ?: null,
            'total_payments' => $report['data']['total_paid'],
            'html' => view(self::HISTORY_VIEWS[$history->report], ['report' => $report])->render(),
            'params' => $params,
        ]);
        $history->touch();
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $history->school_id, 'action' => 'cash_report_history_updated', 'auditable_type' => CashReportHistory::class, 'auditable_id' => $history->id, 'metadata' => ['report' => $history->label(), 'period' => $history->period_text, 'reason' => $data['reason'], 'before' => $before, 'after' => ['payments' => (string) $history->total_payments, 'report_no' => $history->report_no]]]);

        return redirect()->route('cash', ['tab' => 'reports', 'school_id' => $history->school_id])->with('success', $history->label().' of '.$history->period_text.' updated from today\'s figures.');
    }

    /** Removes a kept report (the reason and the user's password are asked); the report itself can be saved again. */
    public function destroyHistory(Request $request, CashReportHistory $history)
    {
        abort_unless($this->schoolIds()->contains($history->school_id), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'password' => ['required', 'current_password']]);

        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $history->school_id, 'action' => 'cash_report_history_deleted', 'auditable_type' => CashReportHistory::class, 'auditable_id' => $history->id, 'metadata' => ['report' => $history->label(), 'period' => $history->period_text, 'fund' => $history->source_of_fund, 'report_no' => $history->report_no, 'reason' => $data['reason']]]);
        $history->delete();

        return redirect()->route('cash', ['tab' => 'reports', 'school_id' => $history->school_id])->with('success', $history->label().' of '.$history->period_text.' removed from the history.');
    }

    public function showHistory(CashReportHistory $history)
    {
        abort_unless($this->schoolIds()->contains($history->school_id), 403);

        return response($history->html)->header('Content-Type', 'text/html; charset=UTF-8');
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
                'reference' => (string) $receipt->reference, 'note' => (string) $receipt->note, 'source_of_fund' => (string) $receipt->source_of_fund, 'fund_cluster' => (string) $receipt->fund_cluster, 'bank_account_number' => (string) $receipt->bank_account_number,
            ])->values());
    }
}
