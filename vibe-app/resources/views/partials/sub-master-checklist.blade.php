{{-- The access checklist of a Sub-master. $selected: the keys that are on. --}}
@php
    use App\Support\SubMasterAccess;
    $groups = ['Work areas' => SubMasterAccess::WORK_AREAS, 'Management' => SubMasterAccess::MANAGEMENT_AREAS, 'Switches (off by default)' => SubMasterAccess::SWITCHES];
@endphp
<input type="hidden" name="access_present" value="1">
<div class="space-y-4" data-checklist>
    @foreach($groups as $title => $items)
        <fieldset class="rounded border border-outline-variant/40 p-3" data-checklist-group>
            <legend class="px-1 text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ $title }}</legend>
            <div class="mb-2 flex gap-2 text-[11px] font-semibold">
                <button type="button" data-check-all class="rounded border border-primary/40 px-2 py-0.5 text-primary hover:bg-primary hover:text-white">Select all</button>
                <button type="button" data-check-none class="rounded border border-outline-variant px-2 py-0.5 hover:bg-surface-container">Clear</button>
            </div>
            <div class="grid gap-1.5 sm:grid-cols-2">
                @foreach($items as $key => $label)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="access[]" value="{{ $key }}" @checked(in_array($key, $selected, true)) class="h-4 w-4 rounded border-outline-variant text-primary">{{ $label }}</label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
<script>
    document.querySelectorAll('[data-checklist-group]').forEach((group) => {
        if (group.dataset.wired) return;
        group.dataset.wired = '1';
        group.querySelector('[data-check-all]').addEventListener('click', () => group.querySelectorAll('input[type=checkbox]').forEach((box) => { box.checked = true; }));
        group.querySelector('[data-check-none]').addEventListener('click', () => group.querySelectorAll('input[type=checkbox]').forEach((box) => { box.checked = false; }));
    });
</script>
