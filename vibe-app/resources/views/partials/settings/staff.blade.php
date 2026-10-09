@php
    $groupStyle = [
        'bac_role' => ['#e4edf6', '#103967', 'gavel'],
        'procurement_role' => ['#e2edf7', '#286da8', 'shopping_cart'],
        'document_role' => ['#ddf0e8', '#2a7f64', 'description'],
    ];
    $staffData = $staff->mapWithKeys(fn ($member) => [$member->id => [
        'name' => $member->name, 'position' => $member->position, 'code' => $member->employee_no,
        'roles' => collect(array_keys($roleGroups))->mapWithKeys(fn ($group) => [$group => $member->rolesFor($group)])->all(),
    ]]);
    $dialog = 'w-[min(46rem,95vw)] rounded-2xl border border-outline-variant/60 bg-white p-0 shadow-2xl backdrop:bg-black/40';
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
@endphp

<section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
        <div><h2 class="font-bold text-primary">Employees &amp; roles</h2><p class="text-xs text-on-surface-variant">Who signs the BAC, procurement and other documents. One person can hold many roles.</p></div>
        <button type="button" data-open="staff-add" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">person_add</span>Add employee</button>
    </div>
    <div class="flex flex-wrap gap-3 border-b border-outline-variant/40 px-5 py-2.5 text-[11px] font-semibold text-on-surface-variant">
        @foreach($roleGroups as $group => $label)
            @php [$soft, $strong, $icon] = $groupStyle[$group]; @endphp
            <span class="inline-flex items-center gap-1.5"><span class="grid h-5 w-5 place-items-center rounded" style="background:{{ $soft }};color:{{ $strong }}"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $icon }}</span></span>{{ $label }} roles</span>
        @endforeach
    </div>
    @if($staff->isEmpty())
        <div class="p-10 text-center text-sm text-on-surface-variant">No employees yet. Add the people who sign your documents.</div>
    @else
        <ul class="divide-y divide-outline-variant/40">
            @foreach($staff as $member)
                @php $initials = collect(preg_split('/\s+/', trim($member->name)))->filter()->take(2)->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))->implode(''); @endphp
                <li class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 px-5 py-3.5 hover:bg-surface-low/40">
                    <div class="flex min-w-[15rem] items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-white">{{ $initials }}</span>
                        <div class="min-w-0"><p class="truncate font-bold">{{ $member->name }}</p><p class="truncate text-xs text-on-surface-variant">{{ $member->position ?: 'No position' }} · {{ $member->employee_no }}</p></div>
                    </div>
                    <div class="flex min-w-[12rem] flex-1 flex-wrap items-center gap-1.5">
                        @php $any = false; @endphp
                        @foreach($roleGroups as $group => $label)
                            @php [$soft, $strong, $icon] = $groupStyle[$group]; @endphp
                            @foreach($member->rolesFor($group) as $role)
                                @php $any = true; @endphp
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold" style="background:{{ $soft }};color:{{ $strong }}" title="{{ $label }} role"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">{{ $icon }}</span>{{ $role }}</span>
                            @endforeach
                        @endforeach
                        @unless($any)<span class="text-xs text-on-surface-variant">No roles yet</span>@endunless
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-staff-edit="{{ $member->id }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-primary/40 px-3 text-[11px] font-bold text-primary hover:bg-primary hover:text-white" aria-label="Manage roles of {{ $member->name }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">manage_accounts</span>Roles</button>
                        <form method="POST" action="{{ route('school-settings.employees.destroy', $member) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from the employee list?')">@csrf @method('DELETE')<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><button class="grid h-8 w-8 place-items-center rounded-lg border border-outline-variant text-on-surface-variant hover:border-error hover:bg-error hover:text-white" aria-label="Remove {{ $member->name }}" title="Remove"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">delete</span></button></form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- The role checklist, shared by the add and the edit dialog --}}
@php
    $roleChecklist = function (string $prefix) use ($roleGroups, $roleCatalog, $groupStyle) {
        $html = '<div class="grid gap-3 sm:grid-cols-3">';
        foreach ($roleGroups as $group => $label) {
            [$soft, $strong, $icon] = $groupStyle[$group];
            $html .= '<fieldset class="rounded-xl border border-outline-variant/50 p-3"><legend class="flex items-center gap-1.5 px-1 text-xs font-bold" style="color:'.$strong.'"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">'.$icon.'</span>'.e($label).'</legend><div class="mt-1 space-y-1.5" data-group="'.$group.'">';
            foreach ($roleCatalog[$group] ?? [] as $role) {
                $html .= '<label class="flex cursor-pointer items-center gap-2 text-xs font-medium"><input type="checkbox" name="roles['.$group.'][]" value="'.e($role).'" class="h-4 w-4 rounded border-outline-variant"> '.e($role).'</label>';
            }
            $html .= '</div></fieldset>';
        }

        return $html.'</div>';
    };
@endphp

<dialog id="staff-add" class="{{ $dialog }}">
    <form method="POST" action="{{ route('school-settings.employees.store') }}" class="p-5">
        @csrf
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <div class="mb-4 flex items-start justify-between"><div><h3 class="text-lg font-bold">Add employee</h3><p class="text-xs text-on-surface-variant">An employee number is generated automatically. No login account is created.</p></div><button type="button" data-close class="rounded-lg p-1 hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold">Full name<input name="name" required class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Position<input name="position" placeholder="e.g. Administrative Officer II" class="{{ $field }}"></label>
        </div>
        <p class="mb-2 mt-4 text-xs font-bold">Roles</p>
        {!! $roleChecklist('add') !!}
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white">Add employee</button></div>
    </form>
</dialog>

<dialog id="staff-edit" class="{{ $dialog }}">
    <form method="POST" id="staff-edit-form" class="p-5">
        @csrf @method('PUT')
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <div class="mb-4 flex items-start justify-between"><div><h3 class="text-lg font-bold">Employee roles</h3><p class="text-xs text-on-surface-variant">Employee no. <strong id="staff-edit-code"></strong></p></div><button type="button" data-close class="rounded-lg p-1 hover:bg-surface-container" aria-label="Close"><span class="material-symbols-outlined">close</span></button></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-bold">Full name<input name="name" required class="{{ $field }}"></label>
            <label class="block text-xs font-bold">Position<input name="position" class="{{ $field }}"></label>
        </div>
        <p class="mb-2 mt-4 text-xs font-bold">Tick every role this person holds</p>
        {!! $roleChecklist('edit') !!}
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold">Cancel</button><button class="rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white">Save employee</button></div>
    </form>

    {{-- A role the lists do not have yet --}}
    <form method="POST" action="{{ route('school-settings.roles.store') }}" class="border-t border-outline-variant/40 bg-surface-low/60 px-5 py-3.5">
        @csrf
        <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
        <p class="text-xs font-bold">Need a role that is not listed?</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <select name="role_group" class="rounded-lg border border-outline-variant bg-white px-2.5 py-2 text-xs">@foreach($roleGroups as $group => $label)<option value="{{ $group }}">{{ $label }}</option>@endforeach</select>
            <input name="name" required maxlength="100" placeholder="New role name" class="min-w-[10rem] flex-1 rounded-lg border border-outline-variant bg-white px-3 py-2 text-xs outline-none focus:border-action">
            <button class="inline-flex items-center gap-1 rounded-lg border border-primary px-3 py-2 text-xs font-bold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[15px]" aria-hidden="true">add</span>Add role</button>
        </div>
        <p class="mt-1.5 text-[11px] text-on-surface-variant">Save your changes first, then add the new role. The page reloads when a role is added.</p>
    </form>
</dialog>

<script>
    (() => {
        const staff = @json($staffData);
        const updateUrl = @json(route('school-settings.employees.update', '__ID__'));
        document.querySelectorAll('[data-staff-edit]').forEach((button) => button.addEventListener('click', () => {
            const id = button.dataset.staffEdit; const member = staff[id]; const form = document.getElementById('staff-edit-form');
            form.action = updateUrl.replace('__ID__', id);
            form.elements.name.value = member.name || '';
            form.elements.position.value = member.position || '';
            document.getElementById('staff-edit-code').textContent = member.code || '';
            form.querySelectorAll('input[type=checkbox]').forEach((box) => {
                const group = box.closest('[data-group]').dataset.group;
                box.checked = (member.roles[group] || []).some((role) => role.toLowerCase() === box.value.toLowerCase());
            });
            document.getElementById('staff-edit').showModal();
        }));
    })();
</script>
