<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolManagementService;
use App\Services\SchoolTakeoverService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Users tab of User Management: every school account in one place. The master and every Sub-master with the
 * "Users" access can look, add, edit, reset passwords and set accounts inactive. Master accounts never appear here;
 * they are managed in the Master User tab.
 */
class UserDirectoryController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAnyMaster(), 403);
        if ($request->query('tab') === 'master-user' || ! $request->user()->hasAccess('users')) {
            return view('user-management', ['tab' => 'master-user']);
        }

        $filtered = $this->directory()
            ->when(trim((string) $request->query('q')) !== '', function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('username', 'like', $term)->orWhere('email', 'like', $term)->orWhere('user_code', 'like', $term));
            })
            ->when($request->query('role'), fn (Builder $query, $role) => $query->where('role', $role))
            ->when($request->query('status'), function (Builder $query, $status) {
                $status === 'active'
                    ? $query->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 'active'))
                    : $query->where('status', $status);
            })
            ->when($request->query('school_id'), fn (Builder $query, $school) => $query->where('school_id', $school));

        $roleCounts = $this->directory()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('user-management', [
            'tab' => 'users',
            'users' => $filtered->with('school:id,name')->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString(),
            'metrics' => [
                'total' => $this->directory()->count(),
                'active' => $this->directory()->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 'active'))->count(),
                'pending' => $this->directory()->where('status', 'pending')->count(),
                'administrators' => $this->directory()->where('role', 'school_admin')->count(),
            ],
            'roleCounts' => $roleCounts,
            'roles' => User::ROLES,
            'schools' => School::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']),
            'vacantSchools' => app(SchoolTakeoverService::class)->vacantSchools(),
            'recentActivity' => AuditLog::withoutGlobalScopes()->with('user:id,name')->latest('id')->limit(6)->get(),
        ]);
    }

    public function show(Request $request, int $user): View
    {
        $this->authorizeUsers($request);
        $user = $this->schoolUser($user);

        return view('user-management', [
            'tab' => 'user',
            'account' => $user->load('school:id,name,division,district'),
            'subscription' => $user->activeSubscription(),
            'roles' => User::ROLES,
            'reasons' => SchoolManagementService::REASONS,
            'history' => AuditLog::withoutGlobalScopes()->with('user:id,name')->where('auditable_type', User::class)->where('auditable_id', $user->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUsers($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! app(SchoolTakeoverService::class)->vacantSchools()->contains('id', (int) $value)) {
                    $fail('Choose a school that is open for a new user. A school can only have one.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], $this->messages());

        $school = School::withoutGlobalScopes()->findOrFail($data['school_id']);

        $user = DB::transaction(function () use ($data, $school, $request) {
            $user = new User(['name' => $data['name'], 'username' => $data['username'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'position' => $data['position'] ?? null, 'password' => $data['password']]);
            $user->forceFill(['role' => $data['role'], 'status' => 'active', 'organization_id' => $school->organization_id, 'school_id' => $school->id, 'password_changed_at' => now()])->save();
            SchoolStaff::create(['organization_id' => $school->organization_id, 'school_id' => $school->id, 'name' => $user->name, 'position' => $user->position]);
            Subscription::create([
                'organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'trial', 'billing_cycle' => 'monthly',
                'amount' => 0, 'payment_status' => 'pending', 'status' => 'trial', 'starts_at' => now(), 'subscription_end' => now()->addDays(30),
            ]);
            $this->audit($request, $user, 'user_created', ['role' => $user->role]);

            return $user;
        });

        return redirect()->route('user-management.users.show', $user->id)->with('success', $user->name.' was added to '.$school->name.'. Give them the temporary password in person.');
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $this->authorizeUsers($request);
        $user = $this->schoolUser($user);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ], $this->messages());

        $before = $user->only(['email', 'phone', 'position', 'role']);
        $user->forceFill(['email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'position' => $data['position'] ?? null, 'role' => $data['role']])->save();
        $this->audit($request, $user, 'user_updated', ['before' => $before, 'after' => $user->only(['email', 'phone', 'position', 'role'])]);

        return $this->back($user, $user->name.' was updated.');
    }

    public function password(Request $request, int $user): RedirectResponse
    {
        $this->authorizeUsers($request);
        $user = $this->schoolUser($user);
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']], $this->messages());

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $this->audit($request, $user, 'user_password_reset');

        return $this->back($user, 'The password of '.$user->name.' was reset.');
    }

    public function deactivate(Request $request, int $user): RedirectResponse
    {
        $this->authorizeUsers($request);
        $user = $this->schoolUser($user);
        $data = $request->validate([
            'reason' => ['required', Rule::in(SchoolManagementService::REASONS)],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        app(SchoolManagementService::class)->deactivateUser($user, $request->user(), $data['reason'], $data['effective_date'], $data['note'] ?? null);

        return $this->back($user, $user->name.' was set inactive.');
    }

    public function reactivate(Request $request, int $user): RedirectResponse
    {
        $this->authorizeUsers($request);
        $user = $this->schoolUser($user);

        app(SchoolManagementService::class)->reactivateUser($user, $request->user());

        return $this->back($user, $user->name.' is active again.');
    }

    public function auditLog(Request $request): View
    {
        $this->authorizeUsers($request);

        $logs = AuditLog::withoutGlobalScopes()->with(['user:id,name', 'school:id,name'])
            ->when(trim((string) $request->query('q')) !== '', fn (Builder $query) => $query->where('action', 'like', '%'.str_replace(' ', '_', trim((string) $request->query('q'))).'%'))
            ->when($request->query('school_id'), fn (Builder $query, $school) => $query->where('school_id', $school))
            ->latest('id')->paginate(25)->withQueryString();

        return view('user-management', [
            'tab' => 'audit',
            'logs' => $logs,
            'schools' => School::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function authorizeUsers(Request $request): void
    {
        abort_unless($request->user()?->isAnyMaster() && $request->user()->hasAccess('users'), 403);
    }

    /** Every school account, never a master or Sub-master. Not narrowed by the signed-in user's organization. */
    private function directory(): Builder
    {
        return User::withoutGlobalScopes()->whereNotIn('role', User::MASTER_ROLES);
    }

    /** Looked up after the permission check, so nobody but a master learns whether an id exists. */
    private function schoolUser(int $id): User
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);
        abort_if($user->isAnyMaster(), 404);

        return $user;
    }

    /** @param  array<string, mixed>  $metadata */
    private function audit(Request $request, User $target, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'school_id' => $target->school_id, 'action' => $action,
            'auditable_type' => User::class, 'auditable_id' => $target->id, 'metadata' => $metadata ?: null,
        ]);
    }

    private function back(User $user, string $message): RedirectResponse
    {
        return redirect()->route('user-management.users.show', $user->id)->with('success', $message);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'username.regex' => 'The username can only have letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already used by another account.',
            'email.unique' => 'That e-mail address is already used by another account.',
            'password.min' => 'The password needs at least 12 characters.',
            'role.in' => 'Choose one of the school roles.',
        ];
    }
}
