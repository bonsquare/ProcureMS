<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORS {{ $report->ors_number }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; }
        .print-actions { width: 8.5in; margin: 12px auto; display: flex; justify-content: space-between; align-items: center; font: 13px Arial, sans-serif; }
        .print-actions a, .print-actions button { border: 1px solid #00236f; background: #fff; color: #00236f; border-radius: 3px; padding: 8px 13px; text-decoration: none; cursor: pointer; }
        .print-actions button { background: #00236f; color: #fff; }
        .sheet { width: 8.5in; height: 13in; margin: 0 auto 12px; background: #fff; }
        .sheet svg { display: block; width: 100%; height: 100%; }
        .sheet line { stroke: #000; stroke-linecap: square; }
        .sheet text { fill: #000; }
        .sheet.no-status .ors-status { display: none; }
        .ph { font-family: 'Times New Roman', Times, serif; color: #000; }
        @media print { html, body { background: #fff; } .print-actions { display: none; } .sheet { margin: 0; } }
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
</head>
<body>
@php
    $pr = $report->procurementRequest;
    $fit = fn ($s, $w, $size) => min($size, round($w / max(mb_strlen((string) $s), 1) / 0.5, 1));
    $date = ($report->submitted_at ?? $report->created_at)?->format('F d, Y');
    $line = $report->chargedLine();
    $fund = $report->source_of_fund ?: $pr?->source_of_fund ?: $line?->source_of_fund;
    $division = $report->school?->division ?: ($agency->division_name ?: $agency->division_office);
    $payee = $report->payee ?: ($pr?->school?->name ?? $report->school?->name);
    $office = $report->payee_office; // the Payee, Office and Address lines all describe the payee
    $address = $report->payee_address;
    $rc = $report->responsibility_center_code ?: $pr?->responsibility_center_code ?: $line?->responsibility_center;
    $uacs = ($report->ors_show_uacs ?? true) ? $line?->uacs_code : null; // written in the UACS Object Code column only when the box was ticked
    $pap = ''; // MFO/PAP is left blank
    $particulars = $report->purpose . ($report->notes ? "\n" . $report->notes : '');
    $amount = number_format((float) $report->amount, 2);
    $paidAmount = $report->paid_at ? $amount : '';
    $statusParticulars = $report->purpose;
    // The Date column of Section C holds the date of the ORS, which is the date of its Purchase Order (specs 32.1), on one line.
    $statusDate = ($report->submitted_at ?? $report->created_at)?->format('M d, Y');
    $orsReference = preg_replace('/^ORS-?/i', '', (string) $report->ors_number); // the ORS/JEV/Check/ADA/TRA No. of Section C: the ORS number, without the letters
    $requesterName = strtoupper((string) ($requester?->name ?: $report->school?->school_head));
    $requesterRole = $requester?->position ?: 'School Head';
    $certifierName = strtoupper((string) ($budgetOfficer?->name ?? ''));
    $certifierRole = $budgetOfficer?->position ?: 'Disbursing Officer';
    $showStatus = true; // Section C, Status of Obligation, is always part of the ORS
@endphp
@include('partials.official-toolbar', ['closeUrl' => route('budget'), 'context' => 'Obligation Request · '.$report->ors_number])
<div class="sheet{{ $showStatus ? '' : ' no-status' }}" data-official-page data-single-page data-doc="ors" data-paper="longbond"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 612 936" preserveAspectRatio="xMidYMid meet" stroke="#000" fill="none">
<g fill="none">
<line x1="17.39" y1="44.92" x2="17.39" y2="{{ $showStatus ? '883.92' : '683.23' }}" stroke-width="1.45"/>
<line x1="16.66" y1="528.18" x2="52.17" y2="528.18" stroke-width="1.45"/>
<line x1="51.44" y1="496.30" x2="51.44" y2="528.91" stroke-width="1.45"/>
<line class="ors-status" x1="51.44" y1="682.51" x2="51.44" y2="703.7" stroke-width="1.45"/>
<line class="ors-status" x1="254.31" y1="702.3" x2="254.31" y2="883.92" stroke-width="1.45"/>
<line x1="81.15" y1="626.72" x2="318.07" y2="626.72" stroke-width="1.45"/>
<line x1="317.34" y1="496.30" x2="317.34" y2="683.95" stroke-width="1.45"/>
<line x1="316.62" y1="528.18" x2="355.74" y2="528.18" stroke-width="1.45"/>
<line x1="355.02" y1="496.30" x2="355.02" y2="528.91" stroke-width="1.45"/>
<line x1="390.52" y1="44.92" x2="390.52" y2="120.27" stroke-width="1.45"/>
<line x1="16.66" y1="45.65" x2="594.84" y2="45.65" stroke-width="1.45"/>
<line x1="16.66" y1="119.55" x2="594.84" y2="119.55" stroke-width="1.45"/>
<line x1="16.66" y1="197.07" x2="594.84" y2="197.07" stroke-width="1.45"/>
<line x1="16.66" y1="226.78" x2="594.84" y2="226.78" stroke-width="1.45"/>
<line x1="16.66" y1="497.03" x2="594.84" y2="497.03" stroke-width="1.45"/>
<line x1="390.52" y1="626.72" x2="594.84" y2="626.72" stroke-width="1.45"/>
<line x1="16.66" y1="683.23" x2="594.84" y2="683.23" stroke-width="1.45"/>
<line class="ors-status" x1="16.66" y1="703" x2="594.84" y2="703" stroke-width="1.45"/>
<line class="ors-status" x1="16.66" y1="721.63" x2="594.84" y2="721.63" stroke-width="1.45"/>
<line class="ors-status" x1="253.58" y1="768.72" x2="594.84" y2="768.72" stroke-width="1.45"/>
<line class="ors-status" x1="16.66" y1="786.84" x2="594.84" y2="786.84" stroke-width="1.45"/>
<line class="ors-status" x1="16.66" y1="883.20" x2="594.84" y2="883.20" stroke-width="1.45"/>
<line x1="594.11" y1="44.92" x2="594.11" y2="{{ $showStatus ? '883.92' : '683.23' }}" stroke-width="1.45"/>
<line class="ors-status" x1="81.66" y1="722.35" x2="81.66" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="81.66" y1="787.56" x2="81.66" y2="882.48" stroke-width="0.72"/>
<line x1="115.56" y1="120.27" x2="115.56" y2="196.35" stroke-width="0.72"/>
<line x1="115.56" y1="197.80" x2="115.56" y2="226.05" stroke-width="0.72"/>
<line x1="115.56" y1="227.50" x2="115.56" y2="496.30" stroke-width="0.72"/>
<line class="ors-status" x1="175" y1="722.35" x2="175" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="175" y1="787.56" x2="175" y2="882.48" stroke-width="0.72"/>
<line x1="317.71" y1="197.80" x2="317.71" y2="226.05" stroke-width="0.72"/>
<line x1="317.71" y1="227.50" x2="317.71" y2="496.30" stroke-width="0.72"/>
<line class="ors-status" x1="317.71" y1="722.35" x2="317.71" y2="768.00" stroke-width="0.72"/>
<line class="ors-status" x1="317.71" y1="769.45" x2="317.71" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="317.71" y1="787.56" x2="317.71" y2="882.48" stroke-width="0.72"/>
<line x1="390.88" y1="197.80" x2="390.88" y2="226.05" stroke-width="0.72"/>
<line x1="390.88" y1="227.50" x2="390.88" y2="496.30" stroke-width="0.72"/>
<line class="ors-status" x1="390.88" y1="722.35" x2="390.88" y2="768.00" stroke-width="0.72"/>
<line class="ors-status" x1="390.88" y1="769.45" x2="390.88" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="390.88" y1="787.56" x2="390.88" y2="882.48" stroke-width="0.72"/>
<line x1="463.34" y1="197.80" x2="463.34" y2="226.05" stroke-width="0.72"/>
<line x1="463.34" y1="227.50" x2="463.34" y2="496.30" stroke-width="0.72"/>
<line class="ors-status" x1="463.34" y1="722.35" x2="463.34" y2="768.00" stroke-width="0.72"/>
<line class="ors-status" x1="463.34" y1="769.45" x2="463.34" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="463.34" y1="787.56" x2="463.34" y2="882.48" stroke-width="0.72"/>
<line class="ors-status" x1="526.37" y1="737.57" x2="526.37" y2="768.00" stroke-width="0.72"/>
<line class="ors-status" x1="526.37" y1="769.45" x2="526.37" y2="786.11" stroke-width="0.72"/>
<line class="ors-status" x1="526.37" y1="787.56" x2="526.37" y2="882.48" stroke-width="0.72"/>
<line x1="18.11" y1="147.44" x2="593.39" y2="147.44" stroke-width="0.72"/>
<line x1="18.11" y1="172.08" x2="593.39" y2="172.08" stroke-width="0.72"/>
<line class="ors-status" x1="462.97" y1="737.93" x2="593.39" y2="737.93" stroke-width="0.72"/>
</g>
<g fill="#000" stroke="none">
<text x="544.11" y="34.05" font-family="'Times New Roman',Times,serif" font-size="10.6" font-style="italic" xml:space="preserve">Appendix 11</text>
<text x="82.81" y="68.6" font-family="'Times New Roman',Times,serif" font-size="13.5" font-weight="bold" xml:space="preserve">OBLIGATION REQUEST AND STATUS</text>
<text x="103.04" y="90.1" font-family="'Times New Roman',Times,serif" font-size="13.5" font-weight="bold" xml:space="preserve">DEPARTMENT OF EDUCATION</text>
<text x="466.4" y="87.67" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="12" xml:space="preserve">Date :</text>
<text x="52.82" y="137.66" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Payee</text>
<text x="51.96" y="163.74" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Office</text>
<text x="47.66" y="188.38" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Address</text>
<text x="20.49" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Responsibility Center</text>
<text x="193.92" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Particulars</text>
<text x="332.05" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">MFO/PAP</text>
<text x="397.46" y="208.66" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">UACS Object</text>
<text x="415.90" y="221.71" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Code</text>
<text x="511.14" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Amount</text>
<text x="118.10" y="518.28" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Charges to appropriation/alloment are</text>
<text x="402.15" y="518.28" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">  Allotment available and obligated</text>
<text class="ors-status" x="39.2" y="757.86" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date</text>
<text class="ors-status" x="105.3" y="757.86" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Particulars</text>
<text class="ors-status" x="214.65" y="751.34" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="9.4" xml:space="preserve">ORS/JEV/Check/</text>
<text class="ors-status" x="214.65" y="764.38" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="9.4" xml:space="preserve">ADA/TRA No.</text>
<text class="ors-status" x="263.47" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Obligation</text>
<text class="ors-status" x="337.48" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Payable</text>
<text class="ors-status" x="408.52" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Payment</text>
<text class="ors-status" x="527" y="732.4" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="11.6" font-weight="bold" xml:space="preserve">Balance</text>
<text class="ors-status" x="468.25" y="757.13" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Not Yet Due</text>
<text class="ors-status" x="542.22" y="750.61" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Due and</text>
<text class="ors-status" x="533.22" y="763.65" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Demandable</text>
<text class="ors-status" x="280.29" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(a)</text>
<text class="ors-status" x="348.10" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(b)</text>
<text class="ors-status" x="421.22" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(c)</text>
<text class="ors-status" x="484.53" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(a-b)</text>
<text class="ors-status" x="549.74" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(b-c)</text>
<text x="54.34" y="532.05" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">necessary, lawful and under my direct supervision;and</text>
<text x="357.92" y="532.05" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve"> for the purpose/adjustment necessary as</text>
<text x="54.34" y="545.09" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">supporting documents valid, proper and legal</text>
<text x="357.92" y="545.09" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve"> indicated above</text>
<text x="20.29" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Signature</text>
<text x="84.5" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="20.29" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Printed Name</text>
<text x="84.5" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="20.29" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Position</text>
<text x="84.5" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="20.29" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date</text>
<text x="84.5" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="320.24" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Signature</text>
<text x="384.5" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="320.24" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Printed Name</text>
<text x="384.5" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="320.24" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Position</text>
<text x="384.5" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="320.24" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date</text>
<text x="384.5" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">:</text>
<text x="199.3" y="667.29" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($date, 150, 10.6) }}">{{ $date }}</text>
<text x="492.4" y="667.29" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($date, 190, 10.6) }}">{{ $date }}</text>
<text x="29.25" y="515.86" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">A.</text>
<text x="331.31" y="515.86" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">B.</text>
<text x="61.57" y="518.28" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Certified:</text>
<text x="357.92" y="518.28" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Certified:</text>
<text class="ors-status" x="29.25" y="696.4" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">C.</text>
<text class="ors-status" x="258.44" y="696.4" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">STATUS OF OBLIGATION</text>
<text class="ors-status" x="139" y="716.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="11.6" font-weight="bold" xml:space="preserve">Reference</text>
<text class="ors-status" x="424" y="716.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="11.6" font-weight="bold" xml:space="preserve">Amount</text>
<!-- header fields -->
<text x="466.4" y="67.4" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="12" xml:space="preserve">Serial No. :</text>
<text x="472" y="67.4" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($report->ors_number ?: '______________', 116, 12.5) }}" font-weight="bold" text-decoration="underline" xml:space="preserve">{{ $report->ors_number ?: '______________' }}</text>
<text x="472" y="87.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($date, 116, 12.5) }}" font-weight="bold" text-decoration="underline">{{ $date }}</text>
<text x="466.4" y="108.7" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="12" xml:space="preserve">Fund Cluster :</text>
<text x="472" y="108.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($fund, 116, 12.5) }}" font-weight="bold" text-decoration="underline">{{ $fund }}</text>
<text x="204" y="111.6" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="11.6" font-weight="bold">{{ $division }}</text>
<!-- payee / office / address -->
<text x="118.1" y="137.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($payee, 470, 11.6) }}" font-weight="bold">{{ $payee }}</text>
<text x="118.1" y="163.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($office, 470, 11.6) }}" font-weight="bold">{{ $office }}</text>
<text x="118.1" y="188.4" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($address, 470, 11.6) }}" font-weight="bold">{{ $address }}</text>
<!-- responsibility center / particulars -->
<text x="66.3" y="263.7" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($rc, 92, 10.6) }}">{{ $rc }}</text>
<text x="362.5" y="263.7" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($pap, 58, 9) }}">{{ $pap }}</text>
<text x="432.5" y="263.7" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($uacs, 64, 10) }}">{{ $uacs }}</text>
<foreignObject x="123.1" y="253.7" width="188" height="240"><div xmlns="http://www.w3.org/1999/xhtml" style="font:11.4px/13.05px 'Times New Roman',Times,serif;color:#000;overflow:hidden;height:240px;white-space:pre-wrap;word-wrap:break-word">{{ $particulars }}</div></foreignObject>
<!-- amount -->
<text x="469.1" y="263.7" font-family="'Times New Roman',Times,serif" font-size="10.6">₱</text>
<text x="588.0" y="263.7" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $amount }}</text>
<text x="469.4" y="493.4" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">₱</text>
<text x="587.7" y="493.4" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $amount }}</text>
<!-- A. requested by -->
<text x="199.3" y="623.1" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($requesterName, 230, 9.7) }}" font-weight="bold">{{ $requesterName }}</text>
<text x="199.3" y="641.2" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($requesterRole, 230, 9.7) }}">{{ $requesterRole }}</text>
<!-- B. certified by -->
<text x="492.4" y="622.4" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($certifierName, 190, 10.6) }}" font-weight="bold">{{ $certifierName }}</text>
<text x="492.4" y="641.9" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($certifierRole, 190, 10.6) }}">{{ $certifierRole }}</text>
<!-- C. status of obligation -->
<text class="ors-status" x="49.2" y="810.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($statusDate, 58, 9.4) }}" font-weight="bold">{{ $statusDate }}</text>
<foreignObject class="ors-status" x="84.7" y="801" width="87.3" height="80.5"><div xmlns="http://www.w3.org/1999/xhtml" style="font:bold 9.5px/11.5px 'Times New Roman',Times,serif;color:#000;text-align:center;overflow:hidden;height:80.5px;word-wrap:break-word;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical">{{ $statusParticulars }}</div></foreignObject>
<text class="ors-status" x="214.7" y="810.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($orsReference, 72, 10.6) }}" font-weight="bold">{{ $orsReference }}</text>
<text class="ors-status" x="293" y="810.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $amount }}</text>
<text class="ors-status" x="366" y="810.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $amount }}</text>
<text class="ors-status" x="438.7" y="810.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $paidAmount }}</text>

</g>
</svg></div>
</body>
</html>
