<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\StaffRoleOption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The people side of School Settings: the system users of a school, its employees and the roles they hold.
 */
class SchoolSettingsController extends Controller
{
    public function storeUser(Request $request): RedirectResponse
    {
        $school = $this->school($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => [$request->user()->role === 'master_user' ? 'required' : 'nullable', Rule::in($this->assignableRoles($request))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        // Roles are the master user's decision; a school's own admin adds people with the least access.
        $data['role'] = $request->user()->role === 'master_user' ? $data['role'] : 'viewer';

        $user = User::create([
            ...$data,
            'username' => strtolower($data['username']),
            'organization_id' => $school->organization_id,
            'school_id' => $school->id,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);
        $this->audit($school, 'created_system_user', $user);

        return $this->back($school, 'users', 'System user '.$user->name.' added ('.$user->user_code.').');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $school = $this->school($request, $user->school_id);
        $isMaster = $request->user()->role === 'master_user';
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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'array'],
            'roles.*.*' => ['string', 'max:100'],
        ]);

        $staff = new SchoolStaff(['name' => $data['name'], 'position' => $data['position'] ?? null, 'school_id' => $school->id, 'organization_id' => $school->organization_id]);
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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'array'],
            'roles.*.*' => ['string', 'max:100'],
        ]);

        $staff->name = $data['name'];
        $staff->position = $data['position'] ?? null;
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

    public function storeRole(Request $request): RedirectResponse
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
            return $this->back($school, 'staff', '"'.$name.'" is already a '.SchoolStaff::ROLE_GROUPS[$data['role_group']].' role.');
        }

        StaffRoleOption::create(['organization_id' => $school->organization_id, 'role_group' => $data['role_group'], 'name' => $name]);

        return $this->back($school, 'staff', 'Role "'.$name.'" added to '.SchoolStaff::ROLE_GROUPS[$data['role_group']].' roles.');
    }

    /** The school being edited, which the signed-in user must be allowed to manage. */
    private function school(Request $request, ?int $schoolId = null): School
    {
        $schoolId ??= (int) $request->input('school_id');
        $user = $request->user();
        abort_unless($schoolId, 422, 'Choose a school first.');
        if ($user->role !== 'master_user') {
            abort_unless((int) $user->school_id === $schoolId, 404);
        }

        return School::findOrFail($schoolId);
    }

    /** @return array<int, string> */
    private function assignableRoles(Request $request, ?User $target = null): array
    {
        $roles = array_keys(User::ROLES);
        if ($request->user()->role !== 'master_user' && ! ($target && $target->role === 'school_admin')) {
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
