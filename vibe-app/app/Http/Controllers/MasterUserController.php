<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The master user's own account: details and password. Only the master user, and only their own account. */
class MasterUserController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $this->master($request);
        $rules = [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
        ];
        // The master always may; a Sub-master only with the 'edit_own_name' switch. Otherwise both stay as they are.
        if ($this->canRename($user)) {
            $rules += [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            ];
        }
        $data = $request->validate($rules, [
            'username.regex' => 'The username can only have letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already used by another account.',
            'email.unique' => 'That e-mail address is already used by another account.',
        ]);
        $data['email'] = strtolower($data['email']);

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();
        $this->audit($user, 'master_profile_updated', ['changed' => $changed]);

        return redirect()->route('user-management', ['tab' => 'master-user'])->with('success', 'Your account details were saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $this->master($request);
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ], [
            'password.min' => 'The new password needs at least 12 characters.',
            'password.different' => 'The new password must be different from the current one.',
        ]);

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $this->audit($user, 'master_password_changed');

        return redirect()->route('user-management', ['tab' => 'master-user'])->with('success', 'Your password was changed.');
    }

    /** The signed-in master or Sub-master, for their own account only. */
    private function master(Request $request): User
    {
        abort_unless($request->user()?->isAnyMaster(), 403);

        return $request->user();
    }

    private function canRename(User $user): bool
    {
        return $user->isMaster() || $user->hasAccess('edit_own_name');
    }

    /** @param  array<string, mixed>  $metadata */
    private function audit(User $user, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $user->id, 'action' => $action, 'auditable_type' => User::class, 'auditable_id' => $user->id, 'metadata' => $metadata ?: null,
        ]);
    }
}
