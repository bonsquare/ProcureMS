<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StationTransferService
{
    private const SCHOOL_FIELDS = ['name', 'school_type', 'region', 'division', 'district', 'address', 'contact_email', 'contact_number'];

    /** @param array{reason: string, to_school_id?: int|null, proposed_school?: array<string, mixed>|null} $data */
    public function request(User $user, array $data): StationTransferRequest
    {
        if ($user->role === 'master_user') {
            $this->fail('reason', 'The master user has no Official Station to transfer from.');
        }
        if (! $this->isActive($user)) {
            $this->fail('reason', 'Your account is not active.');
        }
        if (StationTransferRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            $this->fail('reason', 'You already have a pending transfer request. Cancel it first to send a new one.');
        }

        $toSchoolId = $data['to_school_id'] ?? null;
        $proposed = null;
        if ($toSchoolId) {
            $target = School::withoutGlobalScopes()->find($toSchoolId);
            if (! $target || $target->status !== 'active') {
                $this->fail('to_school_id', 'Choose a school that is registered and active.');
            }
            if ((int) $target->id === (int) $user->school_id) {
                $this->fail('to_school_id', 'That is already your Official Station.');
            }
            if ($this->hasActiveUser($target)) {
                $this->fail('to_school_id', 'That school already has a user. Only a vacant school can be chosen.');
            }
        } elseif (filled($data['proposed_school']['name'] ?? null)) {
            $proposed = array_intersect_key($data['proposed_school'], array_flip(self::SCHOOL_FIELDS));
        } else {
            $this->fail('to_school_id', "Choose a destination school or enter the new school's details.");
        }

        return StationTransferRequest::create([
            'user_id' => $user->id,
            'from_school_id' => $user->school_id,
            'from_organization_id' => $user->organization_id,
            'to_school_id' => $toSchoolId,
            'proposed_school' => $proposed,
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    public function cancel(StationTransferRequest $request, User $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id || ! $request->isPending()) {
            $this->fail('request', 'Only the requester can cancel a pending request.');
        }

        $request->update(['status' => 'cancelled']);
    }

    public function decline(StationTransferRequest $request, User $master, ?string $note = null): void
    {
        $this->assertMaster($master);
        if (! $request->isPending()) {
            $this->fail('request', 'This request was already decided.');
        }

        $request->update(['status' => 'declined', 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function approve(StationTransferRequest $request, User $master, ?string $note = null): StationTransferRequest
    {
        $this->assertMaster($master);

        return DB::transaction(function () use ($request, $master, $note) {
            $request = StationTransferRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                $this->fail('request', 'This request was already decided.');
            }
            $user = User::withoutGlobalScopes()->findOrFail($request->user_id);
            if (! $this->isActive($user)) {
                $this->fail('request', "The user's account is not active. Reactivate it before approving.");
            }

            $target = $request->to_school_id
                ? School::withoutGlobalScopes()->findOrFail($request->to_school_id)
                : $this->createSchool($request->proposed_school ?? []);
            if ((int) $target->id === (int) $user->school_id) {
                $this->fail('request', 'The user is already at that school.');
            }
            if ($this->hasActiveUser($target)) {
                $this->fail('request', 'That school now has a user. A school can only have one.');
            }

            $fromSchoolId = $user->school_id;
            SchoolStaff::withoutGlobalScopes()
                ->where('school_id', $fromSchoolId)->where('name', $user->name)->whereNull('ended_at')
                ->update(['ended_at' => now()]);
            SchoolStaff::create([
                'organization_id' => $target->organization_id,
                'school_id' => $target->id,
                'name' => $user->name,
                'position' => $user->position,
            ]);

            $user->update(['organization_id' => $target->organization_id, 'school_id' => $target->id]);
            $request->update(['status' => 'approved', 'to_school_id' => $target->id, 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);

            foreach ([$fromSchoolId, $target->id] as $schoolId) {
                AuditLog::create([
                    'user_id' => $master->id,
                    'school_id' => $schoolId,
                    'action' => 'approved_station_transfer',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'metadata' => ['request_id' => $request->id, 'from_school_id' => $fromSchoolId, 'to_school_id' => $target->id],
                ]);
            }

            return $request;
        });
    }

    /** Marks the user's approved transfer as confirmed; false when there was nothing to confirm. */
    public function confirm(User $user): bool
    {
        $request = StationTransferRequest::where('user_id', $user->id)->where('status', 'approved')->whereNull('confirmed_at')->oldest('id')->first();
        $request?->update(['confirmed_at' => now()]);

        return (bool) $request;
    }

    private function createSchool(array $proposed): School
    {
        $organization = Organization::create([
            'name' => $proposed['name'],
            'slug' => 'org-'.Str::lower(Str::random(12)),
            'status' => 'active',
            'fiscal_year' => now()->year,
        ]);
        $organization->update(['organization_code' => sprintf('ORG-%06d', $organization->id)]);

        $next = max(1000, ((int) School::withoutGlobalScopes()->max('id')) + 1000);
        do {
            $code = 'SCH-'.$next++;
        } while (School::withoutGlobalScopes()->where('code', $code)->exists());

        // No subscription: the plan belongs to the user who manages the school.
        return School::create([...array_intersect_key($proposed, array_flip(self::SCHOOL_FIELDS)), 'organization_id' => $organization->id, 'code' => $code, 'status' => 'active']);
    }

    private function hasActiveUser(School $school): bool
    {
        return User::withoutGlobalScopes()->where('school_id', $school->id)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))->exists();
    }

    private function isActive(User $user): bool
    {
        return ($user->status ?: 'active') === 'active';
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
