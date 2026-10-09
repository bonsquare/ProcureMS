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
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.school_id', 'schools.id')->where('users.role', '!=', 'master_user')->where(fn ($q) => $q->whereNull('users.status')->orWhere('users.status', 'active')))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('school_takeover_requests')->whereColumn('school_takeover_requests.school_id', 'schools.id')->where('school_takeover_requests.status', 'pending'))
            ->orderBy('name')->get(['id', 'name', 'division', 'district']);
    }

    /** @param array<string, mixed> $userData attributes for the new user (name, username, email, phone, position, password) */
    public function register(array $userData, int $schoolId): SchoolTakeoverRequest
    {
        return DB::transaction(function () use ($userData, $schoolId) {
            if (! $this->vacantSchools()->contains('id', $schoolId)) {
                $this->fail('takeover_school_id', 'Choose a school that is open for a new user.');
            }

            // The person has no school until the master approves, so they cannot sign in or see any school data.
            $user = User::create([...$userData, 'role' => 'school_admin', 'status' => 'pending', 'organization_id' => null, 'school_id' => null]);
            $request = SchoolTakeoverRequest::create(['user_id' => $user->id, 'school_id' => $schoolId, 'status' => 'pending']);
            $this->audit(null, $schoolId, 'submitted_school_takeover', $request->id, ['user_id' => $user->id]);

            return $request;
        });
    }

    public function approve(SchoolTakeoverRequest $request, User $master, ?string $note = null): void
    {
        $this->assertMaster($master);

        DB::transaction(function () use ($request, $master, $note) {
            $request = SchoolTakeoverRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                $this->fail('request', 'This request was already decided.');
            }
            $school = School::withoutGlobalScopes()->findOrFail($request->school_id);
            $user = User::withoutGlobalScopes()->findOrFail($request->user_id);
            if ($school->status !== 'active') {
                $this->fail('request', 'Activate the school first.');
            }
            $hasUser = User::withoutGlobalScopes()->where('school_id', $school->id)->where('role', '!=', 'master_user')
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
            $request->update(['status' => 'approved', 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);
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

    private function audit(?int $userId, int $schoolId, string $action, int $id, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $userId, 'school_id' => $schoolId, 'action' => $action, 'auditable_type' => SchoolTakeoverRequest::class, 'auditable_id' => $id, 'metadata' => $metadata]);
    }

    private function assertMaster(User $user): void
    {
        abort_unless($user->role === 'master_user', 403);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
