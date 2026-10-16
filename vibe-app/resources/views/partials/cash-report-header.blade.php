{{-- The header block of the cash report print pages. Expects $report (the view data of CashReportController::reportViewData): school, agency, officer, bank, fundCluster, period, reportNo, sheetNo; $title; $appendix (optional); $showOfficer (optional). --}}
@php
    $school = $report['school'];
    $agency = $report['agency'];
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $officer = $report['officer'];
@endphp
<header class="masthead">
    <div class="masthead-logo masthead-logo-left">@if($leftLogo)<img class="official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif</div>
    <div>
        <div>Republic of the Philippines</div>
        <div class="dept">Department of Education</div>
        @if($region)<div class="line">{{ $region }}</div>@endif
        @if($division)<div class="line">{{ $division }}</div>@endif
    </div>
    <div class="masthead-logo masthead-logo-right">@if($rightLogo)<img class="official-logo" src="{{ $rightLogo }}" alt="" onerror="this.remove()">@endif</div>
</header>
@if(! empty($appendix))<div class="appendix">{{ $appendix }}</div>@endif
<div class="doc-title">
    <h1>{{ $title }}</h1>
    <p>Period Covered: {{ $report['period'] }}</p>
</div>
<table class="info">
    <tr><td class="label">Entity Name:</td><td class="value">{{ $school->name }}</td><td class="label" style="width:20%">Report No.:</td><td class="value">{{ $report['reportNo'] }}</td></tr>
    <tr><td class="label">Fund Cluster:</td><td class="value">{{ $report['fundCluster'] }}</td><td class="label">Sheet No.:</td><td class="value">{{ $report['sheetNo'] }}</td></tr>
    <tr><td class="label">Bank Name / Account No.:</td><td class="value">{{ $report['bank'] }}</td>@if(! empty($showOfficer))<td class="label">Accountable Officer:</td><td class="value">{{ $officer?->name }}</td>@else<td></td><td></td>@endif</tr>
    @if(! empty($showOfficer))
    <tr><td class="label">Station:</td><td class="value">{{ $school->name }}</td><td class="label">Official Designation:</td><td class="value">{{ $officer?->position }}</td></tr>
    @endif
</table>
