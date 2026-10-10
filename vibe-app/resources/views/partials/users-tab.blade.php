@php
    $input = 'mt-1.5 w-full rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-sm font-normal outline-none focus:border-primary';
    $filter = 'rounded border border-outline-variant/50 bg-surface-low px-3 py-2 text-xs outline-none';
    $statusTone = ['active' => 'secondary', 'pending' => 'error', 'inactive' => 'on-surface-variant'];
    $metricCards = [
        ['key' => 'total', 'label' => 'Total Users', 'note' => 'All school accounts', 'icon' => 'group', 'tone' => 'primary'],
        ['key' => 'active', 'label' => 'Active Users', 'note' => $metrics['total'] ? round($metrics['active'] / $metrics['total'] * 100, 1).'% active rate' : 'No users yet', 'icon' => 'verified', 'tone' => 'secondary'],
        ['key' => 'pending', 'label' => 'Pending Users', 'note' => 'Waiting for the master', 'icon' => 'hourglass_top', 'tone' => 'error'],
        ['key' => 'administrators', 'label' => 'Administrators', 'note' => 'School administrators', 'icon' => 'admin_panel_settings', 'tone' => 'primary'],
    ];
    $openForm = $errors->any() && old('school_id') !== null;
@endphp
<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><div class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">group</span><span class="font-semibold text-primary">Access &amp; Permissions</span></div><h1 class="text-[28px] font-semibold leading-9 tracking-tight">User Management</h1><p class="mt-1 text-[15px] leading-6 text-on-surface-variant">Manage accounts, school assignments, roles, and access across the organization.</p></div>
    <a href="#add-user" onclick="document.getElementById('add-user').open = true" class="flex items-center justify-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">person_add</span>Add User</a></div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metricCards as $card)<article class="flex items-center justify-between rounded border border-outline-variant/30 bg-white p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $card['label'] }}</p><p class="mt-3 text-3xl font-semibold tabular-nums {{ $card['tone'] === 'error' ? 'text-error' : '' }}" data-metric="{{ $card['key'] }}">{{ $metrics[$card['key']] }}</p><p class="mt-1 text-xs font-medium text-{{ $card['tone'] }}">{{ $card['note'] }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded bg-{{ $card['tone'] }}/10 text-{{ $card['tone'] }}"><span class="material-symbols-outlined">{{ $card['icon'] }}</span></span></article>@endforeach</div>

<details id="add-user" class="mb-8 rounded border border-outline-variant/30 bg-white" @if($openForm) open @endif>
    <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-4 text-sm font-semibold text-primary"><span class="material-symbols-outlined text-[20px]">person_add</span>Add User</summary>
    <form method="POST" action="{{ route('user-management.users.store') }}" autocomplete="off" class="grid grid-cols-1 gap-4 border-t border-outline-variant/20 p-5 sm:grid-cols-2 lg:grid-cols-3">@csrf
        <p class="text-xs text-on-surface-variant sm:col-span-2 lg:col-span-3">Creates a school account directly. Only schools without an active user are listed; one user manages one school. Give the person the temporary password yourself, and ask them to change it after signing in.</p>
        <label class="text-xs font-semibold text-on-surface-variant">School
            <select name="school_id" required class="{{ $input }}"><option value="">Choose a school</option>@foreach($vacantSchools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}@if($school->division) · {{ $school->division }}@endif</option>@endforeach</select>
            @error('school_id')<span class="mt-1 block text-error">{{ $message }}</span>@enderror
            @if($vacantSchools->isEmpty())<span class="mt-1 block font-normal">Every active school already has a user.</span>@endif</label>
        <label class="text-xs font-semibold text-on-surface-variant">Full name<input name="name" required maxlength="255" value="{{ old('name') }}" class="{{ $input }}">@error('name')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Username<input name="username" required minlength="4" maxlength="60" value="{{ old('username') }}" class="{{ $input }}">@error('username')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">E-mail<input type="email" name="email" required value="{{ old('email') }}" class="{{ $input }}">@error('email')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Phone<input name="phone" value="{{ old('phone') }}" class="{{ $input }}">@error('phone')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Position<input name="position" value="{{ old('position') }}" class="{{ $input }}">@error('position')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Role
            <select name="role" required class="{{ $input }}">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(old('role', 'school_admin') === $key)>{{ $label }}</option>@endforeach</select>
            @error('role')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Temporary password<input type="password" name="password" required minlength="12" autocomplete="new-password" class="{{ $input }}">@error('password')<span class="mt-1 block text-error">{{ $message }}</span>@enderror</label>
        <label class="text-xs font-semibold text-on-surface-variant">Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="{{ $input }}"></label>
        <div class="sm:col-span-2 lg:col-span-3"><button class="inline-flex items-center gap-2 rounded bg-primary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[18px]">person_add</span>Create account</button></div>
    </form>
</details>

<section class="overflow-hidden rounded border border-outline-variant/30 bg-white">
    <div class="flex flex-col justify-between gap-4 border-b border-outline-variant/30 p-5 lg:flex-row lg:items-center"><div><h2 class="text-lg font-semibold">Organization Users</h2><p class="mt-1 text-xs text-on-surface-variant">Review account status, role assignments, and school access.</p></div>
        <form method="GET" action="{{ route('user-management') }}" class="flex flex-wrap gap-2">
            <div class="relative"><span class="material-symbols-outlined absolute left-3 top-2.5 text-[18px] text-on-surface-variant">search</span><input name="q" value="{{ request('q') }}" class="w-full rounded border border-outline-variant/50 bg-surface-low py-2 pl-9 pr-3 text-xs outline-none focus:border-primary sm:w-56" placeholder="Search name, username, e-mail..." type="search"></div>
            <select name="role" class="{{ $filter }}"><option value="">All Roles</option>@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>@endforeach</select>
            <select name="status" class="{{ $filter }}"><option value="">All Statuses</option>@foreach(['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive'] as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
            <select name="school_id" class="{{ $filter }} max-w-[14rem]"><option value="">All Schools</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</select>
            <button class="rounded bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-container">Filter</button>
            @if(request()->hasAny(['q', 'role', 'status', 'school_id']))<a href="{{ route('user-management') }}" class="rounded border border-outline-variant/50 px-3 py-2 text-xs font-semibold text-on-surface-variant hover:bg-surface-low">Clear</a>@endif
        </form></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[1050px] border-collapse text-left"><thead class="bg-surface-low text-xs uppercase tracking-wider text-on-surface-variant"><tr class="border-b border-outline-variant/30"><th class="px-5 py-3 font-semibold">User</th><th class="px-5 py-3 font-semibold">Role</th><th class="px-5 py-3 font-semibold">School / Access</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Last Active</th><th class="px-5 py-3 text-right font-semibold">Actions</th></tr></thead>
        <tbody class="divide-y divide-outline-variant/20 text-[13px]">
        @forelse($users as $row)
            @php $status = $row->status ?: 'active'; $tone = $statusTone[$status] ?? 'on-surface-variant'; @endphp
            <tr class="hover:bg-surface-low/60"><td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">{{ collect(explode(' ', trim($row->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</div><div><div class="font-semibold">{{ $row->name }}</div><div class="mt-0.5 text-xs text-on-surface-variant">{{ $row->email }} · {{ $row->username }}</div></div></div></td>
                <td class="px-5 py-3.5"><span class="rounded bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">{{ $roles[$row->role] ?? ucfirst(str_replace('_', ' ', (string) $row->role)) }}</span></td>
                <td class="px-5 py-3.5 text-on-surface-variant">{{ $row->school?->name ?? 'No school yet' }}</td>
                <td class="px-5 py-3.5"><span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $tone }}/10 px-2 py-1 text-xs font-semibold text-{{ $tone }}"><span class="h-1.5 w-1.5 rounded-full bg-{{ $tone }}"></span>{{ ucfirst($status) }}</span></td>
                <td class="px-5 py-3.5 text-xs text-on-surface-variant">{{ $row->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="px-5 py-3.5 text-right"><a href="{{ route('user-management.users.show', $row->id) }}" class="inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs font-semibold text-primary hover:bg-surface-high" title="View and manage this user"><span class="material-symbols-outlined text-[18px]">visibility</span>Manage</a></td></tr>
        @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-on-surface-variant">No users match.</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="flex flex-col justify-between gap-3 border-t border-outline-variant/20 bg-surface-low px-5 py-3 text-xs text-on-surface-variant sm:flex-row sm:items-center"><span>Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users</span>@if($users->hasPages())<div class="flex gap-2">@if($users->onFirstPage())<span class="rounded bg-surface-container px-3 py-1 opacity-50">Previous</span>@else<a href="{{ $users->previousPageUrl() }}" class="rounded bg-surface-container px-3 py-1 font-semibold hover:bg-surface-high">Previous</a>@endif<span class="px-2 py-1">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>@if($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}" class="rounded bg-surface-container px-3 py-1 font-semibold hover:bg-surface-high">Next</a>@else<span class="rounded bg-surface-container px-3 py-1 opacity-50">Next</span>@endif</div>@endif</div>
</section>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <section class="rounded border border-outline-variant/30 bg-white p-5 lg:col-span-2"><div class="mb-5 flex items-center justify-between"><div><h3 class="text-lg font-semibold">Users by Role</h3><p class="mt-1 text-xs text-on-surface-variant">Current distribution of access levels.</p></div><span class="material-symbols-outlined text-on-surface-variant">bar_chart</span></div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">@forelse($roleCounts->sortDesc()->take(8) as $role => $total)<div class="rounded bg-surface-low p-4"><p class="text-2xl font-semibold">{{ $total }}</p><p class="mt-1 text-xs text-on-surface-variant">{{ $roles[$role] ?? ucfirst(str_replace('_', ' ', (string) $role)) }}</p></div>@empty<p class="text-xs text-on-surface-variant sm:col-span-4">No users yet.</p>@endforelse</div></section>
    <section class="rounded border border-outline-variant/30 bg-white p-5"><div class="mb-5 flex items-center justify-between"><div><h3 class="text-lg font-semibold">Access Activity</h3><p class="mt-1 text-xs text-on-surface-variant">Recent account events.</p></div><span class="material-symbols-outlined text-on-surface-variant">history</span></div>
        <div class="space-y-3 text-xs text-on-surface-variant">@forelse($recentActivity as $event)<div class="flex gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span><span><strong class="text-on-surface">{{ $event->user?->name ?? 'System' }}</strong> · {{ ucfirst(str_replace('_', ' ', $event->action)) }} <span class="block">{{ $event->created_at?->diffForHumans() }}</span></span></div>@empty<p>Nothing recorded yet.</p>@endforelse</div>
        <a href="{{ route('audit-logs.index') }}" class="mt-6 block border-t border-outline-variant/20 pt-4 text-xs font-semibold text-primary hover:underline">View audit log</a></section>
</div>
