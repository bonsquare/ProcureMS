@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->class('civic-empty-state') }}>
    <span class="material-symbols-outlined civic-empty-state__icon" aria-hidden="true">{{ $icon }}</span>
    <h3>{{ $title }}</h3>
    @if($description)<p>{{ $description }}</p>@endif
    @if(trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
