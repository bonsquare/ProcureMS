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
    $office = $report->school?->name;
    $address = $report->school?->address;
    $rc = $report->responsibility_center_code ?: $pr?->responsibility_center_code ?: $line?->responsibility_center;
    $uacs = $line?->uacs_code;
    $pap = $line?->program;
    $particulars = $report->purpose . ($report->notes ? "\n" . $report->notes : '');
    $amount = number_format((float) $report->amount, 2);
    $paidAmount = $report->paid_at ? $amount : '';
    $statusParticulars = $report->purpose;
    $requesterName = strtoupper((string) ($requester?->name ?: $report->school?->school_head));
    $requesterRole = $requester?->position ?: 'School Head';
    $certifierName = strtoupper((string) ($budgetOfficer?->name ?? ''));
    $certifierRole = $budgetOfficer?->position ?: 'Disbursing Officer';
@endphp
@include('partials.official-toolbar', ['closeUrl' => route('budget'), 'context' => 'Obligation Request · '.$report->ors_number])
<div class="sheet" data-official-page data-single-page data-doc="ors" data-paper="longbond"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 612 936" preserveAspectRatio="xMidYMid meet" stroke="#000" fill="none">
<g fill="none">
<line x1="17.39" y1="44.92" x2="17.39" y2="883.92" stroke-width="1.45"/>
<line x1="16.66" y1="528.18" x2="52.17" y2="528.18" stroke-width="1.45"/>
<line x1="51.44" y1="496.30" x2="51.44" y2="528.91" stroke-width="1.45"/>
<line x1="51.44" y1="682.51" x2="51.44" y2="709.31" stroke-width="1.45"/>
<line x1="254.31" y1="707.86" x2="254.31" y2="883.92" stroke-width="1.45"/>
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
<line x1="16.66" y1="708.59" x2="594.84" y2="708.59" stroke-width="1.45"/>
<line x1="16.66" y1="721.63" x2="594.84" y2="721.63" stroke-width="1.45"/>
<line x1="253.58" y1="768.72" x2="594.84" y2="768.72" stroke-width="1.45"/>
<line x1="16.66" y1="786.84" x2="594.84" y2="786.84" stroke-width="1.45"/>
<line x1="16.66" y1="883.20" x2="594.84" y2="883.20" stroke-width="1.45"/>
<line x1="594.11" y1="44.92" x2="594.11" y2="883.92" stroke-width="1.45"/>
<line x1="51.80" y1="722.35" x2="51.80" y2="786.11" stroke-width="0.72"/>
<line x1="51.80" y1="787.56" x2="51.80" y2="882.48" stroke-width="0.72"/>
<line x1="115.56" y1="120.27" x2="115.56" y2="196.35" stroke-width="0.72"/>
<line x1="115.56" y1="197.80" x2="115.56" y2="226.05" stroke-width="0.72"/>
<line x1="115.56" y1="227.50" x2="115.56" y2="496.30" stroke-width="0.72"/>
<line x1="159.03" y1="722.35" x2="159.03" y2="786.11" stroke-width="0.72"/>
<line x1="159.03" y1="787.56" x2="159.03" y2="882.48" stroke-width="0.72"/>
<line x1="317.71" y1="197.80" x2="317.71" y2="226.05" stroke-width="0.72"/>
<line x1="317.71" y1="227.50" x2="317.71" y2="496.30" stroke-width="0.72"/>
<line x1="317.71" y1="722.35" x2="317.71" y2="768.00" stroke-width="0.72"/>
<line x1="317.71" y1="769.45" x2="317.71" y2="786.11" stroke-width="0.72"/>
<line x1="317.71" y1="787.56" x2="317.71" y2="882.48" stroke-width="0.72"/>
<line x1="390.88" y1="197.80" x2="390.88" y2="226.05" stroke-width="0.72"/>
<line x1="390.88" y1="227.50" x2="390.88" y2="496.30" stroke-width="0.72"/>
<line x1="390.88" y1="722.35" x2="390.88" y2="768.00" stroke-width="0.72"/>
<line x1="390.88" y1="769.45" x2="390.88" y2="786.11" stroke-width="0.72"/>
<line x1="390.88" y1="787.56" x2="390.88" y2="882.48" stroke-width="0.72"/>
<line x1="463.34" y1="197.80" x2="463.34" y2="226.05" stroke-width="0.72"/>
<line x1="463.34" y1="227.50" x2="463.34" y2="496.30" stroke-width="0.72"/>
<line x1="463.34" y1="722.35" x2="463.34" y2="768.00" stroke-width="0.72"/>
<line x1="463.34" y1="769.45" x2="463.34" y2="786.11" stroke-width="0.72"/>
<line x1="463.34" y1="787.56" x2="463.34" y2="882.48" stroke-width="0.72"/>
<line x1="526.37" y1="737.57" x2="526.37" y2="768.00" stroke-width="0.72"/>
<line x1="526.37" y1="769.45" x2="526.37" y2="786.11" stroke-width="0.72"/>
<line x1="526.37" y1="787.56" x2="526.37" y2="882.48" stroke-width="0.72"/>
<line x1="18.11" y1="147.44" x2="593.39" y2="147.44" stroke-width="0.72"/>
<line x1="18.11" y1="172.08" x2="593.39" y2="172.08" stroke-width="0.72"/>
<line x1="462.97" y1="737.93" x2="593.39" y2="737.93" stroke-width="0.72"/>
</g>
<g fill="#000" stroke="none">
<text x="544.11" y="34.05" font-family="'Times New Roman',Times,serif" font-size="9.7" font-style="italic" xml:space="preserve">Appendix 11</text>
<text x="82.81" y="63.03" font-family="'Times New Roman',Times,serif" font-size="13.5" font-weight="bold" xml:space="preserve">OBLIGATION REQUEST AND STATUS</text>
<text x="103.04" y="92.02" font-family="'Times New Roman',Times,serif" font-size="13.5" font-weight="bold" xml:space="preserve">DEPARTMENT OF EDUCATION</text>
<text x="393.42" y="87.67" font-family="'Times New Roman',Times,serif" font-size="13.5" xml:space="preserve">Date :</text>
<text x="52.82" y="137.66" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Payee</text>
<text x="51.96" y="163.74" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Office</text>
<text x="47.66" y="188.38" font-family="'Times New Roman',Times,serif" font-size="11.6" xml:space="preserve">Address</text>
<text x="20.49" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Responsibility Center</text>
<text x="193.92" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Particulars</text>
<text x="332.05" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">MFO/PAP</text>
<text x="397.46" y="208.66" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">UACS Object</text>
<text x="415.90" y="221.71" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Code</text>
<text x="511.14" y="215.18" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Amount</text>
<text x="118.10" y="525.28" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Charges to appropriation/alloment are</text>
<text x="402.15" y="525.28" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">  Allotment available and obligated</text>
<text x="320.24" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Printed Name:</text>
<text x="24.75" y="757.86" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date</text>
<text x="82.71" y="757.86" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Particulars</text>
<text x="169.02" y="751.34" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">ORS/JEV/Check/</text>
<text x="173.74" y="764.38" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">ADA/TRA No.</text>
<text x="263.47" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Obligation</text>
<text x="337.48" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Payable</text>
<text x="408.52" y="749.16" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Payment</text>
<text x="511.44" y="733.22" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Balance</text>
<text x="468.25" y="757.13" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Not Yet Due</text>
<text x="542.22" y="750.61" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Due and</text>
<text x="533.22" y="763.65" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Demandable</text>
<text x="280.29" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(a)</text>
<text x="348.10" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(b)</text>
<text x="421.22" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(c)</text>
<text x="484.53" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(a-b)</text>
<text x="549.74" y="781.04" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">(b-c)</text>
<text x="54.34" y="539.05" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">necessary, lawful and under my direct supervision;and</text>
<text x="357.92" y="539.05" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve"> for the purpose/adjustment necessary as</text>
<text x="54.34" y="552.09" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">supporting documents valid, proper and legal</text>
<text x="357.92" y="552.09" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve"> indicated above</text>
<text x="29.28" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Signature</text>
<text x="320.24" y="602.08" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Signature      :</text>
<text x="20.29" y="623.82" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Printed Name:</text>
<text x="32.22" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Position</text>
<text x="320.24" y="641.93" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Position        :</text>
<text x="20.29" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date            :</text>
<text x="320.24" y="667.29" font-family="'Times New Roman',Times,serif" font-size="10.6" xml:space="preserve">Date             :</text>
<text x="29.25" y="515.86" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">A.</text>
<text x="331.31" y="515.86" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">B.</text>
<text x="61.57" y="525.28" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Certified:</text>
<text x="357.92" y="525.28" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Certified:</text>
<text x="29.25" y="699.17" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">C.</text>
<text x="258.44" y="699.17" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">STATUS OF OBLIGATION</text>
<text x="113.24" y="719.46" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Reference</text>
<text x="405.62" y="719.46" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold" xml:space="preserve">Amount</text>
<!-- header fields -->
<text x="393.4" y="67.4" font-family="'Times New Roman',Times,serif" font-size="13.5" xml:space="preserve">Serial No. : </text>
<text x="458.65" y="67.4" font-family="'Times New Roman',Times,serif" font-size="13.5" text-decoration="underline" xml:space="preserve">{{ $report->ors_number ?: '______________' }}</text>
<text x="429.39" y="87.7" font-family="'Times New Roman',Times,serif" font-size="13.5">{{ $date }}</text>
<text x="393.4" y="108.7" font-family="'Times New Roman',Times,serif" font-size="13.5" xml:space="preserve">Fund Cluster : </text>
<text x="473.8" y="108.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($fund, 118, 13.5) }}" text-decoration="underline">{{ $fund }}</text>
<text x="204" y="111.6" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="11.6" font-weight="bold">{{ $division }}</text>
<!-- payee / office / address -->
<text x="118.1" y="137.7" font-family="Arial,Helvetica,sans-serif" font-size="{{ $fit($payee, 470, 11.6) }}">{{ $payee }}</text>
<text x="118.1" y="163.7" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($office, 470, 11.6) }}">{{ $office }}</text>
<text x="118.1" y="188.4" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($address, 470, 11.6) }}">{{ $address }}</text>
<!-- responsibility center / particulars -->
<text x="66.3" y="237.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="{{ $fit($rc, 92, 10.6) }}">{{ $rc }}</text>
<text x="362.5" y="237.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="{{ $fit($pap, 58, 9) }}">{{ $pap }}</text>
<text x="432.5" y="237.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="{{ $fit($uacs, 64, 10) }}">{{ $uacs }}</text>
<foreignObject x="118.1" y="227.6" width="196" height="266"><div xmlns="http://www.w3.org/1999/xhtml" style="font:10.6px/13.05px Arial,Helvetica,sans-serif;color:#000;overflow:hidden;height:266px;white-space:pre-wrap;word-wrap:break-word">{{ $particulars }}</div></foreignObject>
<!-- amount -->
<text x="469.1" y="286.2" font-family="Arial,Helvetica,sans-serif" font-size="9.7">₱</text>
<text x="588.0" y="286.2" text-anchor="end" font-family="Arial,Helvetica,sans-serif" font-size="9.7">{{ $amount }}</text>
<text x="469.4" y="493.4" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">₱</text>
<text x="587.7" y="493.4" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="10.6" font-weight="bold">{{ $amount }}</text>
<!-- A. requested by -->
<text x="199.3" y="623.1" text-anchor="middle" font-family="'Bookman Old Style','Book Antiqua',Palatino,'Times New Roman',serif" font-size="{{ $fit($requesterName, 230, 9.7) }}" font-weight="bold">{{ $requesterName }}</text>
<text x="199.3" y="641.2" text-anchor="middle" font-family="'Bookman Old Style','Book Antiqua',Palatino,'Times New Roman',serif" font-size="{{ $fit($requesterRole, 230, 9.7) }}">{{ $requesterRole }}</text>
<!-- B. certified by -->
<text x="492.4" y="622.4" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($certifierName, 190, 10.6) }}" font-weight="bold">{{ $certifierName }}</text>
<text x="492.4" y="641.9" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($certifierRole, 190, 10.6) }}">{{ $certifierRole }}</text>
<!-- C. status of obligation -->
<foreignObject x="53" y="822.5" width="105" height="58"><div xmlns="http://www.w3.org/1999/xhtml" style="font:9.5px/11.5px 'Times New Roman',Times,serif;color:#000;text-align:center;overflow:hidden;height:58px;word-wrap:break-word;display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical">{{ $statusParticulars }}</div></foreignObject>
<text x="293" y="838.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="10.6">{{ $amount }}</text>
<text x="438.7" y="838.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="10.6">{{ $paidAmount }}</text>

</g>
</svg></div>
</body>
</html>
