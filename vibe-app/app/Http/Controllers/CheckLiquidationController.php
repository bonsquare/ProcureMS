<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CheckLiquidation;
use App\Models\LiquidationReport;
use App\Models\School;
use App\Services\CheckLiquidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The Liquidation tab's check liquidation: pick a check, confirm or split its actual expense accounts, save a draft, approve, reopen. */
class CheckLiquidationController extends Controller
{
    public function __construct(private CheckLiquidationService $service) {}

    /** The paid checks that can still be liquidated, for the search box. */
    public function checks(Request $request): JsonResponse
    {
        return response()->json($this->service->eligibleChecks($this->schoolIds($request))->map(fn (LiquidationReport $report) => [
            'id' => $report->id, 'check_no' => $report->payment_reference, 'payee' => $report->payee, 'amount' => (float) $report->amount,
            'status' => $report->checkLiquidation?->status ?? 'none',
        ])->values());
    }

    /** Everything the form shows for one check, with the suggested lines or the saved ones. */
    public function show(Request $request, LiquidationReport $liquidationReport): JsonResponse
    {
        $this->authorizeCheck($request, $liquidationReport, 'liquidation.view');
        $liquidation = $liquidationReport->checkLiquidation()->with('lines')->first();
        $suggested = $this->service->suggestedLines($liquidationReport);
        $charged = $liquidationReport->chargedLine();

        return response()->json([
            'id' => $liquidationReport->id, 'check_no' => $liquidationReport->payment_reference, 'check_date' => ($liquidationReport->check_date ?? $liquidationReport->paid_at)?->toDateString(),
            'dv_no' => $liquidationReport->dv_number, 'ors_no' => $liquidationReport->ors_number, 'payee' => $liquidationReport->payee,
            'amount' => (float) $liquidationReport->amount, 'fund' => $liquidationReport->source_of_fund, 'responsibility_center' => $liquidationReport->responsibility_center_code,
            'description' => $liquidationReport->purpose ?: $liquidationReport->procurementRequest?->title,
            'ors_account' => $charged ? ['code' => $charged->account?->code ?? $charged->uacs_code, 'title' => $charged->account?->title] : null,
            'status' => $liquidation?->status ?? 'new', 'version' => $liquidation?->version, 'refund' => (float) ($liquidation?->refund_amount ?? 0), 'refund_reference' => $liquidation?->refund_reference,
            'source' => $liquidation ? 'saved' : $suggested['source'],
            'lines' => $liquidation ? $liquidation->lines->map->only(['account_code', 'account_title', 'amount', 'source'])->all() : $suggested['lines'],
        ]);
    }

    /** The accounts a line can use: the expense and property accounts of the organization's chart. */
    public function accounts(Request $request): JsonResponse
    {
        return response()->json(ChartOfAccount::query()->orderBy('code')->get(['code', 'title'])
            ->reject(fn ($account) => in_array(substr($account->code, 0, 1), ['2', '3', '4'], true) || str_starts_with($account->code, '101') || $account->code === '1990101000')->values());
    }

    public function save(Request $request, LiquidationReport $liquidationReport): JsonResponse
    {
        $this->authorizeCheck($request, $liquidationReport, 'liquidation.create');
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'], 'lines.*.account_code' => ['required', 'string', 'max:50'], 'lines.*.amount' => ['required', 'numeric'],
            'refund' => ['nullable', 'numeric', 'min:0'], 'refund_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $liquidation = $this->service->saveDraft($liquidationReport, $request->user(), $data['lines'], (float) ($data['refund'] ?? 0), $data['refund_reference'] ?? null);

        return response()->json(['status' => $liquidation->status, 'message' => 'Draft saved.']);
    }

    public function approve(Request $request, LiquidationReport $liquidationReport): JsonResponse
    {
        $this->authorizeCheck($request, $liquidationReport, 'liquidation.approve');
        $liquidation = CheckLiquidation::query()->where('liquidation_report_id', $liquidationReport->id)->firstOrFail();
        $this->service->approve($liquidation, $request->user());

        return response()->json(['status' => 'approved', 'message' => 'Liquidation approved.']);
    }

    public function reopen(Request $request, LiquidationReport $liquidationReport): JsonResponse
    {
        $this->authorizeCheck($request, $liquidationReport, 'liquidation.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'password' => ['required', 'current_password']]);
        $liquidation = CheckLiquidation::query()->where('liquidation_report_id', $liquidationReport->id)->firstOrFail();
        $this->service->reopen($liquidation, $request->user(), $data['reason']);

        return response()->json(['status' => 'draft', 'message' => 'Liquidation reopened.']);
    }

    /** The user's permission and the check's school must both be the user's own. */
    private function authorizeCheck(Request $request, LiquidationReport $report, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission) && $this->schoolIds($request)->contains($report->school_id), 403);
        abort_unless($report->paid_at && $report->payment_reference, 422, 'Only a paid check can be liquidated.');
    }

    private function schoolIds(Request $request)
    {
        $user = $request->user();

        return $user->seesAllSchools() || $user->organization_id ? School::query()->pluck('id') : School::query()->whereKey($user->school_id)->pluck('id');
    }
}
