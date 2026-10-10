<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StationTransferService
{
    /** Days the destination school has to answer, and days both users share the school after it accepted. */
    public const REVIEW_DAYS = 5;

    public const HANDOVER_DAYS = 5;

    private const SCHOOL_FIELDS = ['name', 'school_type', 'region', 'division', 'district', 'address', 'contact_email', 'contact_number'];

    /** @param array{reason: string, kind?: string, subject?: string|null, to_school_id?: int|null, proposed_school?: array<string, mixed>|null} $data */
    public function request(User $user, array $data): StationTransferRequest
    {
        $this->expireDue();

        if ($user->seesAllSchools()) {
            $this->fail('reason', 'The master user has no Official Station to transfer from.');
        }
        if (! $this->isActive($user)) {
            $this->fail('reason', 'Your account is not active.');
        }
        if (StationTransferRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            $this->fail('reason', 'You already have a pending request. Cancel it first to send a new one.');
        }

        $kind = $data['kind'] ?? 'transfer';
        if ($kind !== 'transfer') {
            if ($kind === 'other' && blank($data['subject'] ?? null)) {
                $this->fail('subject', 'Type what you are asking for.');
            }

            // The master decides; there is no destination school to ask.
            return StationTransferRequest::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'subject' => $kind === 'other' ? $data['subject'] : null,
                'from_school_id' => $user->school_id,
                'from_organization_id' => $user->organization_id,
                'reason' => $data['reason'],
                'status' => 'pending',
                'requested_at' => now(),
                'review_status' => 'not_required',
            ]);
        }

        $toSchoolId = $data['to_school_id'] ?? null;
        $proposed = null;
        $reviewer = null;
        if ($toSchoolId) {
            $target = School::withoutGlobalScopes()->find($toSchoolId);
            if (! $target || $target->status !== 'active') {
                $this->fail('to_school_id', 'Choose a school that is registered and active.');
            }
            if ((int) $target->id === (int) $user->school_id) {
                $this->fail('to_school_id', 'That is already your Official Station.');
            }
            if ($this->incomingInProgress($target)) {
                $this->fail('to_school_id', 'Another transfer into that school is in progress. Try again when it is done.');
            }
            $reviewer = $this->activeUsersOf($target)->first();
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
            // A school that has a user must accept first; a vacant school has nobody to ask.
            'review_status' => $reviewer ? 'pending' : 'not_required',
            'reviewer_user_id' => $reviewer?->id,
            'review_expires_at' => $reviewer ? now()->addDays(self::REVIEW_DAYS) : null,
        ]);
    }

    public function cancel(StationTransferRequest $request, User $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id || ! $request->isPending()) {
            $this->fail('request', 'Only the requester can cancel a pending request.');
        }

        $request->update(['status' => 'cancelled']);
    }

    /** The destination school's user accepts (starting the handover clock) or declines (closing the request). */
    public function review(StationTransferRequest $request, User $reviewer, bool $accept, ?string $note): void
    {
        $this->expireDue();
        $request->refresh();

        if ($request->status === 'expired') {
            $this->fail('request', 'This request expired because nobody answered in '.self::REVIEW_DAYS.' days.');
        }
        if (! $request->isPending() || $request->review_status !== 'pending' || (int) $request->reviewer_user_id !== (int) $reviewer->id) {
            $this->fail('request', 'Only the destination school can answer a request that is waiting for it.');
        }

        DB::transaction(function () use ($request, $reviewer, $accept, $note) {
            if ($accept) {
                $request->update([
                    'review_status' => 'accepted',
                    'reviewed_at' => now(),
                    'review_note' => $note,
                    'handover_ends_at' => now()->addDays(self::HANDOVER_DAYS),
                    'handover_user_id' => $reviewer->id,
                ]);
            } else {
                $request->update(['review_status' => 'declined', 'reviewed_at' => now(), 'review_note' => $note, 'status' => 'declined', 'decision_note' => $note]);
            }

            $this->audit($reviewer->id, $reviewer->school_id, $accept ? 'accepted_station_transfer' : 'declined_station_transfer', User::class, $request->user_id, ['request_id' => $request->id]);
        });
    }

    public function decline(StationTransferRequest $request, User $master, ?string $note = null): void
    {
        $this->assertMaster($master);
        $this->expireDue();
        $request->refresh();
        if (! $request->isPending()) {
            $this->fail('request', 'This request was already decided.');
        }

        $request->update(['status' => 'declined', 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function approve(StationTransferRequest $request, User $master, ?string $note = null, ?int $schoolId = null): StationTransferRequest
    {
        $this->assertMaster($master);
        $this->expireDue();

        return DB::transaction(function () use ($request, $master, $note, $schoolId) {
            $request = StationTransferRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status === 'expired') {
                $this->fail('request', 'This request expired because the destination school did not answer in '.self::REVIEW_DAYS.' days. The user can send a new one.');
            }
            if (! $request->isPending()) {
                $this->fail('request', 'This request was already decided.');
            }
            if (! $request->canBeApproved()) {
                $this->fail('request', 'The destination school has not accepted this transfer yet.');
            }
            $user = User::withoutGlobalScopes()->findOrFail($request->user_id);
            if (! $this->isActive($user)) {
                $this->fail('request', "The user's account is not active. Reactivate it before approving.");
            }

            // A typed request has nothing to move; approving it is the master's answer, and there is nothing to confirm.
            if ($request->kind === 'other') {
                $request->update(['status' => 'approved', 'decided_by' => $master->id, 'decided_at' => now(), 'decision_note' => $note, 'confirmed_at' => now()]);
                $this->audit($master->id, $user->school_id, 'approved_user_request', User::class, $user->id, ['request_id' => $request->id, 'subject' => $request->subject]);

                return $request->refresh();
            }

            if ($request->kind === 'official_station') {
                if (! $schoolId) {
                    $this->fail('school_id', 'Choose the Official Station, the school this person will manage.');
                }
                if (! app(SchoolTakeoverService::class)->vacantSchools()->contains('id', $schoolId)) {
                    $this->fail('school_id', 'Choose a school that is open for a new user.');
                }
                $request->to_school_id = $schoolId;
            }

            $target = $request->to_school_id
                ? School::withoutGlobalScopes()->findOrFail($request->to_school_id)
                : $this->createSchool($request->proposed_school ?? []);
            if ((int) $target->id === (int) $user->school_id) {
                $this->fail('request', 'The user is already at that school.');
            }
            // Only the user who accepted may still be there (they stay for the handover).
            if ($this->activeUsersOf($target)->where('id', '!=', $request->handover_user_id)->isNotEmpty()) {
                $this->fail('request', 'That school now has another user. A school can only have one.');
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
                $this->audit($master->id, $schoolId, 'approved_station_transfer', User::class, $user->id, ['request_id' => $request->id, 'from_school_id' => $fromSchoolId, 'to_school_id' => $target->id]);
            }

            // The accepting user already used up part of the handover while the master decided; if it is over, they leave now.
            if ($request->handover_user_id && $request->handover_ends_at && $request->handover_ends_at->lte(now())) {
                $this->endHandover($request);
            }

            return $request->refresh();
        });
    }

    /** Marks the user's approved transfer as confirmed; false when there was nothing to confirm. */
    public function confirm(User $user): bool
    {
        $request = StationTransferRequest::where('user_id', $user->id)->where('status', 'approved')->whereNull('confirmed_at')->oldest('id')->first();
        $request?->update(['confirmed_at' => now()]);

        return (bool) $request;
    }

    /** Sets the previous user of the destination school inactive; false when there is nothing to end. */
    public function endHandover(StationTransferRequest $request): bool
    {
        if (! $request->handoverRunning()) {
            return false;
        }

        DB::transaction(function () use ($request) {
            $previous = User::withoutGlobalScopes()->find($request->handover_user_id);
            if ($previous && ($previous->status ?: 'active') === 'active') {
                $previous->update(['status' => 'inactive', 'deactivated_at' => now(), 'deactivation_reason' => 'Transferred', 'deactivation_note' => 'Handover ended']);
            }
            $request->update(['handover_ended_at' => now()]);
            $this->audit(null, $request->to_school_id, 'handover_ended', User::class, $request->handover_user_id, ['request_id' => $request->id]);
        });

        return true;
    }

    public function endHandoverNow(StationTransferRequest $request, User $master): void
    {
        $this->assertMaster($master);
        if (! $this->endHandover($request)) {
            $this->fail('request', 'There is no running handover for this request.');
        }
    }

    /** Ends every handover whose 5 days are over; returns how many. */
    public function endDueHandovers(?User $only = null): int
    {
        return StationTransferRequest::where('status', 'approved')->whereNotNull('handover_user_id')->whereNull('handover_ended_at')
            ->where('handover_ends_at', '<=', now())
            ->when($only, fn ($query) => $query->where('handover_user_id', $only->id))
            ->get()->filter(fn (StationTransferRequest $request) => $this->endHandover($request))->count();
    }

    /** Closes requests the destination school did not answer in time; returns how many. */
    public function expireDue(): int
    {
        return StationTransferRequest::where('status', 'pending')->where('review_status', 'pending')->where('review_expires_at', '<=', now())
            ->update(['status' => 'expired', 'expired_at' => now()]);
    }

    /** True while another transfer into the school is waiting or its handover is still running. */
    public function incomingInProgress(School $school): bool
    {
        return StationTransferRequest::where('to_school_id', $school->id)
            ->where(fn ($query) => $query->where('status', 'pending')->orWhere(fn ($running) => $running->where('status', 'approved')->whereNotNull('handover_user_id')->whereNull('handover_ended_at')))
            ->exists();
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

    /** @return Collection<int, User> */
    private function activeUsersOf(School $school)
    {
        return User::withoutGlobalScopes()->where('school_id', $school->id)->whereNotIn('role', User::MASTER_ROLES)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))->get();
    }

    private function audit(?int $userId, ?int $schoolId, string $action, string $type, int $id, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $userId, 'school_id' => $schoolId, 'action' => $action, 'auditable_type' => $type, 'auditable_id' => $id, 'metadata' => $metadata ?: null]);
    }

    private function isActive(User $user): bool
    {
        return ($user->status ?: 'active') === 'active';
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
