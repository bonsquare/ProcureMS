@php
    $numbering = $selectedOrganization?->numbering_preferences ?? [];
    $sectionHead = 'flex items-center gap-2 border-b border-outline-variant/40 pb-3';
    $chip = 'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary';
    $editingSection = old('_section');

    // One look for every section: locked until Edit is pressed, then Save (first time) or Update, with Cancel.
    $heading = function (string $icon, string $title, string $hint) use ($sectionHead, $chip) {
        return '<div class="'.$sectionHead.'"><span class="'.$chip.'"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">'.$icon.'</span></span><div class="min-w-0 flex-1"><h2 class="font-bold">'.e($title).'</h2><p class="text-xs text-on-surface-variant">'.e($hint).'</p></div>'
            .'<button type="button" data-edit class="inline-flex items-center gap-1 rounded-lg border border-primary/40 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary hover:text-white"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>Edit</button></div>';
    };
    $actions = function (bool $hasData) {
        return '<div data-actions class="mt-4 hidden justify-end gap-2"><button type="button" data-cancel class="rounded-lg border border-outline-variant px-4 py-2 text-xs font-bold hover:bg-surface-container">Cancel</button>'
            .'<button class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-white hover:bg-primary-container"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">'.($hasData ? 'sync' : 'save').'</span>'.($hasData ? 'Update' : 'Save').'</button></div>';
    };
    $logoInfo = function (?string $path) {
        if (! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $size = $disk->size($path);
        $dimensions = @getimagesize($disk->path($path));

        return basename($path).' · '.($size >= 1048576 ? round($size / 1048576, 1).' MB' : max(1, round($size / 1024)).' KB').($dimensions ? ' · '.$dimensions[0].' × '.$dimensions[1].' px' : '');
    };
    $logoBlock = function (string $id, string $name, string $label, ?string $path, bool $shared = false) use ($logoUrl, $logoInfo, $isMasterUser) {
        $url = $logoUrl($path);
        $preview = $url ? '<img src="'.e($url).'" alt="'.e($label).'" class="h-full w-full object-contain">' : '<span class="material-symbols-outlined text-[34px]" aria-hidden="true">image</span>';
        $info = $logoInfo($path) ?? 'No logo uploaded yet';

        $rules = [['image', 'PNG, JPG, WebP or GIF'], ['database', 'Up to 2 MB'], ['aspect_ratio', 'Square, 300 × 300 px or more'], ['contrast', 'Transparent or white background']];
        $rows = collect($rules)->map(fn ($rule) => '<li class="flex items-start gap-1.5"><span class="material-symbols-outlined mt-px text-[13px] text-action" aria-hidden="true">'.$rule[0].'</span><span>'.e($rule[1]).'</span></li>')->implode('');

        return '<div class="w-full"><p class="text-xs font-bold">'.e($label).'</p>'
            .'<div id="'.$id.'" class="mt-1 grid h-32 w-full place-items-center overflow-hidden rounded-xl border border-dashed border-outline-variant bg-surface-low text-on-surface-variant">'.$preview.'</div>'
            .'<p data-logo-info="'.$id.'" title="'.e($info).'" class="mt-1.5 min-h-[2.25rem] break-words text-[11px] font-semibold leading-snug text-on-surface-variant" data-default="'.e($info).'">'.e($info).'</p>'
            .(($shared && $url && ! $isMasterUser) ? '<p class="rounded-lg bg-surface-low px-2 py-1.5 text-center text-[10.5px] font-semibold leading-snug text-on-surface-variant">Shared logo. Only the master user can change it.</p>' : '<label class="block cursor-pointer rounded-lg border border-primary/40 px-2 py-1.5 text-center text-[11px] font-bold text-primary hover:bg-primary hover:text-white">'.($url ? 'Change logo' : 'Upload logo').'<input type="file" name="'.$name.'" accept="image/png,image/jpeg,image/webp,image/gif" data-preview="'.$id.'" class="sr-only"></label>')
            .'<div class="mt-2 rounded-lg border border-outline-variant/50 bg-surface-low/70 p-2"><p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">Logo requirements</p><ul class="mt-1 space-y-1 text-[10.5px] leading-snug text-on-surface-variant">'.$rows.'</ul></div></div>';
    };
    $filled = fn (array $values) => collect($values)->contains(fn ($value) => filled($value));
    $card = 'rounded-xl border border-outline-variant/60 bg-white p-5';
@endphp

<style>
    /* Locked sections read like a profile; unlocked ones look like a form. */
    form[data-section] fieldset:disabled input:not([type=file]),
    form[data-section] fieldset:disabled select { background:#f4f7fa; color:#44546a; border-color:#e2e9f0; }
    form[data-section] fieldset:disabled label:has(input[type=file]) { display:none; }
    form[data-section].is-editing { box-shadow:0 0 0 2px #286da833; border-radius:.9rem; }
    form[data-section].is-editing [data-edit] { display:none; }
    form[data-section].is-editing [data-actions] { display:flex; }
</style>

<div class="grid items-start gap-4 xl:grid-cols-2">
    <div class="space-y-4">
        {{-- Public Header --}}
        <form method="POST" action="{{ route('school-settings.agency') }}" enctype="multipart/form-data" data-section="header" @class(['is-editing' => $editingSection === 'header'])>
            @csrf <input type="hidden" name="_section" value="header"><input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <section class="{{ $card }}">
                {!! $heading('account_balance', 'Public Header', 'Printed at the top of every official document.') !!}
                <fieldset class="mt-4 grid gap-4 sm:grid-cols-[168px_1fr]" @disabled($editingSection !== 'header')>
                    {!! $logoBlock('logo-agency', 'department_logo', 'Agency / Department logo', $sharedDepartmentLogo?->path ?? $agency->department_logo_path, true) !!}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold sm:col-span-2">Republic name<input name="republic_name" value="{{ old('republic_name', $agency->republic_name ?: 'Republic of the Philippines') }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">Department / Agency<input name="department_name" required value="{{ old('department_name', $agency->department_name ?: 'Department of Education') }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">Department / Agency address<input name="address" value="{{ old('address', $agency->address) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Email<input type="email" name="email" value="{{ old('email', $agency->email) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Phone<input name="phone" value="{{ old('phone', $agency->phone) }}" class="{{ $input }}"></label>
                    </div>
                </fieldset>
                {!! $actions($filled([$agency->department_name, $agency->address, $agency->email, $agency->phone, $agency->department_logo_path])) !!}
            </section>
        </form>

        {{-- Division Office Details, with the District Office below it --}}
        <form method="POST" action="{{ route('school-settings.agency') }}" enctype="multipart/form-data" data-section="division" @class(['is-editing' => $editingSection === 'division'])>
            @csrf <input type="hidden" name="_section" value="division"><input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <section class="{{ $card }}">
                {!! $heading('apartment', 'Division Office Details', 'The schools division and district office this school reports to.') !!}
                <fieldset class="mt-4 grid gap-4 sm:grid-cols-[168px_1fr]" @disabled($editingSection !== 'division')>
                    {!! $logoBlock('logo-division', 'division_logo', 'Division office logo', $sharedDivisionLogo?->path ?? $agency->division_logo_path, true) !!}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold sm:col-span-2">Region<input name="region_name" list="place-regions" autocomplete="off" value="{{ old('region_name', $agency->region_name) }}" placeholder="e.g. Region XII" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">School Division Office<input name="division_office" list="place-divisions" autocomplete="off" value="{{ old('division_office', $agency->division_office) }}" placeholder="e.g. Schools Division Office of Cotabato" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">Division address<input name="division_address" value="{{ old('division_address', $agency->division_address) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Email<input type="email" name="division_email" value="{{ old('division_email', $agency->division_email) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Contact number<input name="division_phone" value="{{ old('division_phone', $agency->division_phone) }}" class="{{ $input }}"></label>

                        <div class="my-1 flex items-center gap-3 sm:col-span-2" role="separator" aria-label="District Office"><span class="h-px flex-1 bg-outline-variant/60"></span><span class="text-[10px] font-bold uppercase tracking-[.14em] text-on-surface-variant">District Office</span><span class="h-px flex-1 bg-outline-variant/60"></span></div>

                        <label class="block text-xs font-bold sm:col-span-2">District Office<input name="district_name" list="place-districts" autocomplete="off" value="{{ old('district_name', $agency->district_name) }}" placeholder="e.g. District III" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">District address<input name="district_address" value="{{ old('district_address', $agency->district_address) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Email<input type="email" name="district_email" value="{{ old('district_email', $agency->district_email) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Contact no.<input name="district_phone" value="{{ old('district_phone', $agency->district_phone) }}" class="{{ $input }}"></label>
                    </div>
                </fieldset>
                {!! $actions($filled([$agency->region_name, $agency->division_office, $agency->division_address, $agency->division_email, $agency->division_phone, $agency->division_logo_path, $agency->district_name, $agency->district_address, $agency->district_email, $agency->district_phone])) !!}
            </section>
        </form>
    </div>

    <div class="space-y-4">
        {{-- School details --}}
        <form method="POST" action="{{ route('school-settings.school') }}" enctype="multipart/form-data" data-section="school" @class(['is-editing' => $editingSection === 'school'])>
            @csrf <input type="hidden" name="_section" value="school"><input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <section class="{{ $card }}">
                {!! $heading('school', 'School details', 'Identity and contact of the school.') !!}
                <fieldset class="mt-4 grid gap-4 sm:grid-cols-[168px_1fr]" @disabled($editingSection !== 'school')>
                    {!! $logoBlock('logo-school', 'school_logo', 'School logo', $selectedSchool->logo_path) !!}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold sm:col-span-2">School ID <span class="font-normal text-on-surface-variant">(auto-generated)</span><input value="{{ $selectedSchool->code }}" readonly tabindex="-1" class="{{ $readonly }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">School name<input name="name" required value="{{ old('name', $selectedSchool->name) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">School type<input name="school_type" value="{{ old('school_type', $selectedSchool->school_type) }}" placeholder="Elementary / Integrated" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold sm:col-span-2">School address<input name="address" value="{{ old('address', $selectedSchool->address) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Contact no.<input name="contact_number" value="{{ old('contact_number', $selectedSchool->contact_number) }}" class="{{ $input }}"></label>
                        <label class="block text-xs font-bold">Email<input type="email" name="contact_email" value="{{ old('contact_email', $selectedSchool->contact_email) }}" class="{{ $input }}"></label>
                    </div>
                </fieldset>
                {!! $actions($filled([$selectedSchool->school_type, $selectedSchool->address, $selectedSchool->contact_number, $selectedSchool->contact_email, $selectedSchool->logo_path])) !!}
            </section>
        </form>
    </div>
</div>

<script>
    // Edit unlocks a section; Cancel puts it back exactly as it was saved.
    document.querySelectorAll('form[data-section]').forEach((form) => {
        const fieldset = form.querySelector('fieldset');
        const start = () => { form.classList.add('is-editing'); fieldset.disabled = false; fieldset.querySelector('input:not([readonly]):not([type=file]):not([type=hidden])')?.focus(); };
        form.querySelector('[data-edit]')?.addEventListener('click', start);
        form.querySelector('[data-cancel]')?.addEventListener('click', () => { window.location.href = window.location.pathname + window.location.search; });
    });
</script>
@include('partials.place-suggestions')
