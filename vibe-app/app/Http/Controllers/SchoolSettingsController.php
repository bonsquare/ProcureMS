<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StaffRoleOption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The people side of School Settings: the system users of a school, its employees and the roles they hold.
 */
class SchoolSettingsController extends Controller
{
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->refuseMasterTargets($user);
        $this->refuseOtherAccounts($request, $user);
        $school = $this->school($request, $user->school_id);
        $isMaster = $request->user()->seesAllSchools();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => [$isMaster ? 'required' : 'nullable', Rule::in($this->assignableRoles($request, $user))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // The full name and the username are the account's identity in the system and never change; only the master user changes a role.
        if (! $isMaster) {
            unset($data['role']);
        }

        // Nobody can lock themselves out of the account they are working in.
        if ($user->is($request->user())) {
            $data['status'] = 'active';
            unset($data['role']);
        }

        $user->update($data);
        $this->audit($school, 'updated_system_user', $user);

        return $this->back($school, 'users', $user->name.' updated.');
    }

    public function changePassword(Request $request, User $user): RedirectResponse
    {
        $this->refuseMasterTargets($user);
        $this->refuseOtherAccounts($request, $user);
        $school = $this->school($request, $user->school_id);
        $rules = ['password' => ['required', 'string', 'min:8', 'confirmed']];
        if ($user->is($request->user())) {
            $rules['current_password'] = ['required', 'current_password'];
        }
        $data = $request->validate($rules);

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $this->audit($school, 'changed_system_user_password', $user);

        return $this->back($school, 'users', 'Password changed for '.$user->name.'.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $school = $this->school($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'array'],
            'roles.*.*' => ['string', 'max:100'],
        ]);

        $staff = new SchoolStaff(['name' => $data['name'], 'position' => $data['position'] ?? null, 'is_active' => $data['is_active'] ?? true, 'school_id' => $school->id, 'organization_id' => $school->organization_id]);
        $this->applyRoles($staff, $data['roles'] ?? []);
        $staff->save();

        return $this->back($school, 'staff', $staff->name.' added as employee '.$staff->employee_no.'. No login account was created.');
    }

    public function updateStaff(Request $request, SchoolStaff $staff): RedirectResponse
    {
        $school = $this->school($request, $staff->school_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'array'],
            'roles.*.*' => ['string', 'max:100'],
        ]);

        $staff->name = $data['name'];
        $staff->position = $data['position'] ?? null;
        $staff->is_active = $data['is_active'] ?? $staff->is_active;
        $this->applyRoles($staff, $data['roles'] ?? []);
        $staff->save();

        return $this->back($school, 'staff', $staff->name.' saved.');
    }

    public function destroyStaff(Request $request, SchoolStaff $staff): RedirectResponse
    {
        $school = $this->school($request, $staff->school_id);
        $name = $staff->name;
        $staff->delete();

        return $this->back($school, 'staff', $name.' removed from the employee list.');
    }

    public function storeRole(Request $request): RedirectResponse|JsonResponse
    {
        $school = $this->school($request);
        $data = $request->validate([
            'role_group' => ['required', Rule::in(array_keys(SchoolStaff::ROLE_GROUPS))],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $name = trim(str_replace(',', ' ', $data['name']));
        $existing = collect(SchoolStaff::DEFAULT_ROLES[$data['role_group']])
            ->merge(StaffRoleOption::where('role_group', $data['role_group'])->where('organization_id', $school->organization_id)->pluck('name'));
        if ($existing->contains(fn ($role) => mb_strtolower($role) === mb_strtolower($name))) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['name' => '"'.$name.'" is already a '.SchoolStaff::ROLE_GROUPS[$data['role_group']].' role.']);
            }

            return $this->back($school, 'staff', '"'.$name.'" is already a '.SchoolStaff::ROLE_GROUPS[$data['role_group']].' role.');
        }

        StaffRoleOption::create(['organization_id' => $school->organization_id, 'role_group' => $data['role_group'], 'name' => $name]);

        // The employee dialogs add a role in the background so what was typed in the form is kept.
        if ($request->expectsJson()) {
            return response()->json(['added' => true, 'role_group' => $data['role_group'], 'name' => $name]);
        }

        return $this->back($school, 'staff', 'Role "'.$name.'" added to '.SchoolStaff::ROLE_GROUPS[$data['role_group']].' roles.');
    }

    /** The school being edited, which the signed-in user must be allowed to manage. */
    /** The master and the Sub-masters are managed only in the Master User tab, never through a school's user list. */
    private function refuseMasterTargets(User $user): void
    {
        abort_if(in_array($user->role, User::MASTER_ROLES, true), 403, 'Master accounts are managed in the Master User tab.');
    }

    /** A school's own user can change only their own account here; other accounts are view only. The master and Sub-masters manage all. */
    private function refuseOtherAccounts(Request $request, User $user): void
    {
        abort_unless($request->user()->seesAllSchools() || $user->is($request->user()), 403, 'You can only change your own account.');
    }

    private function school(Request $request, ?int $schoolId = null): School
    {
        $schoolId ??= (int) $request->input('school_id');
        $user = $request->user();
        abort_unless($schoolId, 422, 'Choose a school first.');
        if (! $user->seesAllSchools()) {
            abort_unless((int) $user->school_id === $schoolId, 404);
        }

        return School::findOrFail($schoolId);
    }

    /** @return array<int, string> */
    private function assignableRoles(Request $request, ?User $target = null): array
    {
        $roles = array_keys(User::ROLES);
        if (! $request->user()->seesAllSchools() && ! ($target && $target->role === 'school_admin')) {
            $roles = array_values(array_diff($roles, ['school_admin']));
        }

        return $roles;
    }

    /** @param array<string, array<int, string>> $roles */
    private function applyRoles(SchoolStaff $staff, array $roles): void
    {
        foreach (array_keys(SchoolStaff::ROLE_GROUPS) as $group) {
            $staff->setRoles($group, $roles[$group] ?? []);
        }
    }

    private function audit(School $school, string $action, User $user): void
    {
        AuditLog::create(['user_id' => request()->user()?->id, 'school_id' => $school->id, 'action' => $action, 'auditable_type' => User::class, 'auditable_id' => $user->id]);
    }

    private function back(School $school, string $tab, string $message): RedirectResponse
    {
        return redirect()->route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $school->id, 'tab' => $tab])->with('success', $message);
    }
}
