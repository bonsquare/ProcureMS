@php
    use App\Support\SubMasterAccess;
    $input = 'mt-2 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2.5 text-sm font-normal outline-none focus:border-primary';
    $areaKeys = array_keys(SubMasterAccess::WORK_AREAS + SubMasterAccess::MANAGEMENT_AREAS);
    $subMasters = \App\Models\User::where('role', 'sub_master')->orderBy('name')->get();
@endphp
<section class="mt-4 max-w-5xl rounded border border-outline-variant/30 bg-white p-5">
    <h2 class="text-lg font-semibold">Sub-masters</h2>
    <p class="mt-1 text-xs text-on-surface-variant">Accounts that work across every school with the access you choose. A Sub-master cannot manage other masters or this checklist. Accounts are deactivated, never deleted.</p>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Username</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Access</th></tr></thead>
            <tbody>
            @forelse($subMasters as $sub)
                @php $selected = SubMasterAccess::sanitize($sub->access); $areasOn = count(array_intersect($selected, $areaKeys)); @endphp
                <tr class="border-t border-outline-variant/20"><td class="px-3 py-2 font-semibold">{{ $sub->name }}</td><td class="px-3 py-2">{{ $sub->username }}</td><td class="px-3 py-2">{{ $sub->email }}</td>
                    <td class="px-3 py-2">@if(($sub->status ?: 'active') === 'active')<span class="font-semibold text-secondary">Active</span>@else<span class="font-semibold text-error">Inactive</span>@endif</td>
                    <td class="px-3 py-2 text-xs">{{ $areasOn }} of {{ count($areaKeys) }}@if(array_intersect($selected, array_keys(SubMasterAccess::SWITCHES))) · {{ count(array_intersect($selected, array_keys(SubMasterAccess::SWITCHES))) }} switch(es) on @endif</td></tr>
                <tr class="border-b border-outline-variant/20"><td colspan="5" class="px-3 pb-3">
                    <details class="rounded border border-outline-variant/30 bg-surface-low/50 p-3">
                        <summary class="cursor-pointer text-xs font-bold text-primary">Edit {{ $sub->name }}</summary>
                        <form method="POST" action="{{ route('master-user.sub-masters.update', $sub->id) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf @method('PUT')
                            <label class="text-xs font-semibold text-on-surface-variant">Full name<input name="name" required value="{{ $sub->name }}" class="{{ $input }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant">Username<input name="username" required value="{{ $sub->username }}" class="{{ $input }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant">E-mail<input type="email" name="email" required value="{{ $sub->email }}" class="{{ $input }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="phone" value="{{ $sub->phone }}" class="{{ $input }}"></label>
                            <label class="text-xs font-semibold text-on-surface-variant sm:col-span-2">Position<input name="position" value="{{ $sub->position }}" class="{{ $input }}"></label>
                            <div class="sm:col-span-2">@include('partials.sub-master-checklist', ['selected' => $selected])</div>
                            <div class="sm:col-span-2"><button class="rounded bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-container">Save changes</button></div>
                        </form>
                        <div class="mt-4 grid gap-3 border-t border-outline-variant/30 pt-3 sm:grid-cols-2">
                            <form method="POST" action="{{ route('master-user.sub-masters.password', $sub->id) }}" class="space-y-2" autocomplete="off">@csrf @method('PUT')
                                <p class="text-xs font-bold">Reset password</p>
                                <input type="password" name="password" required minlength="12" placeholder="New password (12+ characters)" autocomplete="new-password" class="{{ $input }}">
                                <input type="password" name="password_confirmation" required minlength="12" placeholder="Confirm new password" autocomplete="new-password" class="{{ $input }}">
                                <button class="rounded border border-primary/40 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary hover:text-white">Reset password</button>
                            </form>
                            <form method="POST" action="{{ route('master-user.sub-masters.status', $sub->id) }}" class="space-y-2">@csrf @method('PUT')
                                <p class="text-xs font-bold">Status</p>
                                @if(($sub->status ?: 'active') === 'active')
                                    <input type="hidden" name="status" value="inactive"><button class="rounded border border-error/40 px-3 py-1.5 text-xs font-bold text-error hover:bg-error hover:text-white" onclick="return confirm('Deactivate this Sub-master? They cannot sign in until you reactivate them.')">Deactivate</button>
                                @else
                                    <input type="hidden" name="status" value="active"><button class="rounded border border-secondary/40 px-3 py-1.5 text-xs font-bold text-secondary hover:bg-secondary hover:text-white">Reactivate</button>
                                @endif
                            </form>
                        </div>
                    </details>
                </td></tr>
            @empty
                <tr><td colspan="5" class="px-3 py-4 text-on-surface-variant">No Sub-masters yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <details class="mt-4 rounded border border-outline-variant/30 p-3" @if($errors->any() && old('_add')) open @endif>
        <summary class="cursor-pointer text-sm font-bold text-primary">Add Sub-master</summary>
        <form method="POST" action="{{ route('master-user.sub-masters.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" autocomplete="off">@csrf <input type="hidden" name="_add" value="1">
            <label class="text-xs font-semibold text-on-surface-variant">Full name<input name="name" required value="{{ old('_add') ? old('name') : '' }}" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Username<input name="username" required value="{{ old('_add') ? old('username') : '' }}" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">E-mail<input type="email" name="email" required value="{{ old('_add') ? old('email') : '' }}" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="phone" value="{{ old('_add') ? old('phone') : '' }}" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant sm:col-span-2">Position<input name="position" value="{{ old('_add') ? old('position') : '' }}" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Temporary password (12+ characters)<input type="password" name="password" required minlength="12" autocomplete="new-password" class="{{ $input }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="{{ $input }}"></label>
            <div class="sm:col-span-2">@include('partials.sub-master-checklist', ['selected' => SubMasterAccess::defaults()])</div>
            <div class="sm:col-span-2"><button class="rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container">Add Sub-master</button></div>
        </form>
    </details>
    @if($errors->any() && (old('_add') || old('access_present')))
        <div class="mt-3 rounded border border-error/30 bg-error/5 px-3 py-2 text-xs text-error">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
    @endif
</section>
