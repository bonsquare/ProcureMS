<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\DvJournalLine;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public const PAYMENT_MODES = ['MDS Check', 'Commercial Check', 'ADA', 'Others'];

    /** The Accounting Entry table of the printed DV has room for this many lines. */
    public const JOURNAL_MAX_LINES = 4;

    private function isMaster(): bool
    {
        return request()->user()->role === 'master_user';
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
        return LiquidationReport::with(['school', 'procurementRequest', 'submitter'])->whereIn('school_id', $this->schoolIds());
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

        return view('finance', [
            'section' => 'accounting',
            'tab' => $tab,
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
        $all = $this->reports()->where('status', 'approved')->whereNotNull('dv_number')->get();
        $paid = $all->whereNotNull('paid_at');
        $unpaid = $all->whereNull('paid_at');

        return view('finance', [
            'section' => 'cash',
            'tab' => $tab,
            'isMasterUser' => $this->isMaster(),
            'counts' => collect(['unpaid' => $unpaid->count(), 'paid' => $paid->count()]),
            'rows' => ($tab === 'paid' ? $paid : $unpaid)->sortByDesc($tab === 'paid' ? 'paid_at' : 'approved_at'),
            'metrics' => [
                ['DVs For Payment', $unpaid->count(), 'schedule', 'error', false],
                ['Payable Amount', $unpaid->sum('amount'), 'request_quote', 'primary', true],
                ['Paid This Month', $paid->filter(fn ($r) => $r->paid_at->isSameMonth(now()))->sum('amount'), 'payments', 'secondary', true],
                ['Total Disbursed', $paid->sum('amount'), 'account_balance_wallet', 'secondary', true],
            ],
        ]);
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
        ]);

        $liquidationReport->update($data + ['paid_by' => $request->user()->id]);
        $liquidationReport->transaction?->update(['status' => 'completed']);
        $liquidationReport->transaction?->recordEvent('cash', 'payment_recorded', 'unpaid', 'paid', $data['payment_reference'] ?? null, ['payment_mode' => $data['payment_mode'], 'paid_at' => $data['paid_at']]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_paid', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $liquidationReport->dv_number, 'mode' => $data['payment_mode']]]);

        return redirect()->route('cash')->with('success', "Payment recorded for {$liquidationReport->ors_number} (DV {$liquidationReport->dv_number}).");
    }

    private function nextDvNumber(): string
    {
        return app(DocumentNumberService::class)
            ->preview((int) request()->user()->organization_id, 'disbursement_voucher', 'DV');
    }

    public function createDv(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($request->user()->hasPermission('accounting.approve') && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->status === 'approved', 422, 'Approve the ORS before creating a disbursement voucher.');
        abort_if($liquidationReport->dv_number, 422, 'A DV already exists for this ORS.');

        $data = $request->validate([
            'dv_number' => ['nullable', 'string', 'max:100', Rule::unique('liquidation_reports', 'dv_number')->where('organization_id', $liquidationReport->organization_id)],
            'dv_date' => ['required', 'date', 'before_or_equal:today'],
            'payee' => ['required', 'string', 'max:255'],
            'dv_particulars' => ['required', 'string', 'max:255'],
            'payment_mode' => ['required', Rule::in(self::PAYMENT_MODES)],
            'dv_include_appropriation' => ['nullable', 'boolean'],
            'journal' => ['required', 'array', 'min:2', 'max:'.self::JOURNAL_MAX_LINES],
            'journal.*.account_code' => ['required', 'string', 'max:50'],
            'journal.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'journal.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'journal.required' => 'Add the journal entry: at least one debit and one credit line.',
            'journal.min' => 'The journal entry needs at least two lines (a debit and a credit).',
            'journal.max' => 'The DV has room for '.self::JOURNAL_MAX_LINES.' accounting entry lines.',
        ]);
        $lines = $this->journalLines($data['journal'], $liquidationReport);
        unset($data['journal']);
        $data['dv_include_appropriation'] = (bool) ($data['dv_include_appropriation'] ?? false);

        $data['dv_number'] ??= app(DocumentNumberService::class)
            ->next((int) $liquidationReport->organization_id, 'disbursement_voucher', 'DV');
        DB::transaction(function () use ($liquidationReport, $data, $lines) {
            $liquidationReport->update($data);
            foreach ($lines as $number => $line) {
                DvJournalLine::create($line + ['organization_id' => $liquidationReport->organization_id, 'liquidation_report_id' => $liquidationReport->id, 'line_no' => $number + 1]);
            }
        });
        $liquidationReport->transaction?->recordEvent('accounting', 'dv_created', null, 'ready_for_payment', $data['dv_number'], ['dv_date' => $data['dv_date']]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'dv_created', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $data['dv_number'], 'ors' => $liquidationReport->ors_number]]);

        return redirect()->route('accounting', ['tab' => 'with_dv'])->with('success', "DV {$data['dv_number']} created for {$liquidationReport->ors_number}. It is now queued in Cash for payment.");
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
        $data = $request->validate(['dv_include_appropriation' => ['required', 'boolean']]);
        $liquidationReport->update(['dv_include_appropriation' => (bool) $data['dv_include_appropriation']]);

        return back()->with('success', 'DV '.$liquidationReport->dv_number.': appropriation table '.($liquidationReport->dv_include_appropriation ? 'will be printed' : 'removed from the print').'.');
    }

    public function printDv(LiquidationReport $liquidationReport)
    {
        abort_unless($this->schoolIds()->contains($liquidationReport->school_id) && $liquidationReport->dv_number, 404);
        $liquidationReport->load(['school', 'procurementRequest']);
        $staff = SchoolStaff::where('school_id', $liquidationReport->school_id)->get();
        $find = fn (string $needle) => $staff->first(fn ($m) => str_contains(strtolower($m->document_role.' '.$m->position), $needle));

        return view('dv-print', [
            'report' => $liquidationReport,
            'agency' => AgencySetting::first() ?? new AgencySetting,
            'accountant' => $find('accountant'),
            'head' => $find('school head') ?? $find('head'),
        ]);
    }
}
