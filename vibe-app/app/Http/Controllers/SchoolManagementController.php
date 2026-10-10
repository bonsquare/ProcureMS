<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolTakeoverRequest;
use App\Models\StationTransferRequest;
use App\Models\User;
use App\Services\SchoolManagementService;
use App\Services\StationTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Master-user page to see every school and take it or its user out of service. */
class SchoolManagementController extends Controller
{
    public function __construct(private SchoolManagementService $service) {}

    public function index(Request $request)
    {
        $this->master($request);
        $search = trim((string) $request->query('q'));
        $filter = in_array($request->query('status'), ['active', 'vacant', 'inactive'], true) ? $request->query('status') : null;
        $pending = StationTransferRequest::where('status', 'pending')->where('kind', 'transfer')->selectRaw('from_school_id, count(*) as total')->groupBy('from_school_id')->pluck('total', 'from_school_id');

        $takeovers = SchoolTakeoverRequest::where('status', 'pending')->selectRaw('school_id, count(*) as total')->groupBy('school_id')->pluck('total', 'school_id');

        $schools = School::with('users')->orderBy('name')
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->get()
            ->map(function (School $school) use ($pending, $takeovers) {
                $school->manager = $school->users->first(fn (User $user) => ! $user->seesAllSchools() && ($user->status ?: 'active') === 'active');
                $school->state = $school->status !== 'active' ? 'inactive' : ($school->manager ? 'active' : 'vacant');
                $school->pending_transfers = (int) ($pending[$school->id] ?? 0);
                $school->pending_takeovers = (int) ($takeovers[$school->id] ?? 0);

                return $school;
            });

        return view('school-management', [
            'schools' => $filter ? $schools->where('state', $filter)->values() : $schools,
            'counts' => $schools->countBy('state'),
            'total' => $schools->count(),
            'search' => $search,
            'filter' => $filter,
            'activeNavRoute' => 'school-management',
        ]);
    }

    public function show(Request $request, School $school)
    {
        $this->master($request);
        app(StationTransferService::class)->expireDue();
        app(StationTransferService::class)->endDueHandovers();

        return view('school-management-show', [
            'school' => $school->load('organization'),
            'users' => User::where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)->orderBy('name')->get(),
            'takeoverRequests' => SchoolTakeoverRequest::with('user')->where('status', 'pending')->where('school_id', $school->id)->oldest('id')->get(),
            'handovers' => StationTransferRequest::with(['user', 'handoverUser'])->where('status', 'approved')->whereNotNull('handover_user_id')->whereNull('handover_ended_at')->where('to_school_id', $school->id)->get(),
            'reasons' => SchoolManagementService::REASONS,
            'transferRequests' => StationTransferRequest::with(['user', 'fromSchool', 'toSchool'])->where('status', 'pending')->where('kind', 'transfer')
                ->where(fn ($query) => $query->where('from_school_id', $school->id)->orWhere('to_school_id', $school->id))->oldest('id')->get(),
            'activeNavRoute' => 'school-management',
        ]);
    }

    public function status(Request $request, School $school): RedirectResponse
    {
        $this->master($request);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $this->service->setSchoolActive($school, (bool) $data['active'], $request->user());

        return $this->back($school->id, $school->name.' is now '.($data['active'] ? 'active' : 'inactive').'.');
    }

    public function endHandover(Request $request, StationTransferRequest $transfer): RedirectResponse
    {
        $this->master($request);
        app(StationTransferService::class)->endHandoverNow($transfer, $request->user());

        return $this->back($transfer->to_school_id, 'Handover ended. The previous user is now inactive.');
    }

    public function deactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->master($request);
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', SchoolManagementService::REASONS)],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->deactivateUser($user, $request->user(), $data['reason'], $data['effective_date'], $data['note'] ?? null);

        return $this->back($user->school_id, $user->name.' is now inactive. The school is vacant.');
    }

    public function reactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->master($request);
        $this->service->reactivateUser($user, $request->user());

        return $this->back($user->school_id, $user->name.' is active again.');
    }

    private function master(Request $request): void
    {
        abort_unless($request->user()?->hasAccess('schools'), 403);
    }

    private function back(?int $schoolId, string $message): RedirectResponse
    {
        return redirect()->route('school-management.show', $schoolId)->with('success', $message);
    }
}
