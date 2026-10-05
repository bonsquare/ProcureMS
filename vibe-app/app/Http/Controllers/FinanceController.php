<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LiquidationReport;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public const PAYMENT_MODES = ['MDS Check', 'Commercial Check', 'ADA', 'Others'];

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
        abort_unless($this->isMaster() && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['for_review', 'pending_documents', 'approved', 'returned'])],
            'accounting_remarks' => [Rule::requiredIf(in_array($request->input('status'), ['pending_documents', 'returned'])), 'nullable', 'string', 'max:255'],
        ]);
        abort_if($liquidationReport->paid_at && $data['status'] !== 'approved', 422, 'A paid ORS cannot be reopened.');

        $liquidationReport->update([
            'status' => $data['status'],
            'accounting_remarks' => $data['accounting_remarks'] ?? null,
            'approved_at' => $data['status'] === 'approved' ? now() : null,
        ]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_' . $data['status'], 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['ors' => $liquidationReport->ors_number]]);

        return back()->with('success', ($liquidationReport->ors_number ?: $liquidationReport->report_number) . ' marked ' . str_replace('_', ' ', $data['status']) . '.');
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
        abort_unless($this->isMaster() && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->status === 'approved' && $liquidationReport->dv_number, 422, 'A disbursement voucher must be created in Accounting first.');
        abort_if($liquidationReport->paid_at, 422, 'This ORS is already paid.');

        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(self::PAYMENT_MODES)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $liquidationReport->update($data + ['paid_by' => $request->user()->id]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'ors_paid', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $liquidationReport->dv_number, 'mode' => $data['payment_mode']]]);

        return redirect()->route('cash')->with('success', "Payment recorded for {$liquidationReport->ors_number} (DV {$liquidationReport->dv_number}).");
    }

    private function nextDvNumber(): string
    {
        $prefix = 'DV-' . now()->format('Y') . '-';
        $last = LiquidationReport::withoutGlobalScopes()->where('dv_number', 'like', $prefix . '%')->pluck('dv_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function createDv(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($this->isMaster() && $this->schoolIds()->contains($liquidationReport->school_id), 403);
        abort_unless($liquidationReport->status === 'approved', 422, 'Approve the ORS before creating a disbursement voucher.');
        abort_if($liquidationReport->dv_number, 422, 'A DV already exists for this ORS.');

        $data = $request->validate([
            'dv_number' => ['required', 'string', 'max:100', Rule::unique('liquidation_reports', 'dv_number')],
            'dv_date' => ['required', 'date', 'before_or_equal:today'],
            'payee' => ['required', 'string', 'max:255'],
            'dv_particulars' => ['required', 'string', 'max:255'],
            'payment_mode' => ['required', Rule::in(self::PAYMENT_MODES)],
        ]);

        $liquidationReport->update($data);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $liquidationReport->school_id, 'action' => 'dv_created', 'auditable_type' => LiquidationReport::class, 'auditable_id' => $liquidationReport->id, 'metadata' => ['dv' => $data['dv_number'], 'ors' => $liquidationReport->ors_number]]);

        return redirect()->route('accounting', ['tab' => 'with_dv'])->with('success', "DV {$data['dv_number']} created for {$liquidationReport->ors_number}. It is now queued in Cash for payment.");
    }

    public function printDv(LiquidationReport $liquidationReport)
    {
        abort_unless($this->schoolIds()->contains($liquidationReport->school_id) && $liquidationReport->dv_number, 404);
        $liquidationReport->load(['school', 'procurementRequest']);
        $staff = \App\Models\SchoolStaff::where('school_id', $liquidationReport->school_id)->get();
        $find = fn (string $needle) => $staff->first(fn ($m) => str_contains(strtolower($m->document_role . ' ' . $m->position), $needle));

        return view('dv-print', [
            'report' => $liquidationReport,
            'agency' => \App\Models\AgencySetting::first() ?? new \App\Models\AgencySetting(),
            'accountant' => $find('accountant'),
            'head' => $find('school head') ?? $find('head'),
        ]);
    }
}
