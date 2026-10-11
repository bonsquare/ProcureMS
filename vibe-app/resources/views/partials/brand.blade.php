{{-- The ProcureMS brand (docs: the logo in public/images). $style: 'sidebar' (default, navy sidebars), 'register' (navy panel) or 'login' (light card, full logo). --}}
@php($style = $style ?? 'sidebar')
@if($style === 'login')
    <div class="mb-8"><img src="{{ asset('images/procurems-logo.svg') }}" alt="ProcureMS - School Procurement &amp; Liquidation" class="h-16 w-auto"></div>
@elseif($style === 'register')
    <div class="relative flex items-center gap-3"><img src="{{ asset('images/procurems-emblem.svg') }}" alt="" class="h-11 w-11 shrink-0"><div><p class="text-xl font-bold leading-tight">ProcureMS</p><p class="text-xs text-white/70">School Pre-registration</p></div></div>
@else
    <div class="mb-8 flex items-center gap-3 px-2"><img src="{{ asset('images/procurems-emblem.svg') }}" alt="" class="h-9 w-9 shrink-0"><div><p class="text-lg font-bold tracking-tight">ProcureMS</p><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-white/55">Civic Operations</p></div></div>
@endif
