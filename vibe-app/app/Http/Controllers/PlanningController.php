<?php

namespace App\Http\Controllers;

use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\MasterTransaction;
use App\Models\PpmpItem;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Models\AgencySetting;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Services\FiscalYearService;
use App\Services\MasterTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $schools = School::query()->whereIn('id', $this->schoolIds())->orderBy('name')->get();
        $selectedSchool = $schools->firstWhere('id', (int) $request->query('school_id'))
            ?? $schools->firstWhere('id', (int) $request->user()->school_id)
            ?? $schools->first();

        if (! $selectedSchool) {
            abort(403, 'No school is assigned to this account.');
        }

        $year = (int) $request->query('year', $selectedSchool->organization?->fiscal_year ?? now()->year);

        return view('planning', [
            'schools' => $schools,
            'selectedSchool' => $selectedSchool,
            'year' => $year,
            'sipProjects' => SipProject::with(['transaction', 'activities'])->where('school_id', $selectedSchool->id)->orderByDesc('school_year')->latest('id')->get(),
            'sipPlans' => SipPlan::where('school_id', $selectedSchool->id)->get()->keyBy('start_year'),
            'aips' => Aip::with(['sipProject', 'activities'])->where('school_id', $selectedSchool->id)->orderByDesc('fiscal_year')->get(),
            'ppmpPlans' => PpmpPlan::with(['items', 'aip', 'transaction'])->where('school_id', $selectedSchool->id)->orderByDesc('fiscal_year')->latest('id')->get(),
            'appPlan' => AppPlan::with(['items.ppmpItem.plan.transaction.procurementRequests', 'items.requestItems.procurementRequest'])->where('school_id', $selectedSchool->id)->where('fiscal_year', $year)->first(),
            'fundSources' => FundSource::where('organization_id', $selectedSchool->organization_id)->orderBy('name')->get(),
            'fundOptions' => FundSource::where('organization_id', $selectedSchool->organization_id)->where('is_active', true)->orderBy('name')->pluck('name')->merge(collect(Aip::FUNDS)->flatten())->unique()->sort()->values(),
            'fiscalYears' => FiscalYear::where('organization_id', $selectedSchool->organization_id)->orderByDesc('year')->get(),
        ]);
    }

    public function storeSip(Request $request, MasterTransactionService $transactions, FiscalYearService $fiscalYears)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'school_year' => ['required', 'integer', 'between:2000,2100'],
            'planning_period' => ['nullable', 'string', 'max:100'],
            'pillar' => ['required', 'string', 'max:100'],
            'kra' => ['required', 'string', 'max:255'],
            'organizational_outcome' => ['nullable', 'string', 'max:1000'],
            'strategy' => ['nullable', 'string', 'max:255'],
            'five_point_agenda' => ['nullable', 'string', 'max:255'],
            'project' => ['required', 'string', 'max:255'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();
        $fiscalYears->assertOpen((int) $school->organization_id, (int) $data['school_year']);

        $sip = DB::transaction(function () use ($data, $school, $request, $transactions) {
            $sip = SipProject::create($data + ['created_by' => $request->user()->id]);
            $transaction = $transactions->create($school, (int) $data['school_year'], $data['project'], $request->user(), 'sip', 'created');
            $sip->update(['master_transaction_id' => $transaction->id]);

            return $sip;
        });

        return back()->with('success', "SIP project saved under {$sip->transaction->transaction_number}.");
    }

    public function storeSipActivity(Request $request, SipProject $sipProject, FiscalYearService $fiscalYears)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:1000'],
            'physical_year1' => ['nullable', 'numeric', 'min:0'], 'physical_year2' => ['nullable', 'numeric', 'min:0'], 'physical_year3' => ['nullable', 'numeric', 'min:0'],
            'financial_year1' => ['nullable', 'numeric', 'min:0'], 'financial_year2' => ['nullable', 'numeric', 'min:0'], 'financial_year3' => ['nullable', 'numeric', 'min:0'],
            'source_of_fund' => ['nullable', 'string', 'max:255'],
            'responsible_person' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $fiscalYears->assertOpen((int) $sipProject->organization_id, (int) $sipProject->school_year);

        DB::transaction(function () use ($sipProject, $data) {
            $sipProject->activities()->create($data + ['organization_id' => $sipProject->organization_id]);
            $this->syncSipBudget($sipProject);
        });

        return back()->with('success', 'SIP activity saved.');
    }

    public function destroySipActivity(Request $request, SipActivity $sipActivity)
    {
        $this->authorizeManage($request);
        $sipProject = $sipActivity->project()->firstOrFail();
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        app(FiscalYearService::class)->assertOpen((int) $sipProject->organization_id, (int) $sipProject->school_year);

        DB::transaction(function () use ($sipActivity, $sipProject) {
            $sipActivity->delete();
            $this->syncSipBudget($sipProject);
        });

        return back()->with('success', 'SIP activity removed.');
    }

    public function saveSipSignatories(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'start_year' => ['required', 'integer', 'between:2000,2100'],
            'prepared_by_name' => ['nullable', 'string', 'max:255'], 'prepared_by_position' => ['nullable', 'string', 'max:255'],
            'recommended_by_name' => ['nullable', 'string', 'max:255'], 'recommended_by_position' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'], 'approved_by_position' => ['nullable', 'string', 'max:255'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();
        SipPlan::updateOrCreate(
            ['school_id' => $school->id, 'start_year' => $data['start_year']],
            collect($data)->except(['school_id', 'start_year'])->all() + ['organization_id' => $school->organization_id],
        );

        return back()->with('success', 'SIP signatories saved.');
    }

    public function printSip(Request $request)
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'start_year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();

        return view('sip-print', [
            'school' => $school,
            'startYear' => (int) $data['start_year'],
            'projects' => SipProject::with('activities')->where('school_id', $school->id)->where('school_year', $data['start_year'])->orderBy('id')->get(),
            'plan' => SipPlan::where('school_id', $school->id)->where('start_year', $data['start_year'])->first(),
            'agency' => AgencySetting::first(),
        ]);
    }

    /** The program's estimated budget is the sum of its three-year activity targets. */
    private function syncSipBudget(SipProject $sipProject): void
    {
        $sipProject->update(['estimated_budget' => $sipProject->activities()->get()->sum(fn ($a) => $a->financial_total)]);
    }

    public function linkAip(Request $request, SipProject $sipProject, MasterTransactionService $transactions)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        $data = $request->validate([
            'aip_id' => ['required', 'integer', Rule::exists('aips', 'id')->where('school_id', $sipProject->school_id)],
        ]);
        $aip = Aip::whereKey($data['aip_id'])->firstOrFail();
        $aipTransaction = $transactions->forAip($aip, $request->user());
        DB::transaction(function () use ($sipProject, $aip, $aipTransaction) {
            $aip->update(['sip_project_id' => $sipProject->id]);
            $aipTransaction->recordEvent('sip', 'linked_to_aip', $aip->status, $aip->status, $sipProject->project, ['sip_project_id' => $sipProject->id]);
            $sipProject->transaction?->recordEvent('aip', 'aip_linked', null, $aip->status, $aip->fiscal_year.' · '.$aipTransaction->transaction_number, ['aip_id' => $aip->id, 'aip_transaction_id' => $aipTransaction->id]);
        });

        return back()->with('success', 'SIP linked to the AIP transaction.');
    }

    public function storePpmp(Request $request, MasterTransactionService $transactions, FiscalYearService $fiscalYears)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'aip_id' => ['required', 'integer', Rule::exists('aips', 'id')],
            'project_title' => ['required', 'string', 'max:255'],
            'procurement_mode' => ['nullable', 'string', 'max:100'],
            'procurement_schedule' => ['nullable', 'string', 'max:255'],
            'fund_source' => ['nullable', 'string', 'max:255'],
            'procurement_item' => ['required', 'string', 'max:255'],
            'specifications' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'estimated_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $aip = Aip::with('school')->whereIn('school_id', $this->schoolIds())->findOrFail($data['aip_id']);
        $this->authorizeManage($request);
        $allowedFunds = FundSource::where('organization_id', $aip->organization_id)
            ->where('is_active', true)
            ->pluck('name')
            ->merge($aip->fundOptions())
            ->unique();
        if (! empty($data['fund_source']) && ! $allowedFunds->contains($data['fund_source'])) {
            throw ValidationException::withMessages(['fund_source' => 'Choose a fund source configured for this organization.']);
        }
        $this->requires($aip->status === 'approved', 'Approve the AIP before preparing its PPMP.');
        $fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);
        $transaction = $transactions->forAip($aip, $request->user());
        $total = round((float) $data['quantity'] * (float) $data['estimated_unit_cost'], 2);

        DB::transaction(function () use ($data, $aip, $transaction, $request, $total) {
            $plan = PpmpPlan::create([
                'school_id' => $aip->school_id,
                'aip_id' => $aip->id,
                'master_transaction_id' => $transaction->id,
                'fiscal_year' => $aip->fiscal_year,
                'project_title' => $data['project_title'],
                'procurement_mode' => $data['procurement_mode'] ?? null,
                'procurement_schedule' => $data['procurement_schedule'] ?? null,
                'fund_source' => $data['fund_source'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $plan->items()->create([
                'organization_id' => $aip->organization_id,
                'procurement_item' => $data['procurement_item'],
                'specifications' => $data['specifications'] ?? null,
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'estimated_unit_cost' => $data['estimated_unit_cost'],
                'estimated_total_cost' => $total,
            ]);
            $transaction->recordEvent('ppmp', 'draft_created', null, 'draft', $data['project_title'], ['ppmp_plan_id' => $plan->id, 'estimated_total' => $total]);
        });

        return back()->with('success', 'PPMP draft saved and linked to its AIP transaction.');
    }

    public function approvePpmp(Request $request, PpmpPlan $ppmpPlan)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($ppmpPlan->school_id), 403);
        $this->requires(! ($ppmpPlan->items()->doesntExist()), 'Add at least one PPMP item before approval.');
        app(FiscalYearService::class)->assertOpen((int) $ppmpPlan->organization_id, (int) $ppmpPlan->fiscal_year);
        $oldStatus = $ppmpPlan->status;
        $ppmpPlan->update(['status' => 'approved']);
        $ppmpPlan->transaction?->update(['status' => 'procurement_planning']);
        $ppmpPlan->transaction?->recordEvent('ppmp', 'approved', $oldStatus, 'approved', $ppmpPlan->project_title);

        return back()->with('success', 'PPMP approved and available for APP generation.');
    }

    public function generateApp(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();
        app(FiscalYearService::class)->assertOpen((int) $school->organization_id, (int) $data['fiscal_year']);
        $approvedItems = PpmpItem::with('plan')
            ->whereHas('plan', fn (Builder $query) => $query->where('school_id', $school->id)->where('fiscal_year', $data['fiscal_year'])->where('status', 'approved'))
            ->get();
        $this->requires(! ($approvedItems->isEmpty()), 'Approve at least one PPMP before generating the APP.');

        DB::transaction(function () use ($data, $school, $request, $approvedItems) {
            $appPlan = AppPlan::firstOrCreate(
                ['school_id' => $school->id, 'fiscal_year' => $data['fiscal_year']],
                ['status' => 'draft', 'created_by' => $request->user()->id],
            );
            $hasNewItems = false;
            foreach ($approvedItems as $item) {
                [$appItem, $created] = AppItem::firstOrCreate(
                    ['app_plan_id' => $appPlan->id, 'ppmp_item_id' => $item->id],
                    [
                        'organization_id' => $appPlan->organization_id,
                        'procurement_item' => $item->procurement_item,
                        'specifications' => $item->specifications,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'estimated_unit_cost' => $item->estimated_unit_cost,
                        'estimated_total_cost' => $item->estimated_total_cost,
                        'procurement_mode' => $item->plan->procurement_mode,
                        'procurement_schedule' => $item->plan->procurement_schedule,
                        'fund_source' => $item->plan->fund_source,
                    ],
                );
                $hasNewItems = $hasNewItems || $created;
                if ($created) {
                    $item->plan->transaction?->recordEvent('app', 'included_in_app', null, 'planned', $item->procurement_item, ['app_plan_id' => $appPlan->id]);
                }
            }
            if ($hasNewItems && $appPlan->status === 'approved') {
                $appPlan->update(['status' => 'draft']);
            }
        });

        return redirect()->route('planning', ['school_id' => $school->id, 'year' => $data['fiscal_year']])->with('success', 'APP generated from approved PPMP items.');
    }

    public function approveApp(Request $request, AppPlan $appPlan)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($appPlan->school_id), 403);
        $this->requires(! ($appPlan->items()->doesntExist()), 'Generate APP items before approval.');
        app(FiscalYearService::class)->assertOpen((int) $appPlan->organization_id, (int) $appPlan->fiscal_year);
        $appPlan->load('items.ppmpItem.plan.transaction');
        $appPlan->update(['status' => 'approved']);
        foreach ($appPlan->items as $item) {
            $item->ppmpItem?->plan?->transaction?->recordEvent('app', 'approved', 'draft', 'approved', $appPlan->fiscal_year.' APP', ['app_plan_id' => $appPlan->id]);
        }

        return back()->with('success', 'APP approved.');
    }

    public function storeFundSource(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $organizationId = (int) School::whereKey($data['school_id'])->value('organization_id');
        FundSource::updateOrCreate(
            ['organization_id' => $organizationId, 'code' => strtoupper($data['code'])],
            ['name' => $data['name'], 'is_active' => true],
        );

        return back()->with('success', 'Fund source saved for this organization.');
    }

    public function setFiscalYearStatus(Request $request, FiscalYearService $fiscalYears)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
        $organizationId = (int) School::whereKey($data['school_id'])->value('organization_id');
        $fiscalYears->setStatus($organizationId, (int) $data['year'], $data['status'], $request->user()->id);

        return back()->with('success', "FY {$data['year']} is now {$data['status']}.");
    }

    public function transaction(Request $request, MasterTransaction $masterTransaction)
    {
        abort_unless($this->schoolIds()->contains($masterTransaction->school_id), 403);
        $masterTransaction->load([
            'school',
            'events.user',
            'aip.sipProject',
            'budgetAllocations.account',
            'procurementRequests.items',
            'liquidationReports',
            'sipProjects',
        ]);

        return view('transaction-show', ['transaction' => $masterTransaction]);
    }

    private function schoolIds()
    {
        $user = request()->user();

        return $user->role === 'master_user' || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    /** Business-rule failures go back to the page with a message instead of an error screen. */
    private function requires(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['planning' => $message]);
        }
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('planning.manage'), 403, 'You do not have permission to manage planning records.');
    }
}
