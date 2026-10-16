<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PowPlan;
use App\Models\School;
use App\Models\SobPlan;
use App\Services\PowService;
use App\Support\GeneratedForms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The Form Generator under the SOB: makes the official forms (the Program of Works for now) from the SOB items of a program and activity. */
class FormGeneratorController extends Controller
{
    public function __construct(private PowService $pow) {}

    /** @return array<int, int> the schools this user may work with (the same rule as the SOB) */
    private function schoolIds(Request $request): array
    {
        $user = $request->user();

        return ($user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id'))->all();
    }

    private function canOpen(Request $request): void
    {
        abort_unless($request->user()->hasPermission('planning.view') || $request->user()->hasPermission('planning.manage'), 403);
    }

    private function scoped(Request $request, int $schoolId): void
    {
        abort_unless(in_array($schoolId, $this->schoolIds($request), true), 403);
    }

    public function index(Request $request): View
    {
        $this->canOpen($request);
        $plan = $request->filled('sob_plan_id') ? SobPlan::query()->findOrFail((int) $request->query('sob_plan_id')) : null;
        if ($plan) {
            $this->scoped($request, $plan->school_id);
        }
        $schools = School::query()->whereIn('id', $this->schoolIds($request))->orderBy('name')->get();
        abort_if($schools->isEmpty(), 404);
        $school = $plan ? $schools->firstWhere('id', $plan->school_id) : ($schools->firstWhere('id', (int) $request->query('school_id')) ?? $schools->first());
        $year = $plan ? (int) $plan->fiscal_year : (int) ($request->query('year') ?: (SobPlan::query()->where('school_id', $school->id)->max('fiscal_year') ?: now()->year));
        $sobPlans = SobPlan::query()->where('school_id', $school->id)->where('fiscal_year', $year)->orderBy('quarter')->get();

        $program = (string) $request->query('program', '');
        $activityId = $request->filled('activity_id') && $request->query('activity_id') !== 'all' ? (int) $request->query('activity_id') : null;
        $programs = $plan ? $this->pow->programs($plan) : collect();
        $activities = $plan && $program !== '' ? $this->pow->activities($plan, $program) : collect();
        $materials = $plan && $program !== '' ? $this->pow->materials($plan, $program, $activityId) : collect();
        $savedPows = PowPlan::query()->where('school_id', $school->id)->latest('id')->limit(50)->get()->each(fn (PowPlan $pow) => $pow->setAttribute('total', $this->pow->totals($pow)['total']));

        return view('form-generator', [
            'forms' => GeneratedForms::all(), 'schools' => $schools, 'school' => $school, 'year' => $year, 'sobPlans' => $sobPlans, 'plan' => $plan,
            'program' => $program, 'programs' => $programs, 'activities' => $activities, 'activityId' => $activityId, 'materials' => $materials,
            'labor' => $plan && $program !== '' ? $this->pow->labor($plan, $program, $activityId) : 0.0, 'savedPows' => $savedPows,
            'signatories' => $this->pow->defaultSignatories($school), 'canManage' => $request->user()->hasPermission('planning.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = SobPlan::query()->findOrFail((int) $request->input('sob_plan_id'));
        $this->scoped($request, $plan->school_id);
        $data = $request->only(['program', 'activity_id', 'project_title', 'school_address', 'pow_date', 'date_started', 'date_completed', 'manpower', 'equipment', 'labor_cost', 'signatories']);
        $data['lines'] = collect((array) $request->input('lines', []))->filter(fn ($line) => is_array($line) && ! empty($line['include']))->values()->all();

        $pow = $this->pow->create($plan, $data, $request->user());
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $pow->school_id, 'action' => 'pow_created', 'auditable_type' => PowPlan::class, 'auditable_id' => $pow->id, 'metadata' => ['program' => $pow->program]]);

        return redirect()->route('planning.pow.show', $pow)->with('success', 'The Program of Works was saved.');
    }
}
