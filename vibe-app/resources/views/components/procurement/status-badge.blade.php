@props(['label', 'tone' => 'neutral'])
<span {{ $attributes->class(['civic-status', 'civic-status--'.$tone]) }} aria-label="Status: {{ $label }}">{{ $label }}</span>
