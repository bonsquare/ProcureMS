{{-- The header block of the cash report print pages. Expects $report (the view data of CashReportController::reportViewData): school, agency, officer, bank, fundCluster, period, reportNo, sheetNo; $title; $appendix (optional); $showOfficer (optional). --}}
@php
    $school = $report['school'];
    $agency = $report['agency'];
    $region = $school->region ?: $agency?->region_name;
    $division = $school->division ?: ($agency?->division_name ?: $agency?->division_office);
    [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($school, $agency);
    $officer = $report['officer'];
@endphp
@if(! empty($appendix))<div class="appendix">{{ $appendix }}</div>@endif
<header class="masthead{{ ! empty($compact) ? ' masthead-compact' : '' }}">
    <div class="masthead-logo masthead-logo-left">@if($leftLogo)<img class="official-logo" src="{{ $leftLogo }}" alt="" onerror="this.remove()">@endif</div>
    <div>
        <div>Republic of the Philippines</div>
        <div class="dept">Department of Education</div>
        @if($region)<div class="line">{{ $region }}</div>@endif
        @if($division)<div class="line">{{ $division }}</div>@endif
    </div>
    <div class="masthead-logo masthead-logo-right">@if($rightLogo)<img class="official-logo" src="{{ $rightLogo }}" alt="" onerror="this.remove()">@endif</div>
</header>
<div class="doc-title">
    <h1>{{ $title }}</h1>
    <p>Period Covered: {{ $report['period'] }}</p>
</div>
@if(! empty($minimal))
<p style="margin:4px 0 8px;font-weight:bold">Entity Name: <span style="text-decoration:underline">{{ mb_strtoupper($school->name) }}</span></p>
@else
<table class="info{{ ! empty($compact) ? ' compact' : '' }}{{ ! empty($compact) && ! empty($showOfficer) ? ' officer' : '' }}">
    <tr><td class="label">Entity Name:</td><td class="value underlined">{{ $school->name }}</td><td class="label" @if(empty($compact)) style="width:20%" @endif>Report No.:</td><td class="value underlined">{{ $report['reportNo'] }}</td></tr>
    <tr><td class="label">Fund Cluster:</td><td class="value underlined">{{ $report['fundCluster'] }}</td><td class="label">Sheet No.:</td><td class="value underlined">@if($report['sheetNo'] === '' && ! empty($autoSheet))<span data-sheet-no>1 of 1</span>@else{{ $report['sheetNo'] }}@endif</td></tr>
    <tr>@if(! empty($compact) && empty($showOfficer))<td colspan="2" class="bank-line"><span class="label">Bank Name / Account No.:</span> <span class="value">{{ $report['bank'] }}</span></td>@else<td class="label">Bank Name / Account No.:</td><td class="value{{ ! empty($compact) ? ' underlined' : '' }}">{{ $report['bank'] }}</td>@endif @if(! empty($showOfficer))<td class="label">Accountable Officer:</td><td class="value underlined">{{ $officer?->name }}</td>@else<td></td><td></td>@endif</tr>
    @if(! empty($showOfficer))
    <tr><td class="label">Station:</td><td class="value underlined">{{ $school->name }}</td><td class="label">Official Designation:</td><td class="value underlined">{{ $officer?->position }}</td></tr>
    @endif
</table>
@endif
