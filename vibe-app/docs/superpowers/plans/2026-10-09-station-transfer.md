# Station Transfer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A school user can ask the master user to move them to another Official Station (a registered school or a school not yet registered); on approval the same account works in the destination school's data after the user confirms the new station.

**Architecture:** One new table (`station_transfer_requests`) and one service (`StationTransferService`) that holds every rule and runs the approval in a single DB transaction. The tenancy scope (`BelongsToOrganization`) already filters by `users.organization_id`, so moving the account (`organization_id` + `school_id`) is what changes the visible data; no school data is copied or moved. A small web-group middleware sends a user with an unconfirmed approved transfer to a confirmation page.

**Tech Stack:** Laravel 13, PHP 8.4, SQLite, Blade + Tailwind (CDN), PHPUnit (`php artisan test`).

**Spec:** `docs/superpowers/specs/2026-10-09-station-transfer-design.md` (run all commands from the `vibe-app/` directory).

## Deviations from the spec (found while reading the code)

1. **No `users.station_confirmed_at` column.** Existing users would all count as "unconfirmed". Confirmation is tracked on the request (`status = approved` and `confirmed_at IS NULL`). Same behavior, no backfill.
2. **Employee number is not copied.** `school_staff.employee_no` is unique; the new record gets its own generated number (the model already does this).
3. **No unique-school-ID check.** School codes are auto-generated (`SCH-xxxx`) and cannot collide.
4. **Master queue is its own page** (`/transfer-requests`, linked from the user menu with a pending count). The Subscriptions page is a static mock-up with no tab structure.
5. **Staff history uses `school_staff.ended_at`** (no status column existed). Ended rows are hidden by a global scope so every existing staff query keeps working unchanged.
6. **Subscriptions are per organization.** After the move, the user falls under the destination school's subscription (a school created on approval gets a 30-day trial, as in pre-registration). The spec's "subscription" wording means the user's account, not the old school's plan.
7. **The employee record is matched to the user by name and school** (`school_staff` has no `user_id`). If no match exists, nothing is ended and a new record is still created.

## Global Constraints

- Username, full name, user code, password, `role` and `status` of the user never change on transfer (only `organization_id` and `school_id`).
- School data is never copied or moved; the old school keeps all of its records.
- Only the master user (`role = master_user`) approves or declines. A master user cannot request a transfer.
- One pending request per user. Destination may not be the user's current school.
- New employee record has no roles. Old employee record is kept (ended), never deleted.
- Approval is one DB transaction: any failure leaves everything unchanged.
- Transfer routes use the `auth` middleware only (not `subscription.writes`), so an expired old school's user can still request a transfer.
- Code style: Pint, match surrounding Blade (compact Tailwind, `rounded-xl border border-outline-variant/60 bg-white` cards).

## Review Focus

- Approve clicked twice (double submit): second call must fail with "already decided" and create no second staff record.
- User deactivated between request and approval: approval is blocked, nothing changes.
- User has no matching employee record at the old school: approval still works.
- Failure after the new school is created (inside the transaction): the school, staff, and user move are all rolled back.
- User with an unconfirmed approved transfer opens any other page (or posts): redirected to the confirmation page, but can still log out.

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_10_10_000001_create_station_transfer_requests.php` | New table + `school_staff.ended_at` |
| `app/Models/StationTransferRequest.php` | Model, relations, status helpers |
| `app/Models/SchoolStaff.php` (modify) | `ended_at` fillable + `active` global scope |
| `app/Services/StationTransferService.php` | request / cancel / decline / approve / confirm / sole-admin warning |
| `app/Http/Middleware/EnsureStationConfirmed.php` | Redirect unconfirmed users to the confirmation page |
| `app/Http/Controllers/StationTransferController.php` | User page, master queue, confirm page |
| `resources/views/station-transfer.blade.php`, `transfer-requests.blade.php`, `station-confirm.blade.php` | The three screens |
| `routes/web.php`, `bootstrap/app.php`, `resources/views/layouts/procurement.blade.php` (modify) | Routes, middleware registration, user-menu links |
| `tests/Feature/StationTransferTest.php` | All tests for this feature |

---

### Task 1: Table, model and ended-staff scope

**Files:**
- Create: `database/migrations/2026_10_10_000001_create_station_transfer_requests.php`
- Create: `app/Models/StationTransferRequest.php`
- Modify: `app/Models/SchoolStaff.php`
- Test: `tests/Feature/StationTransferTest.php`

**Interfaces:**
- Produces: `StationTransferRequest` with fillable `user_id, from_school_id, from_organization_id, to_school_id, proposed_school, reason, status, requested_at, decided_by, decided_at, decision_note, confirmed_at`; relations `user()`, `fromSchool()`, `toSchool()`, `decider()`; methods `isPending(): bool`, `destinationName(): string`. `SchoolStaff` rows with `ended_at` set are hidden from default queries.

- [ ] **Step 1: Write the failing test** — create `tests/Feature/StationTransferTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StationTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StationTransferTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $role = 'school_admin'): array
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => $slug.' School', 'status' => 'active']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'plan' => 'professional', 'billing_cycle' => 'monthly', 'amount' => 0, 'payment_status' => 'paid', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => $role]);

        return [$organization, $school, $user];
    }

    private function master(): User
    {
        return User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
    }

    private function staff(Organization $organization, School $school, string $name, array $extra = []): SchoolStaff
    {
        return SchoolStaff::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => $name, ...$extra]);
    }

    public function test_ended_staff_records_are_hidden_from_normal_queries_but_kept(): void
    {
        [$organization, $school] = $this->tenant('hide-a');
        $kept = $this->staff($organization, $school, 'Active Person');
        $ended = $this->staff($organization, $school, 'Former Person');
        $ended->update(['ended_at' => now()]);

        $this->assertSame(['Active Person'], SchoolStaff::pluck('name')->all());
        $this->assertSame(2, SchoolStaff::withoutGlobalScopes()->where('school_id', $school->id)->count());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=StationTransferTest`
Expected: FAIL (`ended_at` column / `StationTransferRequest` class not found).

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('from_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('to_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->json('proposed_school')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('school_staff', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_staff', function (Blueprint $table) {
            $table->dropColumn('ended_at');
        });
        Schema::dropIfExists('station_transfer_requests');
    }
};
```

- [ ] **Step 4: Create the model** `app/Models/StationTransferRequest.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationTransferRequest extends Model
{
    protected $fillable = [
        'user_id', 'from_school_id', 'from_organization_id', 'to_school_id', 'proposed_school', 'reason',
        'status', 'requested_at', 'decided_by', 'decided_at', 'decision_note', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_school' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function fromSchool()
    {
        return $this->belongsTo(School::class, 'from_school_id')->withoutGlobalScopes();
    }

    public function toSchool()
    {
        return $this->belongsTo(School::class, 'to_school_id')->withoutGlobalScopes();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function destinationName(): string
    {
        return $this->toSchool?->name ?? ($this->proposed_school['name'] ?? '—');
    }
}
```

- [ ] **Step 5: Modify `app/Models/SchoolStaff.php`** — add `'ended_at'` to `$fillable` (after `'employee_no'`), and at the top of `booted()` add the scope:

```php
        // Employees who left the school's station stay in the table as history but drop out of every list and role lookup.
        static::addGlobalScope('active', fn ($query) => $query->whereNull($query->getModel()->getTable().'.ended_at'));

```

- [ ] **Step 6: Run the test, then Pint**

Run: `php artisan test --filter=StationTransferTest` → PASS
Run: `vendor/bin/pint app/Models database/migrations/2026_10_10_000001_create_station_transfer_requests.php tests/Feature/StationTransferTest.php`

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_10_10_000001_create_station_transfer_requests.php app/Models/StationTransferRequest.php app/Models/SchoolStaff.php tests/Feature/StationTransferTest.php
git commit -m "feat: add station transfer request table and ended-staff scope"
```

---

### Task 2: StationTransferService (all rules)

**Files:**
- Create: `app/Services/StationTransferService.php`
- Test: `tests/Feature/StationTransferTest.php`

**Interfaces:**
- Consumes: `StationTransferRequest`, `SchoolStaff` ended scope (Task 1).
- Produces (all on `App\Services\StationTransferService`):
  - `request(User $user, array $data): StationTransferRequest` — `$data` keys: `reason` (string), and either `to_school_id` (int) or `proposed_school` (array with at least `name`). Throws `ValidationException` for: master user, inactive user, pending request exists, destination equals current school, destination not an active school.
  - `cancel(StationTransferRequest $request, User $actor): void` — owner only, pending only.
  - `decline(StationTransferRequest $request, User $master, ?string $note = null): void`
  - `approve(StationTransferRequest $request, User $master, ?string $note = null): StationTransferRequest`
  - `confirm(User $user): bool` — sets `confirmed_at` on the user's oldest unconfirmed approved request; returns whether one existed.
  - `losesLastAdmin(StationTransferRequest $request): bool`

- [ ] **Step 1: Write the failing tests** — append inside the test class:

```php
    public function test_a_user_can_request_and_a_second_pending_request_is_refused(): void
    {
        [, , $user] = $this->tenant('req-a');
        [, $target] = $this->tenant('req-b');
        $service = app(StationTransferService::class);

        $request = $service->request($user, ['to_school_id' => $target->id, 'reason' => 'Reassigned']);

        $this->assertSame('pending', $request->status);
        $this->assertSame($user->school_id, $request->from_school_id);
        $this->expectException(ValidationException::class);
        $service->request($user, ['to_school_id' => $target->id, 'reason' => 'Again']);
    }

    public function test_request_rules_master_same_school_and_inactive_destination(): void
    {
        [, $school, $user] = $this->tenant('rule-a');
        [, $closed] = $this->tenant('rule-b');
        $closed->update(['status' => 'inactive']);
        $service = app(StationTransferService::class);

        foreach ([
            [$this->master(), ['to_school_id' => $school->id, 'reason' => 'x']],
            [$user, ['to_school_id' => $school->id, 'reason' => 'x']],
            [$user, ['to_school_id' => $closed->id, 'reason' => 'x']],
            [$user, ['reason' => 'x']],
        ] as [$who, $data]) {
            try {
                $service->request($who, $data);
                $this->fail('Expected a validation error.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, StationTransferRequest::count());
    }

    public function test_approving_to_a_registered_school_moves_the_account_and_keeps_the_old_school_untouched(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('alpha');
        [$orgB, $schoolB] = $this->tenant('bravo');
        $this->staff($orgA, $schoolA, $user->name, ['position' => 'AO II', 'bac_role' => 'BAC Member']);
        $this->staff($orgA, $schoolA, 'Old Clerk');
        $this->staff($orgB, $schoolB, 'New Clerk');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $request = $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'Division order']), $master, 'Welcome');

        $user->refresh();
        $this->assertSame([$orgB->id, $schoolB->id], [$user->organization_id, $user->school_id]);
        $this->assertSame('school_admin', $user->role);
        $this->assertSame('approved', $request->status);
        $this->assertSame($master->id, $request->decided_by);
        $this->assertNull($request->confirmed_at);

        $old = SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolA->id)->where('name', $user->name)->first();
        $this->assertNotNull($old->ended_at);
        $this->assertSame('BAC Member', $old->bac_role);
        $new = SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->first();
        $this->assertNotNull($new);
        $this->assertNull($new->bac_role);
        $this->assertNotSame($old->employee_no, $new->employee_no);

        $this->actingAs($user);
        $names = SchoolStaff::pluck('name')->all();
        $this->assertContains('New Clerk', $names);
        $this->assertNotContains('Old Clerk', $names);
        $this->assertSame(2, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolA->id)->count());
    }

    public function test_approving_a_school_that_is_not_registered_creates_it(): void
    {
        [, , $user] = $this->tenant('src');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $request = $service->approve($service->request($user, ['proposed_school' => ['name' => 'Charlie Elementary School', 'division' => 'Div X'], 'reason' => 'New post']), $master);

        $school = School::withoutGlobalScopes()->where('name', 'Charlie Elementary School')->firstOrFail();
        $this->assertSame('active', $school->status);
        $this->assertStringStartsWith('SCH-', $school->code);
        $this->assertSame('Div X', $school->division);
        $this->assertSame($school->id, $request->fresh()->to_school_id);
        $this->assertSame($school->id, $user->fresh()->school_id);
        $this->assertSame($school->organization_id, $user->fresh()->organization_id);
        $this->assertSame('trial', Subscription::withoutGlobalScopes()->where('school_id', $school->id)->value('plan'));
    }

    public function test_approving_twice_or_for_an_inactive_user_changes_nothing(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('twice-a');
        [, $schoolB] = $this->tenant('twice-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $request = $service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']);
        $service->approve($request, $master);

        try {
            $service->approve($request, $master);
            $this->fail('Second approval must fail.');
        } catch (ValidationException) {
            $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->count());
        }

        [, $schoolC, $leaver] = $this->tenant('twice-c');
        $pending = $service->request($leaver, ['proposed_school' => ['name' => 'Never Created School'], 'reason' => 'x']);
        $leaver->update(['status' => 'inactive']);
        try {
            $service->approve($pending, $master);
            $this->fail('Inactive user must block approval.');
        } catch (ValidationException) {
            $this->assertSame($schoolC->id, $leaver->fresh()->school_id);
            $this->assertNull(School::withoutGlobalScopes()->where('name', 'Never Created School')->first());
        }
    }

    public function test_a_failure_after_the_school_is_created_rolls_everything_back(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('boom-a');
        $this->staff($orgA, $schoolA, $user->name);
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $request = $service->request($user, ['proposed_school' => ['name' => 'Rolled Back School'], 'reason' => 'x']);

        SchoolStaff::creating(fn () => throw new \RuntimeException('boom'));
        try {
            $service->approve($request, $master);
            $this->fail('Expected the forced failure.');
        } catch (\RuntimeException) {
            $this->assertNull(School::withoutGlobalScopes()->where('name', 'Rolled Back School')->first());
            $this->assertSame($schoolA->id, $user->fresh()->school_id);
            $this->assertNull(SchoolStaff::withoutGlobalScopes()->where('name', $user->name)->first()->ended_at);
            $this->assertSame('pending', $request->fresh()->status);
        } finally {
            SchoolStaff::clearBootedModels();
        }
    }

    public function test_a_user_without_an_employee_record_can_still_be_moved(): void
    {
        [, , $user] = $this->tenant('noemp-a');
        [, $schoolB] = $this->tenant('noemp-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);

        $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']), $master);

        $this->assertSame($schoolB->id, $user->fresh()->school_id);
        $this->assertSame(1, SchoolStaff::withoutGlobalScopes()->where('school_id', $schoolB->id)->where('name', $user->name)->count());
    }

    public function test_decline_cancel_and_the_last_admin_warning(): void
    {
        [, $schoolA, $admin] = $this->tenant('warn-a');
        [, , $viewer] = $this->tenant('warn-v', 'viewer');
        [, $schoolB] = $this->tenant('warn-b');
        $second = User::factory()->create(['organization_id' => $schoolA->organization_id, 'school_id' => $schoolA->id, 'role' => 'school_admin']);
        $master = $this->master();
        $service = app(StationTransferService::class);

        $withBackup = $service->request($admin, ['to_school_id' => $schoolB->id, 'reason' => 'x']);
        $this->assertFalse($service->losesLastAdmin($withBackup));
        $second->update(['status' => 'inactive']);
        $this->assertTrue($service->losesLastAdmin($withBackup));
        $this->assertFalse($service->losesLastAdmin($service->request($viewer, ['to_school_id' => $schoolB->id, 'reason' => 'x'])));

        $service->decline($withBackup, $master, 'Not now');
        $this->assertSame('declined', $withBackup->fresh()->status);
        $this->assertSame($schoolA->id, $admin->fresh()->school_id);

        $again = $service->request($admin, ['to_school_id' => $schoolB->id, 'reason' => 'y']);
        try {
            $service->cancel($again, $viewer);
            $this->fail('Only the owner may cancel.');
        } catch (ValidationException) {
            $service->cancel($again, $admin);
            $this->assertSame('cancelled', $again->fresh()->status);
        }
    }

    public function test_confirm_sets_the_timestamp_once(): void
    {
        [, , $user] = $this->tenant('conf-a');
        [, $schoolB] = $this->tenant('conf-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $service->approve($service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'x']), $master);

        $this->assertTrue($service->confirm($user->fresh()));
        $this->assertFalse($service->confirm($user->fresh()));
        $this->assertNotNull(StationTransferRequest::first()->confirmed_at);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=StationTransferTest`
Expected: FAIL (`StationTransferService` not found).

- [ ] **Step 3: Implement the service** `app/Services/StationTransferService.php`:

```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StationTransferRequest;
use App\Models\Subscription;
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
        } elseif (filled($data['proposed_school']['name'] ?? null)) {
            $proposed = array_intersect_key($data['proposed_school'], array_flip(self::SCHOOL_FIELDS));
        } else {
            $this->fail('to_school_id', 'Choose a destination school or enter the new school\'s details.');
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
                $this->fail('request', 'The user\'s account is not active. Reactivate it before approving.');
            }

            $target = $request->to_school_id
                ? School::withoutGlobalScopes()->findOrFail($request->to_school_id)
                : $this->createSchool($request->proposed_school ?? []);
            if ((int) $target->id === (int) $user->school_id) {
                $this->fail('request', 'The user is already at that school.');
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

    /** True when the requester is an admin and no other active admin would remain at the school they leave. */
    public function losesLastAdmin(StationTransferRequest $request): bool
    {
        $user = $request->user;
        if (! $user || $user->role !== 'school_admin') {
            return false;
        }

        return User::withoutGlobalScopes()
            ->where('school_id', $user->school_id)->where('role', 'school_admin')->where('id', '!=', $user->id)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->doesntExist();
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

        $school = School::create([...array_intersect_key($proposed, array_flip(self::SCHOOL_FIELDS)), 'organization_id' => $organization->id, 'code' => $code, 'status' => 'active']);

        Subscription::create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'plan' => 'trial',
            'billing_cycle' => 'monthly',
            'amount' => 0,
            'payment_status' => 'pending',
            'status' => 'trial',
            'starts_at' => now(),
            'subscription_end' => now()->addDays(30),
        ]);

        return $school;
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
```

- [ ] **Step 4: Run tests, fix until green, run Pint**

Run: `php artisan test --filter=StationTransferTest` → all PASS
Run: `vendor/bin/pint app/Services/StationTransferService.php tests/Feature/StationTransferTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Services/StationTransferService.php tests/Feature/StationTransferTest.php
git commit -m "feat: add station transfer service with transactional approval"
```

---

### Task 3: Middleware, routes and controller

**Files:**
- Create: `app/Http/Middleware/EnsureStationConfirmed.php`
- Create: `app/Http/Controllers/StationTransferController.php`
- Modify: `bootstrap/app.php`, `routes/web.php`
- Test: `tests/Feature/StationTransferTest.php`

**Interfaces:**
- Consumes: `StationTransferService` (Task 2).
- Produces routes (all `auth` middleware): `station-transfer` (GET), `station-transfer.store` (POST), `station-transfer.cancel` (POST `{transfer}`), `station.confirm` (GET), `station.confirm.store` (POST), `transfer-requests` (GET, master), `transfer-requests.approve` / `transfer-requests.decline` (POST `{transfer}`, master). Views used (created in Task 4): `station-transfer`, `station-confirm`, `transfer-requests`.

- [ ] **Step 1: Write the failing tests** — append to the test class:

```php
    public function test_a_user_submits_a_request_over_http(): void
    {
        [, , $user] = $this->tenant('http-a');
        [, $target] = $this->tenant('http-b');

        $this->actingAs($user)->post(route('station-transfer.store'), ['destination' => 'registered', 'to_school_id' => $target->id, 'reason' => 'Reassigned'])
            ->assertRedirect(route('station-transfer'));
        $this->assertSame('pending', StationTransferRequest::first()->status);

        $this->post(route('station-transfer.store'), ['destination' => 'registered', 'to_school_id' => $target->id, 'reason' => 'Again'])->assertSessionHasErrors('reason');
        $this->post(route('station-transfer.store'), ['destination' => 'new', 'new_school' => ['name' => ''], 'reason' => 'x'])->assertSessionHasErrors('new_school.name');
        $this->post(route('station-transfer.cancel', StationTransferRequest::first()))->assertRedirect(route('station-transfer'));
        $this->assertSame('cancelled', StationTransferRequest::first()->status);
    }

    public function test_only_the_master_can_see_and_decide_requests(): void
    {
        [, , $user] = $this->tenant('perm-a');
        [, $target] = $this->tenant('perm-b');
        $request = app(StationTransferService::class)->request($user, ['to_school_id' => $target->id, 'reason' => 'x']);

        $this->actingAs($user)->get(route('transfer-requests'))->assertForbidden();
        $this->post(route('transfer-requests.approve', $request))->assertForbidden();
        $this->post(route('transfer-requests.decline', $request))->assertForbidden();

        $this->actingAs($this->master())->post(route('transfer-requests.approve', $request), ['decision_note' => 'ok'])->assertRedirect(route('transfer-requests'));
        $this->assertSame($target->id, $user->fresh()->school_id);
    }

    public function test_an_unconfirmed_user_is_sent_to_the_confirmation_page_but_can_log_out(): void
    {
        [, , $user] = $this->tenant('mid-a');
        [, $target] = $this->tenant('mid-b');
        $master = $this->master();
        $this->actingAs($master);
        $service = app(StationTransferService::class);
        $service->approve($service->request($user, ['to_school_id' => $target->id, 'reason' => 'x']), $master);

        $this->actingAs($user->fresh());
        $this->get(route('home'))->assertRedirect(route('station.confirm'));
        $this->get(route('procurement'))->assertRedirect(route('station.confirm'));
        $this->get(route('station.confirm'))->assertOk()->assertSee($target->name);
        $this->post(route('logout'))->assertRedirect();

        $this->actingAs($user->fresh())->post(route('station.confirm.store'))->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();
    }
```

- [ ] **Step 2: Run to verify they fail** — `php artisan test --filter=StationTransferTest` → FAIL (routes missing).

- [ ] **Step 3: Middleware** `app/Http/Middleware/EnsureStationConfirmed.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\StationTransferRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === 'master_user' || $request->routeIs('station.confirm', 'station.confirm.store', 'logout')) {
            return $next($request);
        }

        $unconfirmed = StationTransferRequest::where('user_id', $user->id)->where('status', 'approved')->whereNull('confirmed_at')->exists();

        return $unconfirmed ? redirect()->route('station.confirm') : $next($request);
    }
}
```

In `bootstrap/app.php` add `use App\Http\Middleware\EnsureStationConfirmed;` and inside `withMiddleware` after the alias call: `$middleware->appendToGroup('web', EnsureStationConfirmed::class);`

- [ ] **Step 4: Controller** `app/Http/Controllers/StationTransferController.php`:

```php
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
            'requests' => StationTransferRequest::with(['fromSchool', 'toSchool', 'decider'])->where('user_id', $user->id)->latest('id')->get(),
            'schools' => School::withoutGlobalScopes()->where('status', 'active')->where('id', '!=', $user->school_id)->orderBy('name')->get(['id', 'name', 'division', 'district']),
            'station' => $user->school()->withoutGlobalScopes()->first(),
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

        return $transfer ? view('station-confirm', ['transfer' => $transfer, 'user' => $request->user()]) : redirect()->route('home');
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
            'pending' => StationTransferRequest::with($with)->where('status', 'pending')->oldest('id')->get(),
            'history' => StationTransferRequest::with($with)->where('status', '!=', 'pending')->latest('id')->limit(50)->get(),
            'transfers' => $this->transfers,
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
```

- [ ] **Step 5: Routes** — in `routes/web.php` add `use App\Http\Controllers\StationTransferController;` with the other imports, and before the `Route::post('/logout'...)` line (still inside the file's auth region, but in its own group so `subscription.writes` does not apply) add:

```php
Route::middleware('auth')->group(function () {
    Route::get('/station-transfer', [StationTransferController::class, 'index'])->name('station-transfer');
    Route::post('/station-transfer', [StationTransferController::class, 'store'])->name('station-transfer.store');
    Route::post('/station-transfer/{transfer}/cancel', [StationTransferController::class, 'cancel'])->name('station-transfer.cancel');
    Route::get('/station-confirm', [StationTransferController::class, 'confirmShow'])->name('station.confirm');
    Route::post('/station-confirm', [StationTransferController::class, 'confirmStore'])->name('station.confirm.store');
    Route::get('/transfer-requests', [StationTransferController::class, 'queue'])->name('transfer-requests');
    Route::post('/transfer-requests/{transfer}/approve', [StationTransferController::class, 'approve'])->name('transfer-requests.approve');
    Route::post('/transfer-requests/{transfer}/decline', [StationTransferController::class, 'decline'])->name('transfer-requests.decline');
});
```

Place this group after the closing `});` of the existing `['auth','subscription.writes']` group (the `/logout` and `/generate` routes sit inside that group; do not move them).

- [ ] **Step 6: Create minimal placeholder-free views now** so the tests render: Task 4 supplies the final markup. For this task create the three view files with only `@extends('layouts.procurement')` + `@section('content')` + the name of the page and the data the tests assert (`{{ $transfer->toSchool->name }}` in `station-confirm`). Task 4 replaces their content entirely.

```blade
{{-- resources/views/station-confirm.blade.php --}}
@extends('layouts.procurement')
@section('title', 'Confirm station') @section('page-title', 'Confirm station')
@section('hide-module-tabs', '1')
@section('content')
<p>{{ $transfer->toSchool->name }}</p>
@endsection
```
`station-transfer.blade.php` and `transfer-requests.blade.php`: same shape with titles "Station transfer" / "Transfer requests" and `<p>placeholder</p>` removed in Task 4.

- [ ] **Step 7: Run tests** — `php artisan test --filter=StationTransferTest` → PASS. Run Pint on the new PHP files.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureStationConfirmed.php app/Http/Controllers/StationTransferController.php bootstrap/app.php routes/web.php resources/views/station-confirm.blade.php resources/views/station-transfer.blade.php resources/views/transfer-requests.blade.php tests/Feature/StationTransferTest.php
git commit -m "feat: add station transfer routes, controller and confirmation middleware"
```

---

### Task 4: The three screens and the menu links

**Files:**
- Modify (replace content): `resources/views/station-transfer.blade.php`, `resources/views/transfer-requests.blade.php`, `resources/views/station-confirm.blade.php`
- Modify: `resources/views/layouts/procurement.blade.php` (user menu)
- Test: `tests/Feature/StationTransferTest.php`

**Interfaces:** Consumes the controller view data from Task 3 (`requests`, `schools`, `station`; `pending`, `history`, `transfers`; `transfer`, `user`).

- [ ] **Step 1: Write the failing tests** — append:

```php
    public function test_the_screens_show_the_right_content(): void
    {
        [$orgA, $schoolA, $user] = $this->tenant('ui-a');
        [, $schoolB] = $this->tenant('ui-b');
        $service = app(StationTransferService::class);
        $master = $this->master();

        $this->actingAs($user)->get(route('station-transfer'))->assertOk()
            ->assertSee('Request a station transfer')->assertSee($schoolB->name)->assertSee('My school isn')->assertDontSee($schoolA->name.'</option>', false);

        $request = $service->request($user, ['to_school_id' => $schoolB->id, 'reason' => 'Division order']);
        $this->get(route('station-transfer'))->assertSee('Pending')->assertSee('Division order')->assertSee('Cancel request');

        $this->actingAs($master)->get(route('transfer-requests'))->assertOk()
            ->assertSee($user->name)->assertSee($schoolB->name)->assertSee('no admin')->assertSee('Approve')->assertSee('Decline');

        $service->approve($request, $master, 'Welcome aboard');
        $this->actingAs($master)->get(route('transfer-requests'))->assertSee('Approved')->assertSee('Welcome aboard');
        $this->actingAs($user->fresh())->get(route('station.confirm'))->assertOk()->assertSee('Confirm your new station')->assertSee($schoolA->name)->assertSee($schoolB->name)->assertSee('School Admin');
    }

    public function test_the_user_menu_links_to_the_right_page(): void
    {
        [, , $user] = $this->tenant('menu-a');
        $this->actingAs($user)->get(route('procurement'))->assertSee(route('station-transfer'), false)->assertDontSee(route('transfer-requests'), false);
        $this->actingAs($this->master())->get(route('procurement'))->assertSee(route('transfer-requests'), false)->assertDontSee(route('station-transfer'), false);
    }
```

- [ ] **Step 2: Run to verify they fail** — FAIL (placeholder views).

- [ ] **Step 3: `resources/views/station-transfer.blade.php`** (replace the file):

```blade
@extends('layouts.procurement')
@section('title', 'Station transfer') @section('page-title', 'Station transfer')
@section('content')
@php $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20'; $tones = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-secondary/10 text-secondary', 'declined' => 'bg-error/10 text-error', 'cancelled' => 'bg-surface-high text-on-surface-variant']; @endphp
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Official Station</p>
    <h1 class="mt-2 text-3xl font-bold">Station transfer</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Your current station is <strong>{{ $station?->name ?? '—' }}</strong>. If you are reassigned, ask the master user to move you. You keep your account, username and role; you will work in the new school's data and the school you leave keeps its own.</p>
</header>
<div class="grid items-start gap-5 xl:grid-cols-[1fr_360px]">
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
        <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">My requests</h2></div>
        <ul class="divide-y divide-outline-variant/30">
            @forelse($requests as $item)
                <li class="px-5 py-3.5 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ $item->fromSchool?->name ?? '—' }} <span class="text-on-surface-variant">→</span> {{ $item->destinationName() }}@if(! $item->to_school_id) <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">New school</span>@endif</p>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tones[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant">Requested {{ $item->requested_at?->format('M d, Y') }} · {{ $item->reason }}</p>
                    @if($item->decision_note)<p class="mt-1 text-xs"><strong>Master note:</strong> {{ $item->decision_note }}</p>@endif
                    @if($item->status === 'pending')
                        <form method="POST" action="{{ route('station-transfer.cancel', $item) }}" class="mt-2">@csrf<button class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold hover:bg-surface-low">Cancel request</button></form>
                    @endif
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-on-surface-variant">You have not sent a transfer request.</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-xl border border-outline-variant/60 bg-white p-5">
        <h2 class="font-bold">Request a station transfer</h2>
        @if($errors->any())<div class="mt-3 rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-xs text-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('station-transfer.store') }}" class="mt-3 space-y-3 text-xs font-bold" id="transfer-form">
            @csrf
            <label class="block">Destination<select name="destination" id="transfer-destination" class="{{ $field }}"><option value="registered" @selected(old('destination', 'registered') === 'registered')>A registered school</option><option value="new" @selected(old('destination') === 'new')>My school isn't listed</option></select></label>
            <label class="block" data-when="registered">School<select name="to_school_id" class="{{ $field }}"><option value="">Choose a school</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) old('to_school_id') === $school->id)>{{ $school->name }}</option>@endforeach</select></label>
            <div class="space-y-3" data-when="new">
                <label class="block">School name<input name="new_school[name]" value="{{ old('new_school.name') }}" class="{{ $field }}"></label>
                <label class="block">School type<input name="new_school[school_type]" value="{{ old('new_school.school_type') }}" class="{{ $field }}"></label>
                <div class="grid grid-cols-2 gap-3"><label class="block">Region<input name="new_school[region]" value="{{ old('new_school.region') }}" class="{{ $field }}"></label><label class="block">Division<input name="new_school[division]" value="{{ old('new_school.division') }}" class="{{ $field }}"></label></div>
                <label class="block">District<input name="new_school[district]" value="{{ old('new_school.district') }}" class="{{ $field }}"></label>
                <label class="block">Address<input name="new_school[address]" value="{{ old('new_school.address') }}" class="{{ $field }}"></label>
                <div class="grid grid-cols-2 gap-3"><label class="block">School email<input type="email" name="new_school[contact_email]" value="{{ old('new_school.contact_email') }}" class="{{ $field }}"></label><label class="block">Contact number<input name="new_school[contact_number]" value="{{ old('new_school.contact_number') }}" class="{{ $field }}"></label></div>
                <p class="rounded-lg bg-surface-low px-3 py-2 font-normal text-on-surface-variant">The master user registers this school when approving your request.</p>
            </div>
            <label class="block">Reason<textarea name="reason" rows="3" required class="{{ $field }}">{{ old('reason') }}</textarea></label>
            <button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-container">Send request</button>
        </form>
    </section>
</div>
<script>
    (() => {
        const select = document.getElementById('transfer-destination');
        const sync = () => document.querySelectorAll('#transfer-form [data-when]').forEach((box) => { box.hidden = box.dataset.when !== select.value; });
        select.addEventListener('change', sync); sync();
    })();
</script>
@endsection
```

- [ ] **Step 4: `resources/views/transfer-requests.blade.php`** (replace the file):

```blade
@extends('layouts.procurement')
@section('title', 'Transfer requests') @section('page-title', 'Transfer requests')
@section('content')
@php $field = 'w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action'; $tones = ['approved' => 'bg-secondary/10 text-secondary', 'declined' => 'bg-error/10 text-error', 'cancelled' => 'bg-surface-high text-on-surface-variant']; @endphp
<header class="mb-6">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-action">Master account</p>
    <h1 class="mt-2 text-3xl font-bold">Transfer requests</h1>
    <p class="mt-2 text-sm text-on-surface-variant">Users who were reassigned and ask to move to another Official Station. Approving moves the account; the school they leave keeps its data.</p>
</header>
<section class="mb-6 overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">Pending <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $pending->count() }}</span></h2></div>
    <ul class="divide-y divide-outline-variant/30">
        @forelse($pending as $item)
            <li class="px-5 py-4 text-sm">
                <p class="font-semibold">{{ $item->user?->name }} <span class="font-normal text-on-surface-variant">· {{ str($item->user?->role)->replace('_', ' ')->title() }}</span></p>
                <p class="mt-1">{{ $item->fromSchool?->name ?? '—' }} <span class="text-on-surface-variant">→</span> <strong>{{ $item->destinationName() }}</strong>@if(! $item->to_school_id) <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">New school</span>@endif</p>
                <p class="mt-1 text-xs text-on-surface-variant">Requested {{ $item->requested_at?->format('M d, Y') }} · {{ $item->reason }}</p>
                @if($transfers->losesLastAdmin($item))<p class="mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900"><strong>Warning:</strong> {{ $item->fromSchool?->name }} will have no admin after this transfer. It keeps all its data; assign another admin later.</p>@endif
                @if(! $item->to_school_id)<p class="mt-2 rounded-lg bg-surface-low px-3 py-2 text-xs text-on-surface-variant">Approving registers <strong>{{ $item->proposed_school['name'] ?? '' }}</strong> as a new school with a 30-day trial.</p>@endif
                <p class="mt-2 text-xs text-on-surface-variant">On approval: their employee record at {{ $item->fromSchool?->name }} is ended (kept as history), a new record with no roles is created at the new school, and their system role stays <strong>{{ str($item->user?->role)->replace('_', ' ')->title() }}</strong>.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('transfer-requests.approve', $item) }}" class="flex flex-1 flex-wrap gap-2">@csrf<input name="decision_note" placeholder="Note (optional)" class="{{ $field }} min-w-[180px] flex-1"><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Approve</button></form>
                    <form method="POST" action="{{ route('transfer-requests.decline', $item) }}">@csrf<button class="rounded-lg border border-error/40 px-4 py-2 text-xs font-bold text-error hover:bg-error/10">Decline</button></form>
                </div>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-on-surface-variant">No pending transfer requests.</li>
        @endforelse
    </ul>
</section>
<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="border-b border-outline-variant/50 px-5 py-4"><h2 class="font-bold">History</h2></div>
    <ul class="divide-y divide-outline-variant/30 text-sm">
        @forelse($history as $item)
            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                <div><p class="font-semibold">{{ $item->user?->name }}: {{ $item->fromSchool?->name ?? '—' }} → {{ $item->destinationName() }}</p><p class="text-xs text-on-surface-variant">{{ $item->decided_at?->format('M d, Y') ?? $item->requested_at?->format('M d, Y') }}@if($item->decider) · by {{ $item->decider->name }}@endif @if($item->decision_note) · {{ $item->decision_note }}@endif</p></div>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $tones[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span>
            </li>
        @empty
            <li class="px-5 py-6 text-center text-sm text-on-surface-variant">Nothing decided yet.</li>
        @endforelse
    </ul>
</section>
@endsection
```

- [ ] **Step 5: `resources/views/station-confirm.blade.php`** (replace the file):

```blade
@extends('layouts.procurement')
@section('title', 'Confirm station') @section('page-title', 'Confirm station')
@section('hide-module-tabs', '1')
@section('content')
<div class="mx-auto mt-6 max-w-xl rounded-xl border border-outline-variant/60 bg-white p-6 text-center">
    <span class="material-symbols-outlined text-[40px] text-secondary" aria-hidden="true">swap_horiz</span>
    <h1 class="mt-2 text-2xl font-bold">Confirm your new station</h1>
    <p class="mt-2 text-sm text-on-surface-variant">The master user approved your transfer. From now on you work in the new school's data.</p>
    <div class="mt-5 flex items-center justify-center gap-3 text-sm">
        <div class="rounded-lg bg-surface-low px-4 py-3"><p class="text-[11px] font-bold uppercase text-on-surface-variant">From</p><p class="font-bold">{{ $transfer->fromSchool?->name }}</p></div>
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        <div class="rounded-lg bg-primary/10 px-4 py-3"><p class="text-[11px] font-bold uppercase text-primary">To</p><p class="font-bold">{{ $transfer->toSchool?->name }}</p></div>
    </div>
    <p class="mt-4 text-xs text-on-surface-variant">Your system role stays <strong>{{ str($user->role)->replace('_', ' ')->title() }}</strong>. Your employee roles at the new school are set by the school admin or master user.</p>
    <form method="POST" action="{{ route('station.confirm.store') }}" class="mt-5">@csrf<button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-container">Confirm and continue</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-semibold hover:bg-surface-low">Log out</button></form>
</div>
@endsection
```

- [ ] **Step 6: User menu links** — in `resources/views/layouts/procurement.blade.php`, directly after the `School Settings` `<a role="menuitem" ...>` line (line ~66) add, on their own lines:

```blade
                        @if($menuUser?->role === 'master_user')
                            @php $pendingTransfers = \App\Models\StationTransferRequest::where('status', 'pending')->count(); @endphp
                            <a role="menuitem" href="{{ route('transfer-requests') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">swap_horiz</span>Transfer requests @if($pendingTransfers)<span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $pendingTransfers }}</span>@endif</a>
                        @else
                            <a role="menuitem" href="{{ route('station-transfer') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 font-semibold hover:bg-surface-low"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">swap_horiz</span>Station transfer</a>
                        @endif
```

(Keep a space or newline between `@endif` and the next directive; glued directives such as `@endif@if(` do not compile in this project.)

- [ ] **Step 7: Run tests** — `php artisan test --filter=StationTransferTest` → PASS.

- [ ] **Step 8: Browser check** — start the app with the project's `.claude/launch.json` server, sign in as a school user, open `/station-transfer`, switch the destination dropdown between "A registered school" and "My school isn't listed" and confirm the school fields show/hide and nothing overflows at ~375px width.

- [ ] **Step 9: Commit**

```bash
git add resources/views/station-transfer.blade.php resources/views/transfer-requests.blade.php resources/views/station-confirm.blade.php resources/views/layouts/procurement.blade.php tests/Feature/StationTransferTest.php
git commit -m "feat: add station transfer, master queue and confirmation screens"
```

---

### Task 5: Docs and final targeted verification

**Files:**
- Modify: `docs/specs.md`, `docs/superpowers/specs/2026-10-09-station-transfer-design.md`

- [ ] **Step 1: Update the design spec** — in `docs/superpowers/specs/2026-10-09-station-transfer-design.md` replace the `users.station_confirmed_at` bullet and the "copy the employee number" text with the deviations 1–2 from the top of this plan, add a "Deviations" note for items 3–7, and change "Transfer requests tab" to "Transfer requests page (`/transfer-requests`)".

- [ ] **Step 2: Add a short "Station transfer" section to `docs/specs.md`** (after the last numbered section; match its heading style): who can request, what approval does (account moves, old employee record ended, new one created without roles, role kept), the confirmation step, the routes `station-transfer`, `station.confirm`, `transfer-requests`, and that school data is never copied.

- [ ] **Step 3: Run the affected suites**

Run: `php artisan test --filter="StationTransferTest|SchoolSettingsTest|OrganizationIsolationTest|SaasFoundationTest"`
Expected: all PASS.

Run: `php artisan test`
Expected: the full suite passes (it was 103 tests before this feature).

- [ ] **Step 4: Pint the changed files** — `vendor/bin/pint --dirty`

- [ ] **Step 5: Commit**

```bash
git add docs/specs.md docs/superpowers/specs/2026-10-09-station-transfer-design.md
git commit -m "docs: document station transfer"
```
