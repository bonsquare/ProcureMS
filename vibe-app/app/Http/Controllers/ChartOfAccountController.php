<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    private function organizationId(): ?int
    {
        return request()->user()->organization_id;
    }

    private function authorizeAccount(ChartOfAccount $account): void
    {
        abort_unless($account->organization_id === $this->organizationId(), 403);
    }

    public function index()
    {
        ChartOfAccount::ensureDefaults($this->organizationId());

        $accounts = ChartOfAccount::query()->where('organization_id', $this->organizationId())->orderBy('code')->get();
        $usage = BudgetAllocation::whereIn('chart_of_account_id', $accounts->pluck('id'))->selectRaw('chart_of_account_id, count(*) as items, sum(amount) as total')
            ->groupBy('chart_of_account_id')->get()->keyBy('chart_of_account_id');

        return view('chart-of-accounts', ['accounts' => $accounts, 'usage' => $usage, 'categories' => ChartOfAccount::categories($this->organizationId())]);
    }

    public function store(Request $request)
    {
        ChartOfAccount::create($this->validated($request) + ['organization_id' => $this->organizationId()]);

        return redirect()->route('chart-of-accounts')->with('success', 'Account added.');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $this->authorizeAccount($chartOfAccount);
        $data = $this->validated($request, $chartOfAccount);
        $chartOfAccount->update($data);

        // Budget items keep a copy of the code and title for reports, so keep them in step.
        BudgetAllocation::where('chart_of_account_id', $chartOfAccount->id)->update(['uacs_code' => $data['code'], 'particulars' => $data['title']]);

        return redirect()->route('chart-of-accounts')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $chartOfAccount)
    {
        $this->authorizeAccount($chartOfAccount);
        if (BudgetAllocation::where('chart_of_account_id', $chartOfAccount->id)->exists()) {
            return back()->withErrors(['account' => 'This account is used by budget items and cannot be deleted.']);
        }

        $chartOfAccount->delete();

        return redirect()->route('chart-of-accounts')->with('success', 'Account deleted.');
    }

    private function validated(Request $request, ?ChartOfAccount $existing = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->where('organization_id', $this->organizationId())->ignore($existing?->id)],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
        ]);
    }
}
