@php
    $input = 'mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
    $me = auth()->user();
    $canRename = $me->isMaster() || $me->hasAccess('edit_own_name');
    $locked = $canRename ? '' : 'readonly tabindex=-1';
@endphp
<div class="grid max-w-5xl grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Account details</h2>
        <p class="mt-1 text-xs text-on-surface-variant">Your own master account. If you change the e-mail or the username, that is what you use the next time you sign in.@unless($canRename) Your name and username can only be changed by the master user.@endunless</p>
        <form method="POST" action="{{ route('master-user.update') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">@csrf @method('PUT')
            <label class="text-xs font-semibold text-on-surface-variant sm:col-span-2">Full name<input name="name" required value="{{ old('name', $me->name) }}" {{ $locked }} class="{{ $input }}{{ $canRename ? '' : ' bg-surface-container text-on-surface-variant' }}">@error('name')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Username<input name="username" required value="{{ old('username', $me->username) }}" {{ $locked }} class="{{ $input }}{{ $canRename ? '' : ' bg-surface-container text-on-surface-variant' }}">@error('username')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">E-mail<input type="email" name="email" required value="{{ old('email', $me->email) }}" class="{{ $input }}">@error('email')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="phone" value="{{ old('phone', $me->phone) }}" class="{{ $input }}">@error('phone')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Position<input name="position" value="{{ old('position', $me->position) }}" class="{{ $input }}">@error('position')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <div class="sm:col-span-2"><button class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">save</span>Save details</button></div>
        </form>
    </section>

    <section class="rounded border border-outline-variant/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Change password</h2>
        <p class="mt-1 text-xs text-on-surface-variant">At least 12 characters, different from your current password.</p>
        <form method="POST" action="{{ route('master-user.password') }}" class="mt-4 grid grid-cols-1 gap-4" autocomplete="off">@csrf @method('PUT')
            <label class="text-xs font-semibold text-on-surface-variant">Current password<input type="password" name="current_password" required autocomplete="current-password" class="{{ $input }}">@error('current_password')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">New password<input type="password" name="password" required minlength="12" autocomplete="new-password" class="{{ $input }}">@error('password')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
            <label class="text-xs font-semibold text-on-surface-variant">Confirm new password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="{{ $input }}"></label>
            <div><button class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">key</span>Change password</button></div>
        </form>
    </section>
</div>
@if(auth()->user()->isMaster())
    @include('partials.master-user-subs')
@endif
@if(config('app.demo') && auth()->user()->isMaster())
    <section class="mt-4 max-w-5xl rounded border border-error/30 bg-white p-5">
        <h2 class="text-lg font-semibold">Demo data</h2>
        <p class="mt-1 text-xs text-on-surface-variant">This is the demo copy of the system. Reset puts all data back to the starting demo schools, users and requests, and signs everybody out. It cannot be undone.</p>
        <form method="POST" action="{{ route('demo.reset') }}" class="mt-4" onsubmit="return confirm('Erase everything in this demo and start again?')">@csrf
            <button class="inline-flex items-center gap-2 rounded bg-error px-4 py-2.5 text-xs font-semibold text-white hover:opacity-90"><span class="material-symbols-outlined text-[18px]">restart_alt</span>Reset demo data</button>
        </form>
    </section>
@endif
