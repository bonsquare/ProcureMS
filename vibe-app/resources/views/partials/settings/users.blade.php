@php
    $roleLabel = fn (?string $role) => \App\Models\User::ROLES[$role] ?? str((string) $role)->replace('_', ' ')->title();
    $userData = $systemUsers->mapWithKeys(fn ($user) => [$user->id => [
        'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'position' => $user->position, 'office' => $user->office,
        'phone' => $user->phone, 'role' => $user->role, 'status' => $user->status ?: 'active', 'code' => $user->user_code, 'self' => $user->is(auth()->user()), 'station' => $selectedSchool->name,
    ]]);
@endphp

<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <div><h2 class="font-bold text-primary">System users</h2><p class="text-xs text-on-surface-variant">The person who manages {{ $selectedSchool->name }}. One user manages one school.</p></div>
    </div>
    @if($systemUsers->isEmpty())
        <div class="p-10 text-center text-sm text-on-surface-variant">No system users yet.</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-xs">
                <thead class="bg-surface-low text-on-surface-variant"><tr><th class="px-4 py-2.5">User</th><th class="px-4 py-2.5">User ID · Username</th><th class="px-4 py-2.5">Position · Official station</th><th class="px-4 py-2.5">System role</th><th class="px-4 py-2.5">Last sign-in</th><th class="px-4 py-2.5">Status</th><th class="w-px px-4 py-2.5"></th></tr></thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @foreach($systemUsers as $user)
                        @php $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))->implode(''); @endphp
                        <tr class="hover:bg-surface-low/50">
                            <td class="px-4 py-2.5"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary text-[11px] font-bold text-white">{{ $initials }}</span><div class="min-w-0"><p class="truncate font-bold">{{ $user->name }}@if($user->is(auth()->user()))<span class="ml-1.5 rounded-full bg-secondary/10 px-1.5 py-0.5 text-[10px] font-bold text-secondary">You</span>@endif</p><p class="truncate text-on-surface-variant">{{ $user->email }}</p></div></div></td>
                            <td class="px-4 py-2.5"><p class="font-semibold">{{ $user->user_code }}</p><p class="text-on-surface-variant">&#64;{{ $user->username ?: '—' }}</p></td>
                            <td class="px-4 py-2.5"><p>{{ $user->position ?: '—' }}</p><p class="flex items-center gap-1 text-on-surface-variant"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">location_on</span>{{ $user->school?->name ?? $selectedSchool->name }}{{ $user->office ? ' · '.$user->office : '' }}</p></td>
                            <td class="px-4 py-2.5"><span class="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold text-primary">{{ $roleLabel($user->role) }}</span></td>
                            <td class="px-4 py-2.5 text-on-surface-variant">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}<p class="text-[11px]">Password: {{ $user->password_changed_at ? $user->password_changed_at->format('M d, Y') : 'never changed' }}</p></td>
                            <td class="px-4 py-2.5"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ ($user->status ?: 'active') === 'active' ? 'bg-secondary/10 text-secondary' : 'bg-outline-variant/40 text-on-surface-variant' }}">{{ str($user->status ?: 'active')->title() }}</span></td>
                            <td class="px-4 py-2.5"><div class="flex justify-end gap-1.5">
                                <button type="button" data-user-edit="{{ $user->id }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-primary/40 px-2.5 text-[11px] font-bold text-primary hover:bg-primary hover:text-white" aria-label="Edit {{ $user->name }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>Edit</button>
                                <button type="button" data-user-password="{{ $user->id }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-outline-variant px-2.5 text-[11px] font-bold text-on-surface-variant hover:bg-surface-container" aria-label="Change password for {{ $user->name }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">key</span>Password</button>
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@php
    $dialog = 'w-[min(34rem,94vw)] rounded-2xl border border-outline-variant/60 bg-white p-0 shadow-2xl backdrop:bg-black/40';
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
@endphp

{{-- Edit user --}}
<dialog id="user-edit" class="{{ $dialog }}">
    <form method="POST" id="user-edit-form" class="p-5">
        @csrf @method('PUT')
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <div class="mb-4 flex items-start justify-between"><div><h3 class="text-lg font-bold">Edit system user</h3><p class="text-xs text-on-surface-variant">User ID <strong id="user-edit-code"></strong> cannot be changed.</p></div><button type="button" data-close class="rounded-lg p-1 hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold sm:col-span-2">Full name <span class="font-normal text-on-surface-variant">(cannot be changed)</span><input name="name" readonly tabindex="-1" class="{{ $field }} bg-surface-low font-semibold text-on-surface-variant" title="The full name identifies the account in the system and stays the same."></label>
            <label class="block text-xs font-bold">Username <span class="font-normal text-on-surface-variant">(cannot be changed)</span><input name="username" readonly tabindex="-1" class="{{ $field }} bg-surface-low font-semibold text-on-surface-variant" title="The username identifies the account in the system and stays the same."></label>
            <label class="block text-xs font-bold">Email<input type="email" name="email" required class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Position<input name="position" class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Official station <span class="font-normal text-on-surface-variant">(the school)</span><input name="station" readonly tabindex="-1" class="{{ $field }} bg-surface-low font-semibold text-on-surface-variant"></label>
            <label class="block text-xs font-bold">Office / section<input name="office" class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Mobile number<input name="phone" class="{{ $field }}"></label>
            <label class="block text-xs font-bold">System role @unless($isMasterUser)<span class="font-normal text-on-surface-variant">(master user only)</span>@endunless
                <select name="role" required @disabled(! $isMasterUser) class="{{ $field }} @unless($isMasterUser) bg-surface-low font-semibold text-on-surface-variant @endunless">@foreach(\App\Models\User::ROLES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label class="block text-xs font-bold">Status<select name="status" class="{{ $field }}"><option value="active">Active</option><option value="inactive">Inactive (cannot sign in)</option></select></label>
        </div>
        <p id="user-edit-self" class="mt-3 hidden rounded-lg bg-attention/10 px-3 py-2 text-xs font-semibold text-attention">This is your own account, so its role and status stay as they are.</p>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white">Save changes</button></div>
    </form>
</dialog>

{{-- Change password --}}
<dialog id="user-password" class="{{ $dialog }}">
    <form method="POST" id="user-password-form" class="p-5">
        @csrf @method('PUT')
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <div class="mb-4 flex items-start justify-between"><div><h3 class="text-lg font-bold">Change password</h3><p class="text-xs text-on-surface-variant" id="user-password-for"></p></div><button type="button" data-close class="rounded-lg p-1 hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
        <div class="grid gap-3">
            <label class="block text-xs font-bold" id="user-password-current-wrap">Your current password<input type="password" name="current_password" autocomplete="current-password" class="{{ $field }}"></label>
            <label class="block text-xs font-bold">New password<input type="password" name="password" required minlength="8" autocomplete="new-password" class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Confirm new password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="{{ $field }}"></label>
        </div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white">Change password</button></div>
    </form>
</dialog>

<script>
    (() => {
        const users = @json($userData);
        const updateUrl = @json(route('school-settings.users.update', '__ID__'));
        const passwordUrl = @json(route('school-settings.users.password', '__ID__'));
        document.querySelectorAll('[data-user-edit]').forEach((button) => button.addEventListener('click', () => {
            const id = button.dataset.userEdit; const user = users[id]; const form = document.getElementById('user-edit-form');
            form.action = updateUrl.replace('__ID__', id);
            ['name', 'username', 'email', 'position', 'office', 'phone', 'role', 'status', 'station'].forEach((field) => { form.elements[field].value = user[field] ?? ''; });
            document.getElementById('user-edit-code').textContent = user.code || '';
            document.getElementById('user-edit-self').classList.toggle('hidden', !user.self);
            document.getElementById('user-edit').showModal();
        }));
        document.querySelectorAll('[data-user-password]').forEach((button) => button.addEventListener('click', () => {
            const id = button.dataset.userPassword; const user = users[id]; const form = document.getElementById('user-password-form');
            form.action = passwordUrl.replace('__ID__', id);
            document.getElementById('user-password-for').textContent = user.name + ' (' + (user.code || '') + ')';
            const wrap = document.getElementById('user-password-current-wrap');
            wrap.classList.toggle('hidden', !user.self);
            form.elements.current_password.required = !!user.self;
            document.getElementById('user-password').showModal();
        }));
    })();
</script>
