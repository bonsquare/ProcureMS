{{-- Pages that print with their own CSS (not the shared print engine): the school's default print margins, when it has set them. --}}
@php($printMargin = app(\App\Services\PrintSettings::class)->pageMargin($printSchoolId ?? auth()->user()?->school_id))
@if($printMargin)<style>@page { margin: {{ $printMargin['top'] }}mm {{ $printMargin['right'] }}mm {{ $printMargin['bottom'] }}mm {{ $printMargin['left'] }}mm; }</style>@endif
