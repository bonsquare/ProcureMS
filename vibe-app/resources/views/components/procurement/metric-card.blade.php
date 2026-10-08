@props(['label', 'value', 'note' => null, 'icon' => 'analytics', 'tone' => 'action', 'href' => null])
@php $tag = $href ? 'a' : 'article'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class('civic-metric-card') }}>
    <div><p class="civic-metric-card__label">{{ $label }}</p><p class="civic-metric-card__value">{{ $value }}</p>@if($note)<p class="civic-metric-card__note">{{ $note }}</p>@endif</div>
    <span class="civic-metric-card__icon civic-tone--{{ $tone }} material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
</{{ $tag }}>
