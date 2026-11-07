{{-- The ProcMS brand: the owner's logo (public/images/procurems-logo.png). On the dark menus and panels the lettering is white (procurems-logo-light.png, the same logo with the dark lettering made light); no box around it. $style: 'sidebar' (default), 'register' (pre-registration panel) or 'login' (light card, the original colours). --}}
@php($style = $style ?? 'sidebar')
@if($style === 'login')
    <div class="mb-8 flex justify-center"><img src="{{ asset('images/procurems-logo.png') }}" alt="ProcMS - Plan. Procure. Finance. Report." class="h-24 w-auto sm:h-28"></div>
@elseif($style === 'register')
    <div class="relative"><img src="{{ asset('images/procurems-logo-light.png') }}" alt="ProcMS - Plan. Procure. Finance. Report." class="h-16 w-auto"></div>
@else
    <div class="-mx-3 mb-6"><img src="{{ asset('images/procurems-logo-light.png') }}" alt="ProcMS - Plan. Procure. Finance. Report." class="h-auto w-full"></div>
@endif
