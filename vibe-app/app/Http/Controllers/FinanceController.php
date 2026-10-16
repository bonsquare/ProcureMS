<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\CancelledCheck;
use App\Models\CashFundReceipt;
use App\Models\ChartOfAccount;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\DocumentNumberService;
use App\Support\SignatorySlots;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public const PAYMENT_MODES = ['MDS Check', 'Commercial Check', 'ADA', 'Others'];

    /** The Accounting Entry table of the printed DV has room for this many lines. */
    public const JOURNAL_MAX_LINES = 20;

    /** The Less: block of the printed DV has room for this many deduction lines (the usual four and others typed by name). */
    public const DEDUCTION_MAX_LINES = 6;

    private function isMaster(): bool
    {
        return request()->user()->seesAllSchools();
    }

    private function schoolIds()
    {
        $user = request()->user();

        return $this->isMaster() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    private function reports()
    {
        return LiquidationReport::with(['school', 'procurementRequest.documents', 'submitter', 'journalLines', 'cancelledChecks'])->whereIn('school_id', $this->schoolIds());
    }

    public function accounting(Request $request)
    {
        $tab = $request->query('tab', 'for_review');
        $all = $this->reports()->get();
        $counts = $all->groupBy('status')->map->count();
        $forDv = $all->where('status', 'approved')->whereNull('dv_number');
        $withDv = $all->whereNotNull('dv_number');
        $counts->put('for_dv', $forDv->count());
        $counts->put('with_dv', $withDv->count());

        $staff = SchoolStaff::active()->whereIn('school_id', $all->pluck('school_id')->unique())->get()->groupBy('school_id');
        $panel = fn (int $schoolId, ?array $saved) => SignatorySlots::panel('disbursement_voucher', $staff->get($schoolId, collect()), $saved);

        return view('finance', [
            'section' => 'accounting',
            'tab' => $tab,
            'dvDialogPanels' => $forDv->pluck('school_id')->unique()->mapWithKeys(fn ($schoolId) => [$schoolId => $panel((int) $schoolId, null)])->all(),
            'dvRowPanels' => $withDv->mapWithKeys(fn ($report) => [$report->id => $panel((int) $report->school_id, $report->signatories['dv'] ?? null)])->all(),
            'isMasterUser' => $this->isMaster(),
            'counts' => $counts,
            'nextDv' => $this->nextDvNumber(),
            'journalAccounts' => $this->journalAccountOptions(),
            'rows' => match ($tab) {
                'all' => $all->sortByDesc('created_at'),
                'for_dv' => $forDv->sortByDesc('approved_at'),
                'with_dv' => $withDv->sortByDesc('dv_date'),
                default => $all->where('status', $tab)->sortByDesc('created_at'),
            },
            'metrics' => [
                ['For Review', $counts->get('for_review', 0), 'pending_actions', 'error', false],
                ['Pending Documents', $counts->get('pending_documents', 0), 'folder_open', 'primary', false],
                ['Ready for DV', $forDv->count(), 'description', 'secondary', false],
                ['Total ORS Amount', $all->sum('amount'), 'payments', 'primary', true],
            ],
        ]);
    }

    public function review(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('accounting.approve') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['for_review', 'pending_documents', 'approved', 'returned'])],
            'accounting_remarks' => [Rule::requiredIf(in_array($request->input('status'), ['pending_documents', 'returned'])), 'nullable', 'string', 'max:255'],
        ]);
        abort_if($liquidationReport->paid_at && $data['status'] !== 'approved', 422, 'A paid ORS cannot be reopened.');

        $previousStatus = $liquidationReport->status;
        $liquidationReport->update([
            'status' => $data['status'],
            'accounting_remarks' => $data['accounting_remarks'] ?? null,
            'approved_at' => $data['status'] === 'approved' ? now() : null,
        ]);
        $liquidationReport->transaction?->recordEvent('accounting', 'ors_reviewed', $previousStatus, $data['status'], $liquidationReport->ors_number);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_'.$data['status'], 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['ors' => $liquidationReport->ors_number]]);

        return back()->with('success', ($liquidationReport->ors_number ?: $liquidationReport->report_number).' marked '.str_replace('_', ' ', $data['status']).'.');
    }

    public function cash(Request $request)
    {
        $tab = $request->query('tab', 'unpaid');
        if ($tab === 'reports') {
            abort_unless($request->user()->hasPermission('cash.pay'), 403);
        }
        $all = $this->reports()->where('status', 'approved')->whereNotNull('dv_number')->get();
        $paid = $all->whereNotNull('paid_at');
        $unpaid = $all->whereNull('paid_at');
        $cancelledChecks = CancelledCheck::with('canceller')->whereIn('school_id', $this->schoolIds())->latest('cancelled_at')->get();

        return view('finance', [
            'section' => 'cash',
            'tab' => $tab,
            'isMasterUser' => $this->isMaster(),
            'counts' => collect(['unpaid' => $unpaid->count(), 'paid' => $paid->count(), 'cancelled' => $cancelledChecks->count()]),
            'cancelledChecks' => $tab === 'cancelled' ? $cancelledChecks : collect(),
            'rows' => in_array($tab, ['cancelled', 'reports'], true) ? collect() : ($tab === 'paid' ? $paid : $unpaid)->sortByDesc($tab === 'paid' ? 'paid_at' : 'approved_at'),
            'metrics' => [
                ['DVs For Payment', $unpaid->count(), 'schedule', 'error', false],
                ['Payable Amount', $unpaid->sum('amount'), 'request_quote', 'primary', true],
                ['Paid This Month', $paid->filter(fn ($r) => $r->paid_at->isSameMonth(now()))->sum('amount'), 'payments', 'secondary', true],
                ['Total Disbursed', $paid->sum('amount'), 'account_balance_wallet', 'secondary', true],
            ],
        ] + ($tab === 'reports' ? $this->reportsTabData($request) : []));
    }

    /** The school, the period and the fund received that the Reports tab of Cash shows (the period defaults to the current quarter). */
    private function reportsTabData(Request $request): array
    {
        $schools = School::query()->whereIn('id', $this->schoolIds())->orderBy('name')->get();
        $school = $schools->firstWhere('id', (int) $request->query('school_id')) ?? $schools->first();
        $quarterStart = now()->startOfQuarter();
        $from = $this->reportDate($request->query('from'), $quarterStart);
        $to = $this->reportDate($request->query('to'), $quarterStart->copy()->endOfQuarter());

        return [
            'reportSchools' => $schools,
            'reportSchool' => $school,
            'reportFrom' => $from,
            'reportTo' => $to,
            'fundReceipts' => $school ? CashFundReceipt::query()->where('school_id', $school->id)->orderByDesc('received_at')->orderByDesc('id')->limit(50)->get() : collect(),
        ];
    }

    private function reportDate(?string $value, CarbonInterface $default): CarbonInterface
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : $default->copy()->startOfDay();
        } catch (\Throwable) {
            return $default->copy()->startOfDay();
        }
    }

    public function pay(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('cash.pay') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->status === 'approved' && $liquidationReport->dv_number, 422, 'A disbursement voucher must be created in Accounting first.');
        abort_if($liquidationReport->paid_at, 422, 'This ORS is already paid.');

        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(self::PAYMENT_MODES)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'check_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'payment_fund_cluster' => ['nullable', 'string', 'max:100'],
            'received_by' => ['nullable', 'string', 'max:120'],
            'payment_remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $liquidationReport->update($data + ['paid_by' => $request->user()->id, 'reissue_pending' => false]);
        $liquidationReport->transaction?->update(['status' => 'completed']);
        $liquidationReport->transaction?->recordEvent('cash', 'payment_recorded', 'unpaid', 'paid', $data['payment_reference'] ?? null, ['payment_mode' => $data['payment_mode'], 'paid_at' => $data['paid_at']]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_paid', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $liquidationReport->dv_number, 'mode' => $data['payment_mode']]]);

        return redirect()->route('cash')->with('success', "Payment recorded for {$liquidationReport->ors_number} (DV {$liquidationReport->dv_number}).");
    }

    /** Cancels a paid check: it goes to the Cancelled Checks register and the ORS, DV and journal entry open for editing. */
    public function cancelCheck(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('cash.pay') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->paid_at, 422, 'Only a paid check can be cancelled.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'current_password'],
        ]);

        $oldJournal = $liquidationReport->journalLines->map(fn ($line) => [$line->account_code, (string) $line->debit, (string) $line->credit])->all();
        $oldDeductions = $liquidationReport->dv_deductions;
        $dvNumber = $liquidationReport->dv_number;
        DB::transaction(function () use ($request, $liquidationReport, $data) {
            CancelledCheck::create([
                'organization_id' => $liquidationReport->organization_id, 'school_id' => $liquidationReport->school_id, 'liquidation_report_id' => $liquidationReport->id,
                'ors_number' => $liquidationReport->ors_number, 'dv_number' => $liquidationReport->dv_number, 'check_number' => $liquidationReport->payment_reference,
                'payment_mode' => $liquidationReport->payment_mode, 'paid_at' => $liquidationReport->paid_at, 'amount' => $liquidationReport->amount,
                'reason' => $data['reason'], 'cancelled_by' => $request->user()->id, 'cancelled_at' => now(),
            ]);
            $liquidationReport->journalLines()->delete();
            $liquidationReport->update([
                'paid_at' => null, 'payment_reference' => null, 'payment_mode' => null, 'paid_by' => null,
                'check_date' => null, 'bank_name' => null, 'bank_account_name' => null, 'bank_account_number' => null, 'payment_fund_cluster' => null, 'received_by' => null, 'payment_remarks' => null,
                'dv_number' => null, 'dv_date' => null, 'dv_particulars' => null, 'dv_deductions' => null,
                'status' => 'for_review', 'approved_at' => null, 'reissue_pending' => true,
            ]);
            $liquidationReport->transaction?->update(['status' => 'liquidation']);
        });
        $liquidationReport->transaction?->recordEvent('cash', 'check_cancelled', 'paid', 'unpaid', $dvNumber, ['reason' => $data['reason']]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'check_cancelled', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $dvNumber, 'reason' => $data['reason'], 'journal' => $oldJournal, 'deductions' => $oldDeductions]]);

        return redirect()->route('cash', ['tab' => 'cancelled'])->with('success', "Check cancelled for {$liquidationReport->ors_number} (DV {$dvNumber}). Edit the ORS in Accounting (For Review), approve it, create the DV again, then record the new payment.");
    }

    private function nextDvNumber(): string
    {
        return app(DocumentNumberService::class)
            ->preview((int) request()->user()->organization_id, 'disbursement_voucher', 'DV');
    }

    public function createDv(Request $request, LiquidationReport $liquidationReport, bool $preview = false)
    {
        abort_unless($request->user()->hasPermission('accounting.approve') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->status === 'approved', 422, 'Approve the ORS before creating a disbursement voucher.');
        abort_if($liquidationReport->paid_at, 422, 'A paid DV cannot be edited. Cancel the check first.');
        $editing = (bool) $liquidationReport->dv_number;

        $data = $request->validate([
            'dv_number' => ['nullable', 'string', 'max:100', Rule::unique('liquidation_reports', 'dv_number')->where('organization_id', $liquidationReport->organization_id)->ignore($liquidationReport->id)],
            'dv_date' => ['required', 'date', 'before_or_equal:today'],
            'payee' => ['required', 'string', 'max:255'],
            'dv_particulars' => ['required', 'string', 'max:255'],
            'dv_fund_cluster' => ['nullable', 'string', 'max:100'],
            'payment_mode' => ['required', Rule::in(self::PAYMENT_MODES)],
            'dv_include_appropriation' => ['nullable', 'boolean'],
            'deductions' => ['nullable', 'array'],
            'deductions.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'deductions_other' => ['nullable', 'array', 'max:'.(self::DEDUCTION_MAX_LINES - count(LiquidationReport::DV_DEDUCTIONS))],
            'deductions_other.*.label' => ['nullable', 'string', 'max:40'],
            'deductions_other.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'journal' => $preview ? ['nullable', 'array', 'max:'.self::JOURNAL_MAX_LINES] : ['required', 'array', 'min:2', 'max:'.self::JOURNAL_MAX_LINES],
            'journal.*.account_code' => [$preview ? 'nullable' : 'required', 'string', 'max:50'],
            'journal.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'journal.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ] + ($editing ? [
            'reason' => [$preview ? 'nullable' : 'required', 'string', 'max:255'],
            'password' => [$preview ? 'nullable' : 'required', 'current_password'],
        ] : []), [
            'journal.required' => 'Add the journal entry: at least one debit and one credit line.',
            'journal.min' => 'The journal entry needs at least two lines (a debit and a credit).',
            'journal.max' => 'The DV has room for '.self::JOURNAL_MAX_LINES.' accounting entry lines.',
        ]);
        $before = $editing ? ['particulars' => $liquidationReport->dv_particulars, 'deductions' => $liquidationReport->dv_deductions, 'journal' => $liquidationReport->journalLines->map(fn ($line) => [$line->account_code, (string) $line->debit, (string) $line->credit])->all()] : [];
        $signatories = $request->has('signatories')
            ? SignatorySlots::validated('disbursement_voucher', $request->input('signatories'), (int) $liquidationReport->school_id)
            : null;
        try {
            $lines = $this->journalLines($data['journal'] ?? [], $liquidationReport);
        } catch (ValidationException $e) {
            // A preview is drawn even while the journal entry is not finished; saving still needs it complete.
            if (! $preview) {
                throw $e;
            }
            $lines = [];
        }
        unset($data['journal'], $data['deductions'], $data['deductions_other'], $data['reason'], $data['password']);
        $deductions = collect(LiquidationReport::DV_DEDUCTIONS)->keys()
            ->mapWithKeys(fn ($key) => [$key => round((float) $request->input('deductions.'.$key, 0), 2)])->filter(fn ($value) => $value > 0);
        $others = collect($request->input('deductions_other', []))
            ->map(fn ($row) => ['label' => trim((string) ($row['label'] ?? '')), 'amount' => round((float) ($row['amount'] ?? 0), 2)])
            ->filter(fn ($row) => $row['amount'] > 0 || $row['label'] !== '')->values();
        if ($preview) {
            // The preview is drawn while the form is still being typed: a half-typed deduction is left out.
            $others = $others->reject(fn ($row) => $row['label'] === '' || $row['amount'] <= 0)->values();
        }
        if ($others->contains(fn ($row) => $row['label'] === '' || $row['amount'] <= 0)) {
            throw ValidationException::withMessages(['deductions' => 'Give each other deduction a name and an amount.']);
        }
        if ($deductions->sum() + $others->sum('amount') >= (float) $liquidationReport->amount) {
            if ($preview) {
                $deductions = collect();
                $others = collect();
            } else {
                throw ValidationException::withMessages(['deductions' => 'The deductions must be less than the amount of the DV.']);
            }
        }
        $stored = $deductions->all() + ($others->isEmpty() ? [] : ['other' => $others->all()]);
        $data['dv_deductions'] = $stored === [] ? null : $stored;
        if ($signatories !== null) {
            $data['signatories'] = $this->withDvSignatories($liquidationReport, $signatories);
        }
        $data['dv_include_appropriation'] = (bool) ($data['dv_include_appropriation'] ?? false);

        $data['dv_number'] = $editing ? $liquidationReport->dv_number : ($data['dv_number'] ?? app(DocumentNumberService::class)
            ->next((int) $liquidationReport->organization_id, 'disbursement_voucher', 'DV'));
        DB::transaction(function () use ($liquidationReport, $data, $lines, $editing) {
            if ($editing) {
                $liquidationReport->journalLines()->delete();
            }
            $liquidationReport->update($data);
            foreach ($lines as $number => $line) {
                DvJournalLine::create($line + ['organization_id' => $liquidationReport->organization_id, 'liquidation_report_id' => $liquidationReport->id, 'line_no' => $number + 1]);
            }
        });
        if ($editing) {
            $changes = ['reason' => $request->input('reason'), 'before' => $before];
            $liquidationReport->transaction?->recordEvent('accounting', 'dv_edited', null, 'ready_for_payment', $data['dv_number'], $changes);
            AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'dv_edited', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $data['dv_number'], 'ors' => $liquidationReport->ors_number] + $changes]);

            return redirect()->route('accounting', ['tab' => 'with_dv'])->with('success', LiquidationReport::dvLabel($data['dv_number'])." updated for {$liquidationReport->ors_number}.");
        }
        $liquidationReport->transaction?->recordEvent('accounting', 'dv_created', null, 'ready_for_payment', $data['dv_number'], ['dv_date' => $data['dv_date']]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'dv_created', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $data['dv_number'], 'ors' => $liquidationReport->ors_number]]);

        return redirect()->route('accounting', ['tab' => 'with_dv'])->with('success', LiquidationReport::dvLabel($data['dv_number'])." created for {$liquidationReport->ors_number}. It is now queued in Cash for payment.");
    }

    /** Corrects the amount, purpose and payee of an ORS whose check was cancelled, while it is back in review. */
    public function updateReissuedOrs(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('accounting.approve') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->reissue_pending && ! $liquidationReport->dv_number && $liquidationReport->status !== 'approved', 422, 'Only an ORS whose check was cancelled can be edited here, before it is approved again.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'payee' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'current_password'],
        ]);
        $before = $liquidationReport->only(['amount', 'purpose', 'payee']);
        $liquidationReport->update(['amount' => round((float) $data['amount'], 2), 'purpose' => $data['purpose'] ?? null, 'payee' => $data['payee']]);
        $changes = ['reason' => $data['reason'], 'before' => $before, 'after' => $liquidationReport->only(['amount', 'purpose', 'payee'])];
        $liquidationReport->transaction?->recordEvent('accounting', 'ors_edited', null, 'for_review', $liquidationReport->ors_number, $changes);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_edited', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['ors' => $liquidationReport->ors_number] + $changes]);

        return back()->with('success', ($liquidationReport->ors_number ?: $liquidationReport->report_number).' updated. Approve it to send it to Ready for DV.');
    }

    /** The official DV drawn from the form as it is now: the voucher is made and printed inside a transaction that is rolled back, so nothing is saved. */
    public function previewDv(Request $request, LiquidationReport $liquidationReport)
    {
        $request->merge([
            'dv_date' => $request->input('dv_date') ?: now()->toDateString(),
            'payment_mode' => $request->input('payment_mode') ?: self::PAYMENT_MODES[0],
        ]);

        DB::beginTransaction();
        try {
            $this->createDv($request, $liquidationReport, true);
            $saved = session()->has('success');
            $errors = session('errors');
            session()->forget(['success', 'errors', '_old_input']);

            if (! $saved) {
                $messages = $errors ? collect($errors->all())->unique()->values()->all() : ['The form is incomplete.'];

                return response()->view('procurement-document-preview-error', ['messages' => $messages]);
            }

            return response($this->printDv($liquidationReport->fresh())->render());
        } catch (ValidationException $e) {
            return response()->view('procurement-document-preview-error', ['messages' => collect($e->errors())->flatten()->all()]);
        } finally {
            DB::rollBack();
        }
    }

    /** Accounts offered in the journal entry, grouped like the chart: assets and liabilities first, then the expenses. */
    private function journalAccountOptions()
    {
        $accounts = ChartOfAccount::query()->orderBy('code')->get(['code', 'title', 'category']);
        if ($accounts->isEmpty()) {
            $accounts = collect(ChartOfAccount::standardAccounts())->map(fn (array $account) => (object) $account)->sortBy('code');
        }
        $order = ['Assets' => 0, 'Liabilities' => 1, 'Equity' => 2];

        return $accounts->unique('code')->groupBy('category')
            ->sortBy(fn ($items, $category) => ($order[$category] ?? 3).'-'.$category)
            ->map(fn ($items) => $items->map(fn ($account) => ['code' => $account->code, 'title' => $account->title])->values());
    }

    /**
     * Checks the double entry of a DV and returns the lines to save: every account is in the chart of accounts, each line is
     * a debit or a credit, and total debit equals total credit equals the DV amount.
     *
     * @param  array<int, array<string, mixed>>  $journal
     * @return array<int, array{account_code: string, account_title: string, debit: string, credit: string}>
     */
    private function journalLines(array $journal, LiquidationReport $report): array
    {
        ChartOfAccount::ensureDefaults($report->organization_id);
        $titles = ChartOfAccount::withoutGlobalScopes()->where('organization_id', $report->organization_id)
            ->whereIn('code', collect($journal)->pluck('account_code')->all())->pluck('title', 'code');

        $cents = fn ($value) => (int) round(((float) ($value ?: 0)) * 100);
        $lines = [];
        $debit = $credit = 0;
        foreach (array_values($journal) as $number => $line) {
            $position = $number + 1;
            $code = (string) $line['account_code'];
            if (! $titles->has($code)) {
                throw ValidationException::withMessages(['journal' => "Line {$position}: choose an account from the Chart of Accounts."]);
            }
            $d = $cents($line['debit'] ?? 0);
            $c = $cents($line['credit'] ?? 0);
            if (($d > 0) === ($c > 0)) {
                throw ValidationException::withMessages(['journal' => "Line {$position}: enter either a debit or a credit amount, not both and not neither."]);
            }
            $debit += $d;
            $credit += $c;
            $lines[] = ['account_code' => $code, 'account_title' => $titles[$code], 'debit' => number_format($d / 100, 2, '.', ''), 'credit' => number_format($c / 100, 2, '.', '')];
        }
        if ($debit !== $credit) {
            throw ValidationException::withMessages(['journal' => 'The journal entry is out of balance: total debit '.number_format($debit / 100, 2).' and total credit '.number_format($credit / 100, 2).' must be equal.']);
        }
        if ($debit !== $cents($report->amount)) {
            throw ValidationException::withMessages(['journal' => 'The entry totals '.number_format($debit / 100, 2).' but the DV amount is '.number_format((float) $report->amount, 2).'.']);
        }

        return $lines;
    }

    /** Turn the optional APPROPRIATION table of the printed DV on or off (off by default). */
    public function updateDvOptions(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('accounting.approve') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->dv_number, 422, 'Create the DV first.');
        $data = $request->validate([
            'dv_include_appropriation' => ['required_without:signatories', 'boolean'],
            'signatories' => ['nullable', 'array'],
        ]);
        $changes = [];
        if (array_key_exists('dv_include_appropriation', $data)) {
            $changes['dv_include_appropriation'] = (bool) $data['dv_include_appropriation'];
        }
        if ($request->has('signatories')) {
            $changes['signatories'] = $this->withDvSignatories($liquidationReport, SignatorySlots::validated('disbursement_voucher', $request->input('signatories'), (int) $liquidationReport->school_id));
        }
        $liquidationReport->update($changes);

        return back()->with('success', array_key_exists('dv_include_appropriation', $changes)
            ? LiquidationReport::dvLabel($liquidationReport->dv_number).': appropriation table '.($liquidationReport->dv_include_appropriation ? 'will be printed' : 'removed from the print').'.'
            : LiquidationReport::dvLabel($liquidationReport->dv_number).': signatories saved.');
    }

    /** The report's saved signatories with its DV choices replaced; an empty list removes them and leaves the ORS ones alone. */
    private function withDvSignatories(LiquidationReport $report, array $dv): ?array
    {
        $all = $report->signatories ?? [];
        if ($dv === []) {
            unset($all['dv']);
        } else {
            $all['dv'] = $dv;
        }

        return $all === [] ? null : $all;
    }

    public function printDv(LiquidationReport $liquidationReport)
    {
        abort_unless($this->schoolIds()->contains($liquidationReport->school_id) && $liquidationReport->dv_number, 404);
        $liquidationReport->load(['school', 'procurementRequest']);
        $staff = SchoolStaff::active()->where('school_id', $liquidationReport->school_id)->get();

        return view('dv-print', SignatorySlots::overlay('disbursement_voucher', [
            'report' => $liquidationReport,
            'agency' => AgencySetting::first() ?? new AgencySetting,
            ...SignatorySlots::defaults('disbursement_voucher', $staff),
        ], $liquidationReport->signatories['dv'] ?? null));
    }
}
