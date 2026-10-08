@props(['stages'])
<ol {{ $attributes->class('civic-stage-rail') }} aria-label="Procurement progress">
    @foreach($stages as $stage)
        <li class="civic-stage civic-stage--{{ $stage['state'] }}" aria-label="{{ $stage['accessible_label'] }}" @if($stage['state'] === 'current') aria-current="step" @endif>
            <span class="civic-stage-marker" aria-hidden="true">@if($stage['state'] === 'complete')✓@else{{ $loop->iteration }}@endif</span>
            <span class="civic-stage-copy"><strong>{{ $stage['label'] }}</strong><small>{{ str($stage['state'])->replace('_', ' ')->title() }}</small></span>
        </li>
    @endforeach
</ol>
