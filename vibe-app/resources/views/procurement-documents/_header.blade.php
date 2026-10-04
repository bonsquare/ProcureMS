@php
    $leftLogo = $agency?->department_logo_path ? asset('storage/'.$agency->department_logo_path) : asset('images/official-deped-logo.png');
    $rightLogo = $procurementRequest->school?->logo_path ? asset('storage/'.$procurementRequest->school->logo_path) : ($agency?->division_logo_path ? asset('storage/'.$agency->division_logo_path) : asset('images/official-school-logo.png'));
@endphp
<div class="official-header">
    @if($leftLogo)<img class="left-logo" src="{{ $leftLogo }}" alt="Department logo">@endif
    @if($rightLogo)<img class="right-logo" src="{{ $rightLogo }}" alt="School logo">@endif
    <div class="republic">{{ $agency?->republic_name ?: 'Republic of the Philippines' }}</div>
    <div class="department">{{ $agency?->department_name ?: 'Department of Education' }}</div>
    <div>{{ $procurementRequest->school?->region }}</div>
    <div>{{ $procurementRequest->school?->division }}</div>
    <div>{{ $procurementRequest->school?->district }}</div>
    <div class="school">{{ strtoupper($procurementRequest->school?->name) }}</div>
    <div class="rule"></div>
</div>
