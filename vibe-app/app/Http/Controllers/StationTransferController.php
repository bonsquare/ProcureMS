<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\StationTransferRequest;
use App\Services\StationTransferService;
use Illuminate\Http\Request;

class StationTransferController extends Controller
{
    public function __construct(private StationTransferService $transfers) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->role === 'master_user', 403);

        return view('station-transfer', [
            'activeNavRoute' => 'school-settings',
            'requests' => StationTransferRequest::with(['fromSchool', 'toSchool', 'decider'])->where('user_id', $user->id)->latest('id')->get(),
            // One user manages one school, so only vacant schools can be chosen.
            'schools' => School::withoutGlobalScopes()->where('status', 'active')->where('id', '!=', $user->school_id)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.school_id', 'schools.id')->where(fn ($q) => $q->whereNull('users.status')->orWhere('users.status', 'active')))
                ->orderBy('name')->get(['id', 'name', 'division', 'district']),
            'station' => School::withoutGlobalScopes()->find($user->school_id),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'destination' => ['required', 'in:registered,new'],
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
            'reason' => $data['reason'],
            'to_school_id' => $registered ? (int) $data['to_school_id'] : null,
            'proposed_school' => $registered ? null : $data['new_school'],
        ]);

        return redirect()->route('station-transfer')->with('success', 'Transfer request sent to the master user.');
    }

    public function cancel(Request $request, StationTransferRequest $transfer)
    {
        $this->transfers->cancel($transfer, $request->user());

        return redirect()->route('station-transfer')->with('success', 'Transfer request cancelled.');
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
        abort_unless($request->user()->role === 'master_user', 403);
        $with = ['user', 'fromSchool', 'toSchool', 'decider'];

        return view('transfer-requests', [
            'activeNavRoute' => 'school-settings',
            'pending' => StationTransferRequest::with($with)->where('status', 'pending')->oldest('id')->get(),
            'history' => StationTransferRequest::with($with)->where('status', '!=', 'pending')->latest('id')->limit(50)->get(),
        ]);
    }

    public function approve(Request $request, StationTransferRequest $transfer)
    {
        abort_unless($request->user()->role === 'master_user', 403);
        $note = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']])['decision_note'] ?? null;
        $this->transfers->approve($transfer, $request->user(), $note);

        return redirect()->route('transfer-requests')->with('success', 'Transfer approved. The user confirms the new station at next sign-in.');
    }

    public function decline(Request $request, StationTransferRequest $transfer)
    {
        abort_unless($request->user()->role === 'master_user', 403);
        $note = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']])['decision_note'] ?? null;
        $this->transfers->decline($transfer, $request->user(), $note);

        return redirect()->route('transfer-requests')->with('success', 'Transfer request declined.');
    }
}
