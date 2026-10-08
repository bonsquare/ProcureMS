@php
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($procurementRequest->school, $agency);
@endphp
<div class="official-header">
    @if($leftLogo)<img class="left-logo official-logo" src="{{ $leftLogo }}" alt="Department logo" onerror="this.remove()">@endif
    @if($rightLogo)<img class="right-logo official-logo" src="{{ $rightLogo }}" alt="School logo" onerror="this.remove()">@endif
    <div class="republic">{{ $agency?->republic_name ?: 'Republic of the Philippines' }}</div>
    <div class="department">{{ $agency?->department_name ?: 'Department of Education' }}</div>
    <div>{{ $procurementRequest->school?->region }}</div>
    <div>{{ $procurementRequest->school?->division }}</div>
    <div>{{ $procurementRequest->school?->district }}</div>
    <div class="school">{{ strtoupper($procurementRequest->school?->name) }}</div>
    <div class="rule"></div>
</div>
