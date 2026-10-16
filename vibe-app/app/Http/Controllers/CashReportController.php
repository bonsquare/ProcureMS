<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashFundReceipt;
use App\Models\School;
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
}
