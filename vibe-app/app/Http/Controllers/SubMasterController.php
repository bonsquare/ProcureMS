<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SubMasterAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The master creates and manages Sub-masters. A Sub-master can never reach these actions. */
class SubMasterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMaster($request);
        $data = $request->validate([
            ...$this->detailRules(null),
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            ...$this->accessRules(),
        ], $this->messages());

        $access = $request->has('access_present') ? SubMasterAccess::sanitize($data['access'] ?? []) : SubMasterAccess::defaults();
        $user = new User($this->details($data) + ['password' => $data['password']]);
        $user->forceFill(['role' => 'sub_master', 'status' => 'active', 'organization_id' => null, 'school_id' => null, 'access' => $access, 'password_changed_at' => now()])->save();
        $this->audit($request, $user, 'sub_master_created', ['access' => $access]);

        return $this->done($user->name.' was added as a Sub-master.');
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $this->authorizeMaster($request);
        $user = $this->subMaster($user);
        $data = $request->validate([...$this->detailRules($user), ...$this->accessRules()], $this->messages());

        $before = SubMasterAccess::sanitize($user->access);
        $user->fill($this->details($data));
        if ($request->has('access_present')) {
            $user->forceFill(['access' => SubMasterAccess::sanitize($data['access'] ?? [])]);
        }
        $user->save();
        $this->audit($request, $user, 'sub_master_updated', ['access_before' => $before, 'access_after' => SubMasterAccess::sanitize($user->access)]);

        return $this->done($user->name.' was updated.');
    }

    public function password(Request $request, int $user): RedirectResponse
    {
        $this->authorizeMaster($request);
        $user = $this->subMaster($user);
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']], $this->messages());

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $this->audit($request, $user, 'sub_master_password_reset');

        return $this->done('The password of '.$user->name.' was reset.');
    }

    public function status(Request $request, int $user): RedirectResponse
    {
        $this->authorizeMaster($request);
        $user = $this->subMaster($user);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);

        $inactive = $data['status'] === 'inactive';
        $user->forceFill([
            'status' => $data['status'],
            'deactivated_at' => $inactive ? now() : null,
            'deactivation_reason' => $inactive ? 'Deactivated by the master user' : null,
        ])->save();
        $this->audit($request, $user, 'sub_master_status_changed', ['status' => $data['status']]);

        return $this->done($user->name.($inactive ? ' was deactivated.' : ' was reactivated.'));
    }

    private function authorizeMaster(Request $request): void
    {
        abort_unless($request->user()?->isMaster(), 403);
    }

    /** Looked up after the permission check, so nobody but the master learns whether an id exists. */
    private function subMaster(int $id): User
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);
        abort_unless($user->isSubMaster(), 404);

        return $user;
    }

    /** @return array<string, mixed> */
    private function detailRules(?User $ignore): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($ignore?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    private function accessRules(): array
    {
        return ['access' => ['nullable', 'array'], 'access.*' => ['string', Rule::in(SubMasterAccess::keys())]];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'username.regex' => 'The username can only have letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already used by another account.',
            'email.unique' => 'That e-mail address is already used by another account.',
            'password.min' => 'The password needs at least 12 characters.',
            'access.*.in' => 'One of the access choices is not valid.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function details(array $data): array
    {
        return array_merge(array_intersect_key($data, array_flip(['name', 'username', 'phone', 'position'])), ['email' => strtolower($data['email'])]);
    }

    /** @param  array<string, mixed>  $metadata */
    private function audit(Request $request, User $target, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'action' => $action, 'auditable_type' => User::class, 'auditable_id' => $target->id, 'metadata' => $metadata ?: null,
        ]);
    }

    private function done(string $message): RedirectResponse
    {
        return redirect()->route('user-management', ['tab' => 'master-user'])->with('success', $message);
    }
}
