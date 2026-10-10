<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolTakeoverRequest;
use App\Models\StationTransferRequest;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use App\Services\StationTransferService;
use Illuminate\Http\Request;

class StationTransferController extends Controller
{
    public function __construct(private StationTransferService $transfers) {}

    public function index(Request $request)
    {
        abort_if($request->user()->seesAllSchools(), 403);

        return redirect()->route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']);
    }

    /** What the Station transfer tab in School Settings shows for a school user. */
    public function pageData(User $user): array
    {
        $this->transfers->expireDue();

        return [
            // The destination school's user answers requests waiting for it; both users see a running handover.
            'incoming' => StationTransferRequest::with(['user', 'fromSchool'])->where('reviewer_user_id', $user->id)->where('status', 'pending')->where('review_status', 'pending')->oldest('id')->get(),
            'handovers' => StationTransferRequest::with(['user', 'handoverUser', 'toSchool'])->where('status', 'approved')->whereNotNull('handover_user_id')->whereNull('handover_ended_at')
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('handover_user_id', $user->id))->get(),
            'requests' => StationTransferRequest::with(['fromSchool', 'toSchool', 'decider'])->where('user_id', $user->id)->latest('id')->get(),
            // A school that has a user must accept the request first; schools with a transfer in progress are left out.
            'destinationSchools' => School::withoutGlobalScopes()->where('status', 'active')->where('id', '!=', $user->school_id)
                ->get(['id', 'name', 'division', 'district'])
                ->reject(fn (School $school) => $this->transfers->incomingInProgress($school))
                ->each(fn (School $school) => $school->occupied = User::withoutGlobalScopes()->where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))->exists())
                ->sortBy('name')->values(),
            'station' => School::withoutGlobalScopes()->find($user->school_id),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'destination' => ['required', 'in:registered,new,official_station,other'],
            'subject' => ['required_if:destination,other', 'nullable', 'string', 'max:255'],
            'to_school_id' => ['required_if:destination,registered', 'nullable', 'integer'],
            'new_school.name' => ['required_if:destination,new', 'nullable', 'string', 'max:255'],
            'new_school.school_type' => ['nullable', 'string', 'max:100'],
            'new_school.region' => ['nullable', 'string', 'max:100'],
            'new_school.division' => ['nullable', 'string', 'max:255'],
            'new_school.district' => ['nullable', 'string', 'max:255'],
            'new_school.address' => ['nullable', 'string', 'max:1000'],
            'new_school.contact_email' => ['nullable', 'email', 'max:255'],
            'new_school.contact_number' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $registered = $data['destination'] === 'registered';
        $this->transfers->request($request->user(), [
            'kind' => in_array($data['destination'], ['official_station', 'other'], true) ? $data['destination'] : 'transfer',
            'subject' => $data['subject'] ?? null,
            'reason' => $data['reason'],
            'to_school_id' => $registered ? (int) $data['to_school_id'] : null,
            'proposed_school' => $registered ? null : ($data['new_school'] ?? null),
        ]);

        return redirect()->to(route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']))->with('success', 'Request sent to the master user.');
    }

    public function cancel(Request $request, StationTransferRequest $transfer)
    {
        $this->transfers->cancel($transfer, $request->user());

        return redirect()->to(route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']))->with('success', 'Request cancelled.');
    }

    public function review(Request $request, StationTransferRequest $transfer)
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,decline'], 'note' => ['nullable', 'string', 'max:500']]);
        $accept = $data['decision'] === 'accept';
        $this->transfers->review($transfer, $request->user(), $accept, $data['note'] ?? null);

        return redirect()->to(route('school-settings', ['ui' => 'staff-save-v7', 'tab' => 'transfer']))
            ->with('success', $accept ? 'Accepted. Both of you have access to this school for '.StationTransferService::HANDOVER_DAYS.' days after the master approves.' : 'Transfer request declined.');
    }

    public function confirmShow(Request $request)
    {
        $transfer = StationTransferRequest::with(['fromSchool', 'toSchool'])->where('user_id', $request->user()->id)
            ->where('status', 'approved')->whereNull('confirmed_at')->oldest('id')->first();

        return $transfer ? view('station-confirm', ['transfer' => $transfer, 'user' => $request->user(), 'activeNavRoute' => 'school-settings']) : redirect()->route('home');
    }

    public function confirmStore(Request $request)
    {
        $this->transfers->confirm($request->user());

        return redirect()->route('home')->with('success', 'Welcome to your new station.');
    }

    public function queue(Request $request)
    {
        abort_unless($request->user()->hasAccess('transfers'), 403);
        $this->transfers->expireDue();
        $with = ['user', 'fromSchool', 'toSchool', 'decider'];

        return view('transfer-requests', [
            'activeNavRoute' => 'school-settings',
            'takeoverQueue' => app(SchoolTakeoverService::class)->waitingForSchool(),
            'vacantSchools' => app(SchoolTakeoverService::class)->vacantSchools(),
            'takeoverHistory' => SchoolTakeoverRequest::with(['user', 'school', 'decider'])->where('status', '!=', 'pending')->latest('decided_at')->latest('id')->limit(50)->get(),
            'pending' => StationTransferRequest::with($with)->where('status', 'pending')->oldest('id')->get(),
            'history' => StationTransferRequest::with($with)->where('status', '!=', 'pending')->latest('id')->limit(50)->get(),
        ]);
    }

    public function approve(Request $request, StationTransferRequest $transfer)
    {
        abort_unless($request->user()->hasAccess('transfers'), 403);
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:500'], 'school_id' => ['nullable', 'integer']]);
        $this->transfers->approve($transfer, $request->user(), $data['decision_note'] ?? null, isset($data['school_id']) ? (int) $data['school_id'] : null);

        return $this->afterDecision($request, $transfer)->with('success', $transfer->refresh()->kind === 'other' ? 'Request approved.' : 'Approved. The user confirms the new station at next sign-in.');
    }

    public function decline(Request $request, StationTransferRequest $transfer)
    {
        abort_unless($request->user()->hasAccess('transfers'), 403);
        $note = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']])['decision_note'] ?? null;
        $this->transfers->decline($transfer, $request->user(), $note);

        return $this->afterDecision($request, $transfer)->with('success', 'Request declined.');
    }

    /** Back to the school page when the decision was made there, otherwise to the request queue. */
    private function afterDecision(Request $request, StationTransferRequest $transfer)
    {
        return $request->input('back') === 'school' && $transfer->from_school_id
            ? redirect()->route('school-management.show', $transfer->from_school_id)
            : redirect()->route('transfer-requests');
    }
}
