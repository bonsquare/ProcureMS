<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\Aip;
use App\Models\AppItem;
use App\Models\AppPlan;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\MasterTransaction;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Models\SipActivity;
use App\Models\SipPlan;
use App\Models\SipProject;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Services\FiscalYearService;
use App\Services\MasterTransactionService;
use App\Services\SipAipService;
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
            return view('no-school', ['module' => 'Planning']);
        }

        $year = (int) $request->query('year', $selectedSchool->organization?->fiscal_year ?? now()->year);

        return view('planning', [
            'schools' => $schools,
            'selectedSchool' => $selectedSchool,
            'year' => $year,
            'sipProjects' => SipProject::with(['transaction', 'activities'])->where('school_id', $selectedSchool->id)->orderByDesc('school_year')->latest('id')->get(),
            'sipPlans' => SipPlan::where('school_id', $selectedSchool->id)->get()->keyBy('start_year'),
            'aips' => Aip::with(['sipProject', 'activities'])->where('school_id', $selectedSchool->id)->orderByDesc('fiscal_year')->get(),
            'sobPlans' => SobPlan::with('items')->where('school_id', $selectedSchool->id)->orderBy('fiscal_year')->orderBy('quarter')->get(),
            // Older PPMPs stay in the database; they only keep their AIP from being deleted.
            'legacyPpmpAipIds' => PpmpPlan::where('school_id', $selectedSchool->id)->pluck('aip_id')->all(),
            'appPlan' => AppPlan::with(['items.ppmpItem.plan.transaction.procurementRequests', 'items.sobItem.plan.transaction.procurementRequests', 'items.requestItems.procurementRequest'])->where('school_id', $selectedSchool->id)->where('fiscal_year', $year)->first(),
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

    /** Breaks one year of the SIP into the draft AIP of that fiscal year. */
    public function generateAipFromSip(Request $request, SipAipService $service)
    {
        $this->authorizeManage($request);
        abort_unless($request->user()->canManageBudget(), 403, 'Only the Budget Officer or an administrator can create the AIP.');
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'start_year' => ['required', 'integer', 'between:2000,2100'],
            'year_no' => ['required', 'integer', 'between:1,3'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();
        $aip = $service->generate($school, (int) $data['start_year'], (int) $data['year_no'], $request->user());

        return redirect()->to(route('planning', ['school_id' => $school->id, 'year' => $aip->fiscal_year]).'#aip')
            ->with('success', "AIP FY {$aip->fiscal_year} created from year {$data['year_no']} of the SIP. Choose the source of fund and the account of each activity, then approve it.");
    }

    public function updateSip(Request $request, SipProject $sipProject, FiscalYearService $fiscalYears)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        $data = $request->validate([
            'school_year' => ['required', 'integer', 'between:2000,2100'],
            'planning_period' => ['nullable', 'string', 'max:100'],
            'pillar' => ['required', Rule::in(Aip::PILLARS)],
            'kra' => ['required', 'string', 'max:255'],
            'organizational_outcome' => ['nullable', 'string', 'max:1000'],
            'strategy' => ['nullable', 'string', 'max:255'],
            'five_point_agenda' => ['nullable', 'string', 'max:255'],
            'project' => ['required', 'string', 'max:255'],
        ]);
        $fiscalYears->assertOpen((int) $sipProject->organization_id, (int) $sipProject->school_year);
        $fiscalYears->assertOpen((int) $sipProject->organization_id, (int) $data['school_year']);
        $sipProject->update($data);

        return back()->with('success', 'SIP program updated.');
    }

    /** Deletes a program with its activities; refused while an AIP is linked to it. */
    public function destroySip(Request $request, SipProject $sipProject, MasterTransactionService $transactions)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        app(FiscalYearService::class)->assertOpen((int) $sipProject->organization_id, (int) $sipProject->school_year);
        $this->requires(! Aip::withoutGlobalScopes()->where('sip_project_id', $sipProject->id)->exists(), 'An AIP is linked to this program. Delete that AIP, or link it to another program, first.');

        $transaction = $sipProject->transaction;
        DB::transaction(function () use ($sipProject, $transaction, $request, $transactions) {
            AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $sipProject->school_id, 'action' => 'sip_program_deleted', 'auditable_type' => SipProject::class, 'auditable_id' => $sipProject->id, 'metadata' => ['project' => $sipProject->project, 'activities' => $sipProject->activities()->count()]]);
            $sipProject->activities()->delete();
            $sipProject->delete();
            $transactions->discardIfUnused($transaction);
        });

        return back()->with('success', 'SIP program deleted.');
    }

    public function updateSipActivity(Request $request, SipActivity $sipActivity)
    {
        $this->authorizeManage($request);
        $sipProject = $sipActivity->project()->firstOrFail();
        abort_unless($this->schoolIds()->contains($sipProject->school_id), 403);
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:1000'],
            'physical_year1' => ['nullable', 'numeric', 'min:0'], 'physical_year2' => ['nullable', 'numeric', 'min:0'], 'physical_year3' => ['nullable', 'numeric', 'min:0'],
            'financial_year1' => ['nullable', 'numeric', 'min:0'], 'financial_year2' => ['nullable', 'numeric', 'min:0'], 'financial_year3' => ['nullable', 'numeric', 'min:0'],
            'source_of_fund' => ['nullable', 'string', 'max:255'],
            'responsible_person' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        app(FiscalYearService::class)->assertOpen((int) $sipProject->organization_id, (int) $sipProject->school_year);
        foreach (['financial_year1', 'financial_year2', 'financial_year3'] as $column) {
            $data[$column] = $data[$column] ?? 0;
        }

        DB::transaction(function () use ($sipActivity, $sipProject, $data) {
            $sipActivity->update($data);
            $this->syncSipBudget($sipProject);
        });

        return back()->with('success', 'SIP activity updated.');
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

    public function generateApp(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        $school = School::whereKey($data['school_id'])->firstOrFail();
        app(FiscalYearService::class)->assertOpen((int) $school->organization_id, (int) $data['fiscal_year']);
        $approvedItems = SobItem::with(['plan', 'activity'])
            ->whereHas('plan', fn (Builder $query) => $query->where('school_id', $school->id)->where('fiscal_year', $data['fiscal_year'])->where('status', 'approved'))
            ->orderBy('id')
            ->get();
        $this->requires(! ($approvedItems->isEmpty()), 'Approve at least one SOB before generating the APP.');

        DB::transaction(function () use ($data, $school, $request, $approvedItems) {
            $appPlan = AppPlan::firstOrCreate(
                ['school_id' => $school->id, 'fiscal_year' => $data['fiscal_year']],
                ['status' => 'draft', 'created_by' => $request->user()->id],
            );
            $hasNewItems = false;
            foreach ($approvedItems as $item) {
                $appItem = AppItem::firstOrCreate(
                    ['app_plan_id' => $appPlan->id, 'sob_item_id' => $item->id],
                    [
                        'organization_id' => $appPlan->organization_id,
                        'procurement_item' => $item->particulars,
                        'specifications' => $item->activity?->activity,
                        'quantity' => round((float) $item->frequency * (float) $item->quantity, 2),
                        'unit' => $item->unit,
                        'estimated_unit_cost' => $item->unit_cost,
                        'estimated_total_cost' => $item->amount,
                        'fund_source' => $item->plan->fund_source,
                    ],
                );
                $created = $appItem->wasRecentlyCreated;
                $hasNewItems = $hasNewItems || $created;
                if ($created) {
                    $item->plan->transaction?->recordEvent('app', 'included_in_app', null, 'planned', $item->procurement_item, ['app_plan_id' => $appPlan->id]);
                }
            }
            if ($hasNewItems && $appPlan->status === 'approved') {
                $appPlan->update(['status' => 'draft']);
            }
        });

        return redirect()->route('planning', ['school_id' => $school->id, 'year' => $data['fiscal_year']])->with('success', 'APP generated from the approved SOBs.');
    }

    public function updateAppItem(Request $request, AppItem $appItem)
    {
        $plan = $this->editableAppItem($request, $appItem);
        $data = $request->validate([
            'procurement_item' => ['required', 'string', 'max:255'],
            'specifications' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'estimated_unit_cost' => ['required', 'numeric', 'min:0'],
            'procurement_mode' => ['nullable', 'string', 'max:100'],
            'procurement_schedule' => ['nullable', 'string', 'max:255'],
            'fund_source' => ['nullable', 'string', 'max:255'],
        ]);
        $data['estimated_total_cost'] = round((float) $data['quantity'] * (float) $data['estimated_unit_cost'], 2);
        $appItem->update($data);

        return back()->with('success', 'APP item updated for FY '.$plan->fiscal_year.'.');
    }

    public function destroyAppItem(Request $request, AppItem $appItem)
    {
        $plan = $this->editableAppItem($request, $appItem);
        $appItem->delete();

        return back()->with('success', 'APP item removed from FY '.$plan->fiscal_year.'. Generate the APP again to bring it back.');
    }

    /** A draft APP item that no Purchase Request draws from can be changed or removed. */
    private function editableAppItem(Request $request, AppItem $appItem): AppPlan
    {
        $this->authorizeManage($request);
        $plan = $appItem->plan()->firstOrFail();
        abort_unless($this->schoolIds()->contains($plan->school_id), 403);
        $this->requires($plan->status !== 'approved', 'Approved plans are locked. An approved APP is the source of Purchase Requests.');
        app(FiscalYearService::class)->assertOpen((int) $plan->organization_id, (int) $plan->fiscal_year);
        $this->requires($appItem->requestItems()->doesntExist(), 'A Purchase Request already draws from this item.');

        return $plan;
    }

    public function approveApp(Request $request, AppPlan $appPlan)
    {
        $this->authorizeManage($request);
        abort_unless($this->schoolIds()->contains($appPlan->school_id), 403);
        $this->requires(! ($appPlan->items()->doesntExist()), 'Generate APP items before approval.');
        app(FiscalYearService::class)->assertOpen((int) $appPlan->organization_id, (int) $appPlan->fiscal_year);
        $appPlan->load('items.ppmpItem.plan.transaction', 'items.sobItem.plan.transaction');
        $appPlan->update(['status' => 'approved']);
        foreach ($appPlan->items as $item) {
            ($item->sobItem?->plan?->transaction ?? $item->ppmpItem?->plan?->transaction)?->recordEvent('app', 'approved', 'draft', 'approved', $appPlan->fiscal_year.' APP', ['app_plan_id' => $appPlan->id]);
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

        return $user->seesAllSchools() || $user->organization_id
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
