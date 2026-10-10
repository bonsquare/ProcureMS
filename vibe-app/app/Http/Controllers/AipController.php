<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\Aip;
use App\Models\AipActivity;
use App\Models\AipKra;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\ChartOfAccount;
use App\Models\FundSource;
use App\Models\PpmpPlan;
use App\Models\School;
use App\Services\FiscalYearService;
use App\Services\MasterTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AipController extends Controller
{
    public function __construct(private FiscalYearService $fiscalYears, private MasterTransactionService $transactions) {}

    private function schoolIds()
    {
        $user = request()->user();

        return $user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id');
    }

    private function authorizeAip(Aip $aip): void
    {
        abort_unless($this->schoolIds()->contains($aip->school_id), 403);
    }

    private function authorizeManage(): void
    {
        abort_unless(request()->user()->canManageBudget(), 403, 'Only the Budget Officer or an administrator can change the AIP.');
    }

    /** The AIP list lives on the Planning page; keep the old URL working. */
    public function index()
    {
        return redirect()->to(route('planning').'#aip');
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds()->all())],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100', Rule::unique('aips')->where('school_id', $request->input('school_id'))],
        ], ['fiscal_year.unique' => 'This school already has an AIP for that fiscal year.']);

        $school = School::findOrFail($data['school_id']);
        $this->fiscalYears->assertOpen((int) $school->organization_id, (int) $data['fiscal_year']);
        $aip = DB::transaction(function () use ($data, $request, $school) {
            $aip = Aip::create($data + [
                'created_by' => $request->user()->id,
                'prepared_by_name' => $school->school_head,
                'prepared_by_position' => 'School Head',
                'noted_by_position' => 'Chief, SGOD',
                'approved_by_position' => 'Schools Division Superintendent',
            ]);
            $this->transactions->forAip($aip, $request->user());

            return $aip;
        });

        return redirect()->route('aip.show', $aip)->with('success', 'AIP created. Add the activities below.');
    }

    public function show(Aip $aip)
    {
        $this->authorizeAip($aip);
        $aip->load(['school', 'kras.activities.account', 'transaction', 'sipProject']);

        return view('aip-show', [
            'aip' => $aip,
            'accounts' => $this->accounts(),
            'fundOptions' => $this->fundOptions($aip),
            'pillars' => collect(Aip::PILLARS)->merge(AipKra::whereIn('aip_id', Aip::pluck('id'))->distinct()->pluck('pillar'))->filter()->unique()->values(),
            'canManage' => request()->user()->canManageBudget(),
            'allotments' => $aip->allotments()->get(),
        ]);
    }

    public function print(Aip $aip)
    {
        $this->authorizeAip($aip);
        $aip->load(['school', 'kras.activities']);

        return view('aip-print', ['aip' => $aip, 'agency' => AgencySetting::first()]);
    }

    public function update(Request $request, Aip $aip)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        $data = $request->validate([
            'fiscal_year' => ['sometimes', 'required', 'integer', 'between:2000,2100', Rule::unique('aips')->where('school_id', $aip->school_id)->ignore($aip->id)],
            'entity' => ['sometimes', 'required', Rule::in(array_keys(Aip::FUNDS))],
            'prepared_by_name' => ['nullable', 'string', 'max:255'], 'prepared_by_position' => ['nullable', 'string', 'max:255'],
            'noted_by_name' => ['nullable', 'string', 'max:255'], 'noted_by_position' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'], 'approved_by_position' => ['nullable', 'string', 'max:255'],
        ]);
        $yearChanged = isset($data['fiscal_year']) && (int) $data['fiscal_year'] !== (int) $aip->fiscal_year;
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) ($data['fiscal_year'] ?? $aip->fiscal_year));
        $aip->update($data);
        if ($yearChanged) {
            // Allotments created from this AIP follow its fiscal year.
            $aip->allotments()->update(['fiscal_year' => $data['fiscal_year'], 'start_date' => $data['fiscal_year'].'-01-01', 'end_date' => $data['fiscal_year'].'-12-31']);
            $aip->transaction?->update(['fiscal_year' => $data['fiscal_year'], 'title' => 'AIP FY '.$data['fiscal_year']]);
        }

        if ($yearChanged) {
            return back()->with('success', 'Fiscal year changed to FY '.$data['fiscal_year'].'.');
        }

        return back()->with('success', isset($data['entity']) ? 'Entity updated. Source of fund choices now follow it.' : 'Signatories saved.');
    }

    public function createKra(Aip $aip)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);

        return view('aip-kra-form', $this->kraFormData($aip, new AipKra(['aip_id' => $aip->id])));
    }

    public function editKra(Aip $aip, AipKra $kra)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        abort_unless($kra->aip_id === $aip->id, 404);

        return view('aip-kra-form', $this->kraFormData($aip, $kra));
    }

    private function kraFormData(Aip $aip, AipKra $kra): array
    {
        $kra->load('activities');

        return [
            'aip' => $aip->load('school'),
            'kra' => $kra,
            'activities' => old('activities', $kra->activities->map(fn (AipActivity $a) => $a->only([
                'id', 'activity', 'physical_target', 'timeline', 'q1_amount', 'q2_amount', 'q3_amount', 'q4_amount', 'source_of_fund', 'chart_of_account_id', 'responsible_persons', 'remarks_list',
            ]))->all()),
            'accounts' => $this->accounts(),
            'fundOptions' => $this->fundOptions($aip),
            'pillars' => collect(Aip::PILLARS)->merge(AipKra::whereIn('aip_id', Aip::pluck('id'))->distinct()->pluck('pillar'))->filter()->unique()->values(),
        ];
    }

    /** One save creates the KRA together with all of its activities. */
    public function storeKra(Request $request, Aip $aip)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);
        [$kraData, $activities] = $this->validatedKraForm($request, $aip);

        DB::transaction(function () use ($aip, $kraData, $activities) {
            $kra = $aip->kras()->create($kraData);
            foreach ($activities as $activity) {
                $kra->activities()->create($activity + ['aip_id' => $aip->id]);
            }
        });
        $this->markRevised($aip);

        return redirect()->route('aip.show', $aip)->with('success', 'KRA and its activities saved.');
    }

    /** Activities on the form are matched by id: updated, added, or removed when left off. */
    public function updateKra(Request $request, Aip $aip, AipKra $kra)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        abort_unless($kra->aip_id === $aip->id, 404);
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);
        [$kraData, $activities] = $this->validatedKraForm($request, $aip);

        DB::transaction(function () use ($aip, $kra, $kraData, $activities) {
            $kra->update($kraData);
            $keep = [];
            foreach ($activities as $activity) {
                $id = $activity['id'] ?? null;
                unset($activity['id']);
                $existing = $id ? $kra->activities()->whereKey($id)->first() : null;
                if ($existing) {
                    $existing->update($activity);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $kra->activities()->create($activity + ['aip_id' => $aip->id])->id;
                }
            }
            $kra->activities()->whereNotIn('id', $keep)->delete();
        });
        $this->markRevised($aip);

        return redirect()->route('aip.show', $aip)->with('success', 'KRA and its activities updated.');
    }

    /** Deletes a draft AIP that nothing was built on. An approved AIP stays. */
    public function destroy(Aip $aip)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);

        if ($aip->status !== 'draft') {
            throw ValidationException::withMessages(['aip' => 'Only a draft AIP can be deleted. This one was already approved.']);
        }
        if (PpmpPlan::withoutGlobalScopes()->where('aip_id', $aip->id)->exists() || BudgetAllocation::withoutGlobalScopes()->where('aip_id', $aip->id)->exists()) {
            throw ValidationException::withMessages(['aip' => 'This AIP has a PPMP or budget built on it. Remove those first.']);
        }

        $transaction = $aip->transaction;
        DB::transaction(function () use ($aip, $transaction) {
            AuditLog::create(['user_id' => request()->user()->id, 'school_id' => $aip->school_id, 'action' => 'aip_deleted', 'auditable_type' => Aip::class, 'auditable_id' => $aip->id, 'metadata' => ['fiscal_year' => $aip->fiscal_year, 'activities' => $aip->activities()->count()]]);
            $aip->delete();

            // The tracking record of an AIP that never went anywhere goes with it.
            app(MasterTransactionService::class)->discardIfUnused($transaction);
        });

        return redirect()->to(route('planning', ['school_id' => $aip->school_id, 'year' => $aip->fiscal_year]).'#aip')->with('success', 'AIP FY '.$aip->fiscal_year.' deleted.');
    }

    public function destroyKra(Aip $aip, AipKra $kra)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        abort_unless($kra->aip_id === $aip->id, 404);
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);
        $kra->delete();
        $this->markRevised($aip);

        return back()->with('success', 'KRA and its activities removed.');
    }

    /** Once approved, any change needs a new approval before allotments follow it. */
    private function markRevised(Aip $aip): void
    {
        if ($aip->status === 'approved') {
            $aip->transaction?->recordEvent('aip', 'revised', 'approved', 'revised');
            $aip->update(['status' => 'revised']);
            $aip->transaction?->update(['status' => 'planning']);
        }
    }

    private function accounts()
    {
        $user = request()->user();
        ChartOfAccount::ensureDefaults($user->organization_id);

        return ChartOfAccount::query()
            ->when($user->seesAllSchools() && ! $user->organization_id, fn ($q) => $q->whereNull('organization_id'))
            ->where('category', '!=', 'Revenue')->orderBy('code')->get();
    }

    private function fundOptions(Aip $aip): array
    {
        return FundSource::where('organization_id', $aip->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->merge($aip->fundOptions())
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function validatedKraForm(Request $request, Aip $aip): array
    {
        $data = $request->validate([
            'pillar' => ['nullable', 'string', 'max:100'],
            'kra' => ['required', 'string', 'max:255'],
            'intermediate_outcome' => ['nullable', 'string', 'max:1000'],
            'strategy' => ['nullable', 'string', 'max:255'],
            'five_point_agenda' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.id' => ['nullable', 'integer'],
            'activities.*.activity' => ['required', 'string', 'max:1000'],
            'activities.*.physical_target' => ['nullable', 'integer', 'min:0'],
            'activities.*.timeline' => ['nullable', 'string', 'max:100'],
            'activities.*.q1_amount' => ['nullable', 'numeric', 'min:0'],
            'activities.*.q2_amount' => ['nullable', 'numeric', 'min:0'],
            'activities.*.q3_amount' => ['nullable', 'numeric', 'min:0'],
            'activities.*.q4_amount' => ['nullable', 'numeric', 'min:0'],
            'activities.*.source_of_fund' => ['nullable', 'string', 'max:255', Rule::in($this->fundOptions($aip))],
            'activities.*.chart_of_account_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('organization_id', $aip->organization_id),
            ],
            'activities.*.responsible_persons' => ['nullable', 'array'],
            'activities.*.responsible_persons.*' => ['nullable', 'string', 'max:255'],
            'activities.*.remarks_list' => ['nullable', 'array'],
            'activities.*.remarks_list.*' => ['nullable', 'string', 'max:500'],
        ], ['activities.required' => 'Add at least one activity under the KRA.', 'activities.min' => 'Add at least one activity under the KRA.', 'activities.*.activity.required' => 'Every activity needs a description.']);

        $activities = collect($data['activities'])->map(function (array $activity) {
            foreach (['q1_amount', 'q2_amount', 'q3_amount', 'q4_amount'] as $quarter) {
                $activity[$quarter] = (float) ($activity[$quarter] ?? 0);
            }
            $activity['physical_target'] = (int) ($activity['physical_target'] ?? 1);
            foreach (['responsible_persons', 'remarks_list'] as $list) {
                $activity[$list] = array_values(array_filter(array_map('trim', $activity[$list] ?? []), fn ($value) => $value !== ''));
            }

            return $activity;
        })->values()->all();

        return [collect($data)->except('activities')->all(), $activities];
    }

    /**
     * Approve the AIP. An approved AIP is used for reports and to start a PPMP; it is not connected to the Budget,
     * so approving creates no allotment and asks for no source of fund or account code.
     */
    public function approve(Request $request, Aip $aip)
    {
        $this->authorizeManage();
        $this->authorizeAip($aip);
        $this->fiscalYears->assertOpen((int) $aip->organization_id, (int) $aip->fiscal_year);

        if ($aip->status === 'approved') {
            return back()->with('success', 'This AIP is already approved.');
        }
        if (! $aip->activities()->exists()) {
            throw ValidationException::withMessages(['aip' => 'Add at least one activity before approving.']);
        }

        $transaction = $this->transactions->forAip($aip, $request->user());
        $previousStatus = $aip->status;
        $aip->update(['status' => 'approved', 'approved_at' => now()]);
        $transaction->recordEvent('aip', 'approved', $previousStatus, 'approved');
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $aip->school_id, 'action' => 'aip_approved', 'auditable_type' => Aip::class, 'auditable_id' => $aip->id]);

        return back()->with('success', 'AIP approved.');
    }
}
