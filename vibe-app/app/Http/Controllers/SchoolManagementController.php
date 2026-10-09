<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\User;
use App\Services\SchoolManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Master-user page to see every school and take its user or employees out of service. */
class SchoolManagementController extends Controller
{
    public function __construct(private SchoolManagementService $service) {}

    public function index(Request $request)
    {
        $this->master($request);
        $search = trim((string) $request->query('q'));
        $filter = in_array($request->query('status'), ['active', 'vacant', 'inactive'], true) ? $request->query('status') : null;
        $pending = StationTransferRequest::where('status', 'pending')->selectRaw('from_school_id, count(*) as total')->groupBy('from_school_id')->pluck('total', 'from_school_id');

        $schools = School::with('users')->withCount('staff')->orderBy('name')
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->get()
            ->map(function (School $school) use ($pending) {
                $school->manager = $school->users->first(fn (User $user) => $user->role !== 'master_user' && ($user->status ?: 'active') === 'active');
                $school->state = $school->status !== 'active' ? 'inactive' : ($school->manager ? 'active' : 'vacant');
                $school->pending_transfers = (int) ($pending[$school->id] ?? 0);

                return $school;
            });
        $counts = $schools->countBy('state');

        return view('school-management', [
            'schools' => $filter ? $schools->where('state', $filter)->values() : $schools,
            'counts' => $counts,
            'total' => $schools->count(),
            'search' => $search,
            'filter' => $filter,
            'activeNavRoute' => 'school-management',
        ]);
    }

    public function show(Request $request, School $school)
    {
        $this->master($request);

        return view('school-management-show', [
            'school' => $school->load('organization'),
            'users' => User::where('school_id', $school->id)->where('role', '!=', 'master_user')->orderBy('name')->get(),
            'employees' => SchoolStaff::where('school_id', $school->id)->orderBy('name')->get(),
            'inactiveEmployees' => SchoolStaff::withoutGlobalScope('active')->where('school_id', $school->id)->whereNotNull('ended_at')->orderByDesc('ended_at')->get(),
            'reasons' => SchoolManagementService::REASONS,
            'activeNavRoute' => 'school-management',
        ]);
    }

    public function status(Request $request, School $school): RedirectResponse
    {
        $this->master($request);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $this->service->setSchoolActive($school, (bool) $data['active'], $request->user());

        return $this->back($school, $school->name.' is now '.($data['active'] ? 'active' : 'inactive').'.');
    }

    public function deactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->master($request);
        $data = $this->deactivationData($request);
        $this->service->deactivateUser($user, $request->user(), $data['reason'], $data['effective_date'], $data['note'] ?? null);

        return $this->back($user->school_id, $user->name.' is now inactive. The school is vacant.');
    }

    public function reactivateUser(Request $request, User $user): RedirectResponse
    {
        $this->master($request);
        $this->service->reactivateUser($user, $request->user());

        return $this->back($user->school_id, $user->name.' is active again.');
    }

    public function deactivateEmployee(Request $request, int $employee): RedirectResponse
    {
        $this->master($request);
        $record = SchoolStaff::withoutGlobalScope('active')->findOrFail($employee);
        $data = $this->deactivationData($request);
        $this->service->deactivateEmployee($record, $request->user(), $data['reason'], $data['effective_date'], $data['note'] ?? null);

        return $this->back($record->school_id, $record->name.' is now inactive.');
    }

    public function reactivateEmployee(Request $request, int $employee): RedirectResponse
    {
        $this->master($request);
        $record = SchoolStaff::withoutGlobalScope('active')->findOrFail($employee);
        $this->service->reactivateEmployee($record, $request->user());

        return $this->back($record->school_id, $record->name.' is active again.');
    }

    private function deactivationData(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'in:'.implode(',', SchoolManagementService::REASONS)],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function master(Request $request): void
    {
        abort_unless($request->user()?->role === 'master_user', 403);
    }

    private function back(School|int|null $school, string $message): RedirectResponse
    {
        $id = $school instanceof School ? $school->id : $school;

        return redirect()->route('school-management.show', $id)->with('success', $message);
    }
}
