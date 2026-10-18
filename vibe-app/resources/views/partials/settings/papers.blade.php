@php
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $own = $selectedSchool->checklistItems()->orderBy('ors_kind')->orderBy('stage')->orderBy('id')->get();
@endphp
<div class="grid items-start gap-5 xl:grid-cols-[1fr_380px]">
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><h2 class="text-sm font-bold text-primary">Your own papers</h2><p class="text-xs text-on-surface-variant">Papers your school adds to the liquidation checklist, on top of the standard ones. Accounting ticks them in the Papers window of each ORS.</p></div>
        @forelse($own as $paper)
            <div class="flex items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-3 last:border-0">
                <div><p class="text-sm font-semibold">{{ $paper->label }}</p><p class="text-xs text-on-surface-variant">{{ \App\Support\LiquidationChecklist::KINDS[$paper->ors_kind] ?? $paper->ors_kind }} · {{ \App\Support\LiquidationChecklist::STAGES[$paper->stage] ?? $paper->stage }}</p></div>
                <form method="POST" action="{{ route('school-settings.papers.destroy', $paper) }}" onsubmit="return confirm('Remove this paper from the checklist?')">@csrf @method('DELETE')<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><button class="rounded-lg px-3 py-1.5 text-xs font-semibold text-error ring-1 ring-error/30 hover:bg-error hover:text-white">Remove</button></form>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-on-surface-variant">No own papers yet. The standard papers of each kind of expense are always on the checklist.</p>
        @endforelse
    </section>
    <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm">
        <div class="border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3"><h2 class="text-sm font-bold text-primary">Add a paper</h2></div>
        <form method="POST" action="{{ route('school-settings.papers.store') }}" class="grid gap-3 p-5">@csrf <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <label class="text-xs font-semibold text-on-surface-variant">Paper<input name="label" required maxlength="120" value="{{ old('label') }}" class="{{ $field }}"></label>
            <label class="text-xs font-semibold text-on-surface-variant">Type of expense<select name="ors_kind" class="{{ $field }}">@foreach(\App\Support\LiquidationChecklist::KINDS as $key => $label)<option value="{{ $key }}" @selected(old('ors_kind', 'goods') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-on-surface-variant">Needed<select name="stage" class="{{ $field }}">@foreach(\App\Support\LiquidationChecklist::STAGES as $key => $label)<option value="{{ $key }}" @selected(old('stage', 'ors') === $key)>{{ $label }}</option>@endforeach</select></label>
            @if($errors->any())<p class="text-xs text-error">{{ $errors->first() }}</p>@endif
            <div><button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-container">Add paper</button></div>
        </form>
    </section>
</div>
