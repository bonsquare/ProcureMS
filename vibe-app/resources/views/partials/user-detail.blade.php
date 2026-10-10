@php
    $input = 'mt-1.5 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm font-normal outline-none focus:border-primary';
    $status = $account->status ?: 'active';
    $isActive = $status === 'active';
    $tone = ['active' => 'secondary', 'pending' => 'error'][$status] ?? 'on-surface-variant';
@endphp
<a href="{{ route('user-management') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>All users</a>
<div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">{{ $account->name }}</h1><p class="mt-1 text-sm text-on-surface-variant">{{ $account->user_code }} · {{ $account->username }}</p></div>
    <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-{{ $tone }}/10 px-3 py-1.5 text-xs font-semibold text-{{ $tone }}"><span class="h-1.5 w-1.5 rounded-full bg-{{ $tone }}"></span>{{ ucfirst($status) }}</span></div>

<div class="grid max-w-6xl grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Account</h2>
        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <div><dt class="text-xs font-semibold text-on-surface-variant">School</dt><dd>{{ $account->school?->name ?? 'No school yet' }}</dd></div>
            <div><dt class="text-xs font-semibold text-on-surface-variant">Division</dt><dd>{{ $account->school?->division ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold text-on-surface-variant">Subscription</dt><dd>@if($subscription)<span class="capitalize">{{ $subscription->plan }} · {{ $subscription->status }}</span>@if($subscription->subscription_end), until {{ $subscription->subscription_end->format('M d, Y') }}@endif @else None @endif</dd></div>
            <div><dt class="text-xs font-semibold text-on-surface-variant">Last sign-in</dt><dd>{{ $account->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</dd></div>
            <div><dt class="text-xs font-semibold text-on-surface-variant">Password changed</dt><dd>{{ $account->password_changed_at?->format('M d, Y') ?? 'Never' }}</dd></div>
            @unless($isActive)<div><dt class="text-xs font-semibold text-on-surface-variant">Inactive since</dt><dd>{{ $account->deactivated_at?->format('M d, Y') ?? '—' }}@if($account->deactivation_reason) · {{ $account->deactivation_reason }}@endif</dd></div>@endunless
        </dl>
        <form method="POST" action="{{ route('user-management.users.update', $account->id) }}" class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant/20 pt-5 sm:grid-cols-2">@csrf @method('PUT')
            <p class="text-xs text-on-surface-variant sm:col-span-2">The name and username can only be changed by the user.</p>
            <label class="text-xs font-semibold text-on-surface-variant">E-mail<input type="email" name="email" required value="{{ old('email', $account->email) }}" class="{{ $input }}">@error('email')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="phone" value="{{ old('phone', $account->phone) }}" class="{{ $input }}">@error('phone')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Position<input name="position" value="{{ old('position', $account->position) }}" class="{{ $input }}">@error('position')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Role<select name="role" required class="{{ $input }}">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(old('role', $account->role) === $key)>{{ $label }}</option>@endforeach</select>@error('role')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <div class="sm:col-span-2"><button class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">save</span>Save changes</button></div>
        </form>
    </section>

    <div class="space-y-4">
        <section class="rounded border border-outline-variant/30 bg-white p-5">
            <h2 class="text-lg font-semibold">Reset password</h2>
            <p class="mt-1 text-xs text-on-surface-variant">At least 12 characters. Give the new password to the user yourself.</p>
            <form method="POST" action="{{ route('user-management.users.password', $account->id) }}" autocomplete="off" class="mt-4 grid grid-cols-1 gap-4">@csrf @method('PUT')
                <label class="text-xs font-semibold text-on-surface-variant">New password<input type="password" name="password" required minlength="12" autocomplete="new-password" class="{{ $input }}">@error('password')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                <label class="text-xs font-semibold text-on-surface-variant">Confirm new password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="{{ $input }}"></label>
                <div><button class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">key</span>Reset password</button></div>
            </form>
        </section>

        <section class="rounded border border-outline-variant/30 bg-white p-5">
            @error('user')<div class="mb-3 rounded border border-error/30 bg-error/5 px-3 py-2 text-xs text-error">{{ $message }}</div>@enderror
            @if($isActive)
                <h2 class="text-lg font-semibold">Set inactive</h2>
                <p class="mt-1 text-xs text-on-surface-variant">The user can no longer sign in. Nothing is deleted, and the account can be activated again.</p>
                <form method="POST" action="{{ route('user-management.users.deactivate', $account->id) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">@csrf
                    <label class="text-xs font-semibold text-on-surface-variant">Reason<select name="reason" required class="{{ $input }}">@foreach($reasons as $reason)<option @selected(old('reason') === $reason)>{{ $reason }}</option>@endforeach</select>@error('reason')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="text-xs font-semibold text-on-surface-variant">Effective date<input type="date" name="effective_date" required max="{{ now()->toDateString() }}" value="{{ old('effective_date', now()->toDateString()) }}" class="{{ $input }}">@error('effective_date')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
                    <label class="text-xs font-semibold text-on-surface-variant sm:col-span-2">Note (optional)<input name="note" maxlength="500" value="{{ old('note') }}" class="{{ $input }}"></label>
                    <div class="sm:col-span-2"><button class="inline-flex items-center gap-2 rounded bg-error px-4 py-2.5 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[18px]">person_off</span>Set inactive</button></div>
                </form>
            @else
                <h2 class="text-lg font-semibold">Activate again</h2>
                <p class="mt-1 text-xs text-on-surface-variant">Works only while the school is active and has no other active user.</p>
                <form method="POST" action="{{ route('user-management.users.reactivate', $account->id) }}" class="mt-4">@csrf
                    <button class="inline-flex items-center gap-2 rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[18px]">how_to_reg</span>Activate user</button>
                </form>
            @endif
        </section>
    </div>
</div>

<section class="mt-4 max-w-6xl rounded border border-outline-variant/30 bg-white p-5"><h2 class="text-lg font-semibold">History</h2>
    <ul class="mt-3 space-y-2 text-xs text-on-surface-variant">@forelse($history as $event)<li><strong class="text-on-surface">{{ ucfirst(str_replace('_', ' ', $event->action)) }}</strong> by {{ $event->user?->name ?? 'System' }} · {{ $event->created_at?->format('M d, Y H:i') }}</li>@empty<li>Nothing recorded yet.</li>@endforelse</ul>
    <a href="{{ route('audit-logs.index', ['school_id' => $account->school_id]) }}" class="mt-4 inline-block text-xs font-semibold text-primary hover:underline">Open the audit log of this school</a></section>
