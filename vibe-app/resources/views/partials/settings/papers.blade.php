@php
    $field = 'mt-1 w-full rounded-lg border border-outline-variant bg-white px-3 py-2 text-sm font-normal outline-none focus:border-action focus:ring-2 focus:ring-action/20';
    $own = $selectedSchool->checklistItems()->orderBy('ors_kind')->orderBy('stage')->orderBy('id')->get()->groupBy('ors_kind');
    $kindIcons = ['goods' => 'inventory_2', 'direct' => 'receipt_long', 'travel' => 'flight_takeoff', 'other' => 'folder_open'];
@endphp
<div class="mb-5">
    <h2 class="text-lg font-semibold text-on-surface">Documentary requirements for common government transactions</h2>
    <p class="mt-1 text-xs text-on-surface-variant">The papers each kind of expense needs before its ORS is approved and before its DV is created. The standard ones are always on the checklist; add the papers your school also requires. Accounting ticks them in the Papers window of each ORS.</p>
</div>
<div class="grid items-start gap-5 xl:grid-cols-[1fr_380px]">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach(\App\Support\LiquidationChecklist::KINDS as $kind => $kindLabel)
            @php
                $standard = collect(\App\Support\LiquidationChecklist::standard($kind));
                $mine = $own->get($kind, collect());
                $total = $standard->count() + $mine->count();
            @endphp
            <section class="overflow-hidden rounded-xl border border-outline-variant/60 bg-white shadow-sm" aria-labelledby="papers-{{ $kind }}">
                <div class="flex items-center gap-3 border-b border-outline-variant/40 bg-[#eef4fa] px-5 py-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $kindIcons[$kind] ?? 'description' }}</span></span>
                    <div class="min-w-0 flex-1"><h3 id="papers-{{ $kind }}" class="text-sm font-bold text-primary">{{ $kindLabel }}</h3><p class="text-xs text-on-surface-variant">{{ $total }} {{ \Illuminate\Support\Str::plural('paper', $total) }}@if($mine->count()) · {{ $mine->count() }} of your own @endif</p></div>
                </div>
                @foreach(\App\Support\LiquidationChecklist::STAGES as $stage => $stageLabel)
                    @php $rows = $standard->where('stage', $stage); $extra = $mine->where('stage', $stage); @endphp
                    @if($rows->isNotEmpty() || $extra->isNotEmpty())
                        <p class="border-b border-outline-variant/20 bg-surface-low px-5 py-1.5 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">{{ $stageLabel }}</p>
                        @foreach($rows as $paper)
                            <div class="flex items-center justify-between gap-3 border-b border-outline-variant/20 px-5 py-2.5 last:border-0">
                                <p class="text-sm">{{ $paper['label'] }}</p>
                                <span class="shrink-0 rounded-full bg-secondary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-secondary">{{ $paper['doc'] ? 'Proved by the document' : 'Standard' }}</span>
                            </div>
                        @endforeach
                        @foreach($extra as $paper)
                            <div class="flex items-center justify-between gap-3 border-b border-outline-variant/20 bg-primary/5 px-5 py-2.5 last:border-0">
                                <p class="text-sm font-semibold">{{ $paper->label }} <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">Yours</span></p>
                                <form data-guard="Remove this paper from the checklist" method="POST" action="{{ route('school-settings.papers.destroy', $paper) }}" onsubmit="return confirm('Remove this paper from the checklist?')">@csrf @method('DELETE')<input type="hidden" name="school_id" value="{{ $selectedSchool->id }}"><button class="rounded-lg px-3 py-1.5 text-xs font-semibold text-error ring-1 ring-error/30 hover:bg-error hover:text-white">Remove</button></form>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            </section>
        @endforeach
    </div>
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
