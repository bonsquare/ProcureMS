<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use App\Models\School;
use App\Services\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialReportController extends Controller
{
    public const REPORTS = ['tb' => 'Trial Balance', 'gl' => 'General Ledger', 'sl' => 'Subsidiary Ledger', 'is' => 'Income Statement', 'bs' => 'Balance Sheet', 'cf' => 'Cash Flow'];

    /** The schools this user may report on (the same rule as the Cash page). */
    private function schoolIds()
    {
        $user = request()->user();

        return $user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    public function index(Request $request, GeneralLedgerService $books)
    {
        abort_unless($request->user()->hasPermission('accounting.view') || $request->user()->hasPermission('accounting.manage'), 403, 'Your role does not have permission to perform this action.');
        $input = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(self::REPORTS))],
            'school_id' => ['nullable', 'integer'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'account' => ['nullable', 'string', 'max:50'],
        ]);

        $schoolIds = $this->schoolIds();
        $schoolId = (int) ($input['school_id'] ?? $schoolIds->first());
        abort_unless($schoolIds->contains($schoolId), 403);

        $report = $input['report'] ?? 'tb';
        $fund = $input['fund_cluster'] ?? null;
        $from = Carbon::parse($input['from'] ?? now()->startOfYear())->startOfDay();
        $to = Carbon::parse($input['to'] ?? now())->startOfDay();
        $account = $input['account'] ?? null;
        $ids = [$schoolId];

        $all = $books->postings($ids, null);
        $data = match ($report) {
            'tb' => $books->trialBalance($ids, $fund, $from, $to),
            'gl' => $account ? $books->ledger($account, $ids, $fund, $from, $to) : null,
            'sl' => $account ? $books->subsidiary($account, $ids, $fund, $from, $to) : null,
            'is' => $books->incomeStatement($ids, $fund, $from, $to),
            'bs' => $books->balanceSheet($ids, $fund, $to),
            'cf' => $books->cashFlow($ids, $fund, $from, $to),
        };

        return view('financial-reports', [
            'reports' => self::REPORTS, 'report' => $report, 'data' => $data, 'account' => $account,
            'accounts' => $all->unique('account_code')->pluck('account_title', 'account_code')->sortKeys(),
            'schools' => School::query()->whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']), 'schoolId' => $schoolId,
            'funds' => $all->pluck('fund_cluster')->push(GeneralLedgerService::DEFAULT_FUND)->unique()->values(), 'fund' => $fund,
            'from' => $from, 'to' => $to,
            'openings' => OpeningBalance::query()->where('school_id', $schoolId)->orderBy('account_code')->get(),
            'canManage' => $request->user()->hasPermission('accounting.manage'),
        ]);
    }

    public function storeOpening(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'as_of' => ['required', 'date'],
            'account_code' => ['required', 'string', 'max:50'],
            'debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        abort_unless($this->schoolIds()->contains((int) $data['school_id']), 403);
        $school = School::findOrFail($data['school_id']);
        $account = ChartOfAccount::query()->where('organization_id', $school->organization_id)->where('code', $data['account_code'])->first();
        $fund = ($data['fund_cluster'] ?? null) ?: GeneralLedgerService::DEFAULT_FUND;
        $debit = (float) ($data['debit'] ?? 0);
        $credit = (float) ($data['credit'] ?? 0);

        if (! $account) {
            return back()->withErrors(['account_code' => 'That account is not in the Chart of Accounts.'])->withInput();
        }
        if ($debit <= 0 && $credit <= 0) {
            return back()->withErrors(['debit' => 'Enter a debit or a credit amount.'])->withInput();
        }
        if (OpeningBalance::query()->where(['school_id' => $school->id, 'fund_cluster' => $fund, 'account_code' => $account->code])->exists()) {
            return back()->withErrors(['account_code' => 'This account already has an opening balance for that fund; delete it first to change it.'])->withInput();
        }

        OpeningBalance::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'fund_cluster' => $fund, 'as_of' => $data['as_of'], 'account_code' => $account->code, 'account_title' => $account->title, 'debit' => $debit, 'credit' => $credit]);

        return back()->with('success', 'Opening balance added.');
    }

    public function destroyOpening(OpeningBalance $openingBalance): RedirectResponse
    {
        abort_unless($this->schoolIds()->contains($openingBalance->school_id), 403);
        $openingBalance->delete();

        return back()->with('success', 'Opening balance deleted.');
    }
}
