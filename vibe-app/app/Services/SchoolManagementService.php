<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Takes schools and their user out of service (and back). Nothing is ever deleted. */
class SchoolManagementService
{
    public const REASONS = ['Retired', 'Resigned', 'Transferred', 'Other'];

    public function setSchoolActive(School $school, bool $active, User $master): void
    {
        $this->assertMaster($master);
        $status = $active ? 'active' : 'inactive';
        if ($school->status === $status) {
            $this->fail('active', 'The school is already '.$status.'.');
        }

        DB::transaction(function () use ($school, $status, $active, $master) {
            $school->update(['status' => $status]);
            $school->organization?->update(['status' => $status]);
            $this->audit($master, $school->id, $active ? 'school_reactivated' : 'school_set_inactive', School::class, $school->id);
        });
    }

    public function deactivateUser(User $user, User $master, string $reason, string $effectiveDate, ?string $note): void
    {
        $this->assertUserManager($master);
        $this->assertReason($reason);
        if ($user->seesAllSchools()) {
            $this->fail('user', 'The master account cannot be set inactive here.');
        }
        if (! $this->isActive($user)) {
            $this->fail('user', 'This user is already inactive.');
        }

        DB::transaction(function () use ($user, $master, $reason, $effectiveDate, $note) {
            $user->update(['status' => 'inactive', 'deactivated_at' => $effectiveDate, 'deactivation_reason' => $reason, 'deactivation_note' => $note]);
            $this->audit($master, $user->school_id, 'user_set_inactive', User::class, $user->id, ['reason' => $reason, 'effective_date' => $effectiveDate, 'note' => $note]);
        });
    }

    public function reactivateUser(User $user, User $master): void
    {
        $this->assertUserManager($master);
        if ($this->isActive($user)) {
            $this->fail('user', 'This user is already active.');
        }
        $school = School::withoutGlobalScopes()->find($user->school_id);
        if (! $school || $school->status !== 'active') {
            $this->fail('user', 'Activate the school first.');
        }
        $hasOther = User::withoutGlobalScopes()->where('school_id', $school->id)->where('id', '!=', $user->id)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))->exists();
        if ($hasOther) {
            $this->fail('user', 'This school already has an active user. One user manages one school.');
        }

        DB::transaction(function () use ($user, $master, $school) {
            $user->update(['status' => 'active', 'deactivated_at' => null, 'deactivation_reason' => null, 'deactivation_note' => null]);
            $this->audit($master, $school->id, 'user_reactivated', User::class, $user->id);
        });
    }

    private function audit(User $master, ?int $schoolId, string $action, string $type, int $id, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $master->id, 'school_id' => $schoolId, 'action' => $action, 'auditable_type' => $type, 'auditable_id' => $id, 'metadata' => $metadata ?: null]);
    }

    private function isActive(User $user): bool
    {
        return ($user->status ?: 'active') === 'active';
    }

    private function assertMaster(User $user): void
    {
        abort_unless($user->hasAccess('schools'), 403);
    }

    /** The Users tab and School Management both manage accounts. */
    private function assertUserManager(User $user): void
    {
        abort_unless($user->hasAccess('schools') || $user->hasAccess('users'), 403);
    }

    private function assertReason(string $reason): void
    {
        if (! in_array($reason, self::REASONS, true)) {
            $this->fail('reason', 'Choose a reason: '.implode(', ', self::REASONS).'.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
