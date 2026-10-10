<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\SchoolTakeoverRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A new person registers to take over a school that has no user; the master gives them the school and its data. */
class SchoolTakeoverService
{
    /** Active schools with no active user and no takeover request waiting. */
    public function vacantSchools(): Collection
    {
        return School::withoutGlobalScopes()->where('status', 'active')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.school_id', 'schools.id')->whereNotIn('users.role', User::MASTER_ROLES)->where(fn ($q) => $q->whereNull('users.status')->orWhere('users.status', 'active')))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('school_takeover_requests')->whereColumn('school_takeover_requests.school_id', 'schools.id')->where('school_takeover_requests.status', 'pending'))
            ->orderBy('name')->get(['id', 'name', 'division', 'district']);
    }

    /** Requests from people who asked to take over a school but name none; the master chooses the Official Station. */
    public function waitingForSchool(): Collection
    {
        return SchoolTakeoverRequest::with('user')->whereNull('school_id')->where('status', 'pending')->oldest('id')->get();
    }

    /**
     * A person asks to take over a school without naming one; the master decides which school when approving.
     *
     * @param  array<string, mixed>  $userData  attributes for the new user (name, username, email, phone, position, password)
     */
    public function register(array $userData, ?string $note = null): SchoolTakeoverRequest
    {
        return DB::transaction(function () use ($userData, $note) {
            // The person has no school until the master approves, so they cannot sign in or see any school data.
            $user = User::create([...$userData, 'role' => 'school_admin', 'status' => 'pending', 'organization_id' => null, 'school_id' => null]);
            $request = SchoolTakeoverRequest::create(['user_id' => $user->id, 'school_id' => null, 'note' => filled($note) ? $note : null, 'status' => 'pending']);
            $this->audit(null, null, 'submitted_school_takeover', $request->id, ['user_id' => $user->id]);

            return $request;
        });
    }

    public function approve(SchoolTakeoverRequest $request, User $master, ?string $note = null, ?int $schoolId = null): void
    {
        $this->assertMaster($master);

        DB::transaction(function () use ($request, $master, $note, $schoolId) {
            $request = SchoolTakeoverRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                $this->fail('request', 'This request was already decided.');
            }
            // A request made before the school was left to the master already names one; any other needs the master's choice.
            $chosen = $request->school_id ?? $schoolId;
            if (! $chosen) {
                $this->fail('school_id', 'Choose the Official Station, the school this person will manage.');
            }
            if (! $request->school_id && ! $this->vacantSchools()->contains('id', (int) $chosen)) {
                $this->fail('school_id', 'Choose a school that is open for a new user.');
            }
            $school = School::withoutGlobalScopes()->findOrFail($chosen);
            $user = User::withoutGlobalScopes()->findOrFail($request->user_id);
            if ($school->status !== 'active') {
                $this->fail('request', 'Activate the school first.');
            }
            $hasUser = User::withoutGlobalScopes()->where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))->exists();
            if ($hasUser) {
                $this->fail('request', 'This school now has a user. A school can only have one.');
            }

            $user->update(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'status' => 'active', 'role' => 'school_admin', 'password_changed_at' => now()]);
            SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => $user->name, 'position' => $user->position]);
            Subscription::create([
                'organization_id' => $school->organization_id,
                'school_id' => $school->id,
                'user_id' => $user->id,
                'plan' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => 0,
                'payment_status' => 'pending',
                'status' => 'trial',
                'starts_at' => now(),
                'subscription_end' => now()->addDays(30),
            ]);
            $request->update(['status' => 'approved', 'school_id' => $school->id, 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->audit($master->id, $school->id, 'approved_school_takeover', $request->id, ['user_id' => $user->id]);
        });
    }

    public function decline(SchoolTakeoverRequest $request, User $master, ?string $note = null): void
    {
        $this->assertMaster($master);

        DB::transaction(function () use ($request, $master, $note) {
            $request = SchoolTakeoverRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                $this->fail('request', 'This request was already decided.');
            }
            User::withoutGlobalScopes()->whereKey($request->user_id)->update(['status' => 'inactive']);
            $request->update(['status' => 'declined', 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->audit($master->id, $request->school_id, 'declined_school_takeover', $request->id, ['user_id' => $request->user_id]);
        });
    }

    private function audit(?int $userId, ?int $schoolId, string $action, int $id, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $userId, 'school_id' => $schoolId, 'action' => $action, 'auditable_type' => SchoolTakeoverRequest::class, 'auditable_id' => $id, 'metadata' => $metadata]);
    }

    private function assertMaster(User $user): void
    {
        abort_unless($user->hasAccess('transfers'), 403);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
