<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DV {{ $report->dv_number }}</title>
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
        .sheet.no-appropriation .dv-appropriation { display: none; }
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
    $fund = $report->source_of_fund ?: $pr?->source_of_fund;
    $date = $report->dv_date?->format('F d, Y');
    $payee = $report->payee;
    $tin = $report->payee_tin;
    $address = $report->payee_address;
    $mode = ['Check' => 'MDS Check', 'Cash' => 'Others'][$report->payment_mode] ?? $report->payment_mode;
    $othersText = $report->payment_mode === 'Cash' ? 'Cash' : '';
    $particulars = $report->dv_particulars ?: $report->purpose;
    $rc = $report->responsibility_center_code ?: $pr?->responsibility_center_code;
    $amount = number_format((float) $report->amount, 2);
    $deductions = collect(\App\Models\LiquidationReport::DV_DEDUCTIONS)->map(fn ($label, $key) => ['label' => $label, 'value' => (float) ($report->dv_deductions[$key] ?? 0)])->filter(fn ($row) => $row['value'] > 0)->values()->concat(collect($report->dv_deductions['other'] ?? [])->map(fn ($row) => ['label' => $row['label'], 'value' => (float) $row['amount']]));
    $amountDue = number_format((float) $report->amount - $deductions->sum('value'), 2);
    $lessHeight = $deductions->isEmpty() ? 0 : 11 * ($deductions->count() + 1) + 4;
    $headName = strtoupper((string) ($head?->name ?: $report->school?->school_head));
    $headRole = $head?->position ?: 'School Head';
    $accountantName = strtoupper((string) ($accountant?->name ?? ''));
    $accountantRole = $accountant?->position ?: '';
    // Accounting Entry: up to 4 lines spread over the table of the form; more lines (up to 20) take smaller rows as they grow in number, and the table grows (to the page, less when the appropriation block is printed), pushing the rest of the form down by $shift. Beyond 10 lines the Particulars box gives up $cut of its height first, so the form does not run off the page.
    $entryCount = $report->journalLines->count();
    $entryRoom = $report->dv_include_appropriation ? 52 : 140;
    $cut = $entryCount > 10 ? round(min(max(0, 54 - $lessHeight), max(0, 459 + ($entryCount - 1) * 9 + 4 - 497.68 - $entryRoom)), 2) : 0;
    $entryFirst = ($entryCount > 4 ? 459 : 460) - $cut;
    $entryPitch = $entryCount > 4 ? min(9.5, (497.68 + $entryRoom - 4 - $entryFirst) / ($entryCount - 1)) : ($entryCount > 1 ? min(15, 36 / ($entryCount - 1)) : 0);
    $entrySize = $entryCount > 4 ? min(9, max(5.5, round($entryPitch * 0.95, 1))) : 9.8;
    $shift = $entryCount > 4 ? round(max(0, $entryFirst + ($entryCount - 1) * $entryPitch + 4 - 497.68), 2) : 0;
@endphp
@include('partials.official-toolbar', ['closeUrl' => route('accounting', ['tab' => 'with_dv']), 'context' => 'Disbursement Voucher · '.$report->dv_number])
<div class="sheet{{ $report->dv_include_appropriation ? '' : ' no-appropriation' }}" data-official-page data-single-page data-doc="dv" data-paper="longbond"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 612 936" preserveAspectRatio="xMidYMid meet" stroke="#000" fill="none">
<g fill="none">
<line x1="17.33" y1="40.20" x2="17.33" y2="{{ 782.56 + $shift }}" stroke-width="1.39"/>
<line transform="translate(0 {{ $shift }})" x1="313.30" y1="496.98" x2="313.30" y2="679.97" stroke-width="1.39"/>
<line x1="16.64" y1="40.90" x2="594.71" y2="40.90" stroke-width="1.39"/>
<line x1="16.64" y1="120.61" x2="594.71" y2="120.61" stroke-width="1.39"/>
<line x1="16.64" y1="155.26" x2="594.71" y2="155.26" stroke-width="1.39"/>
<line x1="71.39" y1="180.91" x2="594.71" y2="180.91" stroke-width="1.39"/>
<line x1="594.02" y1="40.20" x2="594.02" y2="181.60" stroke-width="1.39"/>
<line x1="16.64" y1="203.09" x2="594.71" y2="203.09" stroke-width="1.39"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="16.64" y1="355.58" x2="594.71" y2="355.58" stroke-width="1.39"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="16.64" y1="420.74" x2="594.71" y2="420.74" stroke-width="1.39"/>
<line transform="translate(0 {{ $shift }})" x1="16.64" y1="497.68" x2="594.71" y2="497.68" stroke-width="1.39"/>
<line transform="translate(0 {{ $shift }})" x1="16.64" y1="679.28" x2="594.71" y2="679.28" stroke-width="1.39"/>
<line transform="translate(0 {{ $shift }})" x1="16.64" y1="781.86" x2="594.71" y2="781.86" stroke-width="1.39"/>
<line x1="594.02" y1="202.40" x2="594.02" y2="{{ 782.56 + $shift }}" stroke-width="1.39"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="18.02" y1="368.40" x2="31.88" y2="368.40" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="31.54" y1="356.27" x2="31.54" y2="368.75" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="31.54" y1="421.43" x2="31.54" y2="435.99" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.54" y1="498.37" x2="31.54" y2="515.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.54" y1="524.71" x2="31.54" y2="535.80" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.54" y1="546.89" x2="31.54" y2="557.98" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.54" y1="569.07" x2="31.54" y2="580.16" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.54" y1="679.97" x2="31.54" y2="697.99" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="525.05" x2="57.53" y2="525.05" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="535.45" x2="57.53" y2="535.45" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="57.18" y1="524.71" x2="57.18" y2="535.80" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="547.23" x2="57.53" y2="547.23" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="557.63" x2="57.53" y2="557.63" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="57.18" y1="546.89" x2="57.18" y2="557.98" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="569.42" x2="57.53" y2="569.42" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="31.19" y1="579.81" x2="57.53" y2="579.81" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="57.18" y1="569.07" x2="57.18" y2="580.16" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="57.18" y1="793.65" x2="57.18" y2="881.67" stroke-width="0.69"/>
<line x1="71.74" y1="121.30" x2="71.74" y2="154.57" stroke-width="0.69"/>
<line x1="18.02" y1="181.26" x2="71.39" y2="181.26" stroke-width="0.69"/>
<line x1="71.74" y1="155.96" x2="71.74" y2="180.22" stroke-width="0.69"/>
<line x1="71.74" y1="181.60" x2="71.74" y2="202.40" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="71.74" y1="595.41" x2="71.74" y2="678.58" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="71.74" y1="697.30" x2="71.74" y2="764.53" stroke-width="0.69"/>
<line x1="109.17" y1="129.62" x2="109.17" y2="142.79" stroke-width="0.69"/>
<line x1="108.82" y1="129.96" x2="125.46" y2="129.96" stroke-width="0.69"/>
<line x1="108.82" y1="142.44" x2="125.46" y2="142.44" stroke-width="0.69"/>
<line x1="125.11" y1="129.62" x2="125.11" y2="142.79" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="149.37" y1="793.65" x2="149.37" y2="881.67" stroke-width="0.69"/>
<line x1="181.26" y1="129.62" x2="181.26" y2="142.79" stroke-width="0.69"/>
<line x1="180.91" y1="129.96" x2="197.55" y2="129.96" stroke-width="0.69"/>
<line x1="180.91" y1="142.44" x2="197.55" y2="142.44" stroke-width="0.69"/>
<line x1="197.20" y1="129.62" x2="197.20" y2="142.79" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="213.14" y1="697.30" x2="213.14" y2="764.53" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="260.97" y1="793.65" x2="260.97" y2="881.67" stroke-width="0.69"/>
<line x1="285.92" y1="203.78" x2="285.92" y2="{{ 354.89 - $cut }}" stroke-width="0.69"/>
<line x1="313.65" y1="129.62" x2="313.65" y2="142.79" stroke-width="0.69"/>
<line x1="313.65" y1="155.96" x2="313.65" y2="180.22" stroke-width="0.69"/>
<line x1="313.65" y1="{{ 435.29 - $cut }}" x2="313.65" y2="{{ 496.98 + $shift }}" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="514.66" x2="312.61" y2="514.66" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="595.75" x2="312.61" y2="595.75" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="626.00" x2="312.61" y2="626.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="647.00" x2="312.61" y2="647.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="663.00" x2="312.61" y2="663.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.65" y1="697.30" x2="313.65" y2="764.53" stroke-width="0.69"/>
<line x1="313.30" y1="129.96" x2="329.93" y2="129.96" stroke-width="0.69"/>
<line x1="313.30" y1="142.44" x2="329.93" y2="142.44" stroke-width="0.69"/>
<line x1="329.59" y1="129.62" x2="329.59" y2="142.79" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="329.59" y1="498.37" x2="329.59" y2="515.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="345.53" y1="793.65" x2="345.53" y2="881.67" stroke-width="0.69"/>
<line x1="372.56" y1="129.62" x2="372.56" y2="142.79" stroke-width="0.69"/>
<line x1="372.56" y1="203.78" x2="372.56" y2="{{ 354.89 - $cut }}" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="372.56" y1="595.41" x2="372.56" y2="678.58" stroke-width="0.69"/>
<line x1="372.22" y1="129.96" x2="395.09" y2="129.96" stroke-width="0.69"/>
<line x1="372.22" y1="142.44" x2="395.09" y2="142.44" stroke-width="0.69"/>
<line x1="394.74" y1="129.62" x2="394.74" y2="142.79" stroke-width="0.69"/>
<line x1="394.74" y1="{{ 435.29 - $cut }}" x2="394.74" y2="{{ 496.98 + $shift }}" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="416.92" y1="793.65" x2="416.92" y2="881.67" stroke-width="0.69"/>
<line x1="480.69" y1="41.59" x2="480.69" y2="119.91" stroke-width="0.69"/>
<line x1="480.69" y1="155.96" x2="480.69" y2="180.22" stroke-width="0.69"/>
<line x1="480.69" y1="203.78" x2="480.69" y2="{{ 354.89 - $cut }}" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="697.65" x2="481.04" y2="697.65" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="764.19" x2="481.04" y2="764.19" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="480.69" y1="679.97" x2="480.69" y2="781.17" stroke-width="0.69"/>
<line x1="496.64" y1="{{ 435.29 - $cut }}" x2="496.64" y2="{{ 496.98 + $shift }}" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="793.99" x2="512.92" y2="793.99" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="805.08" x2="512.92" y2="805.08" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="820.33" x2="512.92" y2="820.33" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="835.58" x2="512.92" y2="835.58" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="850.83" x2="512.92" y2="850.83" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="866.08" x2="512.92" y2="866.08" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="56.84" y1="881.33" x2="512.92" y2="881.33" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" class="dv-appropriation" x1="512.58" y1="793.65" x2="512.58" y2="881.67" stroke-width="0.69"/>
<line x1="480.35" y1="79.36" x2="593.33" y2="79.36" stroke-width="0.69"/>
<line x1="594.37" y1="181.60" x2="594.37" y2="202.40" stroke-width="0.69"/>
<line x1="18.02" y1="225.62" x2="593.33" y2="225.62" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="480.35" y1="340.68" x2="593.33" y2="340.68" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="18.02" y1="435.64" x2="593.33" y2="435.64" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="18.02" y1="450.19" x2="593.33" y2="450.19" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.99" y1="514.66" x2="593.33" y2="514.66" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.99" y1="595.75" x2="593.33" y2="595.75" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.99" y1="626.00" x2="593.33" y2="626.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.99" y1="647.00" x2="593.33" y2="647.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="313.99" y1="663.00" x2="593.33" y2="663.00" stroke-width="0.69"/>
<line transform="translate(0 {{ $shift }})" x1="18.02" y1="726.76" x2="593.33" y2="726.76" stroke-width="0.69"/>
<line transform="translate(0 {{ 0 - $cut }})" x1="233.59" y1="405.83" x2="355.58" y2="405.83" stroke-width="0.69"/>
</g>
<g fill="#000" stroke="none">
<text x="531.56" y="34.66" font-family="'Times New Roman',Times,serif" font-size="12.0" font-style="italic" xml:space="preserve">Appendix 32</text>
<text x="152.64" y="60.30" font-family="'Times New Roman',Times,serif" font-size="12.9" font-weight="bold" xml:space="preserve">DEPARTMENT OF EDUCATION</text>
<text x="181.44" y="76.25" font-family="'Times New Roman',Times,serif" font-size="12.9" font-weight="bold" xml:space="preserve">COTABATO DIVISION</text>
<text x="483.12" y="55.45" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Fund Cluster :</text>
<text x="484.71" y="90.80" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Date :</text>
<text x="485.90" y="104.66" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">DV No. :</text>
<text x="20.10" y="135.86" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Mode of</text>
<text x="20.10" y="146.95" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Payment</text>
<text x="20.10" y="171.90" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Payee</text>
<text x="20.10" y="195.47" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Address</text>
<text transform="translate(0 {{ 0 - $cut }})" x="126.52" y="352.12" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Amount Due</text>
<text transform="translate(0 {{ 0 - $cut }})" x="20.10" y="365.29" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">A.</text>
<text transform="translate(0 {{ 0 - $cut }})" x="20.10" y="431.83" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">B.</text>
<text transform="translate(0 {{ $shift }})" x="21" y="506.6" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">C.</text>
<text transform="translate(0 {{ $shift }})" x="35" y="506.6" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Certified:</text>
<text transform="translate(0 {{ $shift }})" x="333" y="506.6" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Approved for Payment</text>
<text transform="translate(0 {{ $shift }})" x="21" y="688.5" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">E.</text>
<text transform="translate(0 {{ $shift }})" x="35" y="688.5" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">Receipt of Payment</text>
<text transform="translate(0 {{ $shift }})" x="317" y="506.6" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold" xml:space="preserve">D.</text>
<text x="127.54" y="140.01" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">MDS Check</text>
<text x="199.62" y="140.01" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Commercial Check</text>
<text x="332.01" y="140.01" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">ADA</text>
<text x="397.17" y="140.01" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Others (Please specify)</text>
<text x="316.07" y="167.05" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">TIN/Employee No.:</text>
<text x="483.12" y="167.05" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">ORS/BURS No.: </text>
<text x="132.04" y="217.65" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Particulars</text>
<text x="289.08" y="217.65" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Responsibility Center</text>
<text x="407.28" y="217.65" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">MFO/PAP</text>
<text x="522.04" y="217.65" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Amount</text>
<text transform="translate(0 {{ 0 - $cut }})" x="33.96" y="431.83" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve"> Accounting Entry:</text>
<text transform="translate(0 {{ 0 - $cut }})" x="140.29" y="446.38" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Account Title</text>
<text transform="translate(0 {{ 0 - $cut }})" x="330.97" y="446.38" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">UACS Code</text>
<text transform="translate(0 {{ 0 - $cut }})" x="435.43" y="447.77" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Debit</text>
<text transform="translate(0 {{ 0 - $cut }})" x="533.61" y="447.77" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Credit</text>
<text transform="translate(0 {{ $shift }})" x="27.00" y="611" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Signature</text>
<text transform="translate(0 {{ $shift }})" x="325.40" y="611" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Signature</text>
<text transform="translate(0 {{ $shift }})" x="342.5" y="636.5" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="8.4" xml:space="preserve">Printed Name</text>
<text transform="translate(0 {{ $shift }})" x="29.56" y="655" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Position</text>
<text transform="translate(0 {{ $shift }})" x="327.96" y="655" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Position</text>
<text transform="translate(0 {{ $shift }})" x="35.99" y="671.1" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Date</text>
<text transform="translate(0 {{ $shift }})" x="334.38" y="671.1" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Date</text>
<text transform="translate(0 {{ $shift }})" x="483.12" y="695.22" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">JEV  No.</text>
<text transform="translate(0 {{ $shift }})" x="31.62" y="709.78" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Check/</text>
<text transform="translate(0 {{ $shift }})" x="24.30" y="720.87" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">ADA No. :</text>
<text transform="translate(0 {{ $shift }})" x="215.57" y="712.2" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Date :</text>
<text transform="translate(0 {{ $shift }})" x="316.07" y="715.32" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Bank Name &amp; Account Number:</text>
<text transform="translate(0 {{ $shift }})" x="24.56" y="748.59" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Signature :</text>
<text transform="translate(0 {{ $shift }})" x="215.57" y="748.59" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Date :</text>
<text transform="translate(0 {{ $shift }})" x="316.07" y="748.59" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Printed Name:</text>
<text transform="translate(0 {{ $shift }})" x="483.12" y="749.29" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Date</text>
<text transform="translate(0 {{ $shift }})" x="20.10" y="776.32" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">Official Receipt No. &amp; Date/Other Documents</text>
<text x="463.71" y="151.80" font-family="'Times New Roman',Times,serif" font-size="9.2" xml:space="preserve">_________________</text>
<text transform="translate(0 {{ $shift }})" x="44.9" y="636.5" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="8.4" xml:space="preserve">Printed Name</text>
<text x="148.70" y="105.36" font-family="'Times New Roman',Times,serif" font-size="14.8" font-weight="bold" xml:space="preserve">DISBURSEMENT  VOUCHER</text>
<text transform="translate(0 {{ 0 - $cut }})" x="33.96" y="365.98" font-family="'Times New Roman',Times,serif" font-size="10.2" xml:space="preserve"> Certified:  Expenses/Cash Advance necessary,  lawful and  incurred under my direct supervision.</text>
<text transform="translate(0 {{ $shift }})" x="59.61" y="535.10" font-family="'Times New Roman',Times,serif" font-size="10.2" xml:space="preserve"> Cash available</text>
<text transform="translate(0 {{ $shift }})" x="62.15" y="557.29" font-family="'Times New Roman',Times,serif" font-size="10.2" xml:space="preserve">Subject to Authority to Debit Account (when applicable)</text>
<text transform="translate(0 {{ $shift }})" x="62.15" y="579.47" font-family="'Times New Roman',Times,serif" font-size="10.2" xml:space="preserve">Supporting documents complete and amount claimed</text>
<text transform="translate(0 {{ $shift }})" x="59.61" y="589.86" font-family="'Times New Roman',Times,serif" font-size="10.2" xml:space="preserve">proper</text>
<text transform="translate(0 {{ $shift }})" class="dv-appropriation" x="68.93" y="802.66" font-family="Arial,Helvetica,sans-serif" font-size="8.3" font-weight="bold" xml:space="preserve">APPROPRIATION</text>
<text transform="translate(0 {{ $shift }})" class="dv-appropriation" x="194.31" y="802.66" font-family="Arial,Helvetica,sans-serif" font-size="8.3" font-weight="bold" xml:space="preserve">P/A/P</text>
<text transform="translate(0 {{ $shift }})" class="dv-appropriation" x="288.46" y="802.66" font-family="Arial,Helvetica,sans-serif" font-size="8.3" font-weight="bold" xml:space="preserve">OR NO.</text>
<text transform="translate(0 {{ $shift }})" class="dv-appropriation" x="362.98" y="802.66" font-family="Arial,Helvetica,sans-serif" font-size="8.3" font-weight="bold" xml:space="preserve">AMOUNT</text>
<text transform="translate(0 {{ $shift }})" class="dv-appropriation" x="431.93" y="802.66" font-family="Arial,Helvetica,sans-serif" font-size="8.3" font-weight="bold" xml:space="preserve">EXPENSE CODE</text>
<!-- header -->
<text x="537.2" y="72.8" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($fund, 108, 9.2) }}">{{ $fund }}</text>
<text x="510.75" y="90.8" font-family="'Times New Roman',Times,serif" font-size="9.2">{{ $date }}</text>
<text x="522.69" y="104.7" font-family="'Times New Roman',Times,serif" font-size="9.2" font-weight="bold">{{ $report->dv_number }}</text>
<!-- mode of payment -->
<text x="117.30" y="139.50" font-family="Arial,Helvetica,sans-serif" font-size="11" font-weight="bold" text-anchor="middle">{{ $mode === 'MDS Check' ? '✓' : '' }}</text>
<text x="189.20" y="139.50" font-family="Arial,Helvetica,sans-serif" font-size="11" font-weight="bold" text-anchor="middle">{{ $mode === 'Commercial Check' ? '✓' : '' }}</text>
<text x="321.60" y="139.50" font-family="Arial,Helvetica,sans-serif" font-size="11" font-weight="bold" text-anchor="middle">{{ $mode === 'ADA' ? '✓' : '' }}</text>
<text x="383.90" y="139.50" font-family="Arial,Helvetica,sans-serif" font-size="11" font-weight="bold" text-anchor="middle">{{ $mode === 'Others' ? '✓' : '' }}</text>
<text x="503" y="150.6" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="9.2">{{ $othersText }}</text>
<!-- payee -->
@php
    // The payee and the address are written in a box of the cell: centered vertically, left aligned, bold; a long text takes a smaller size and a second line instead of being cut.
    $cellText = function ($text, $width, $height, $max) {
        $length = max(mb_strlen((string) $text), 1);
        $single = min($max, round($width / $length / 0.5, 1));
        if ($single >= 8.6) {
            return ['size' => $single, 'height' => $height];
        }

        return ['size' => max(min(9.2, round($width / ceil($length / 2) / 0.5, 1)), 6.5), 'height' => $height];
    };
    $payeeCell = $cellText($payee, 232, 24, 10.2);
    $addressCell = $cellText($address, 510, 20.8, 10.2);
@endphp
<foreignObject x="76" y="155.96" width="234" height="24.3"><div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:center;justify-content:flex-start;height:24.3px;overflow:hidden;font:bold {{ $payeeCell['size'] }}px/1.08 'Times New Roman',Times,serif;color:#000;word-wrap:break-word;text-align:left">{{ $payee }}</div></foreignObject>
<text x="316.07" y="177.6" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($tin, 150, 9.8) }}" font-weight="bold">{{ $tin }}</text>
<text x="483.1" y="177.6" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($report->ors_number, 108, 9.8) }}" font-weight="bold">{{ $report->ors_number }}</text>
<foreignObject x="76" y="181.6" width="514" height="20.8"><div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:center;justify-content:flex-start;height:20.8px;overflow:hidden;font:bold {{ $addressCell['size'] }}px/1.08 'Times New Roman',Times,serif;color:#000;word-wrap:break-word;text-align:left">{{ $address }}</div></foreignObject>
<!-- particulars -->
<foreignObject x="22.6" y="230.6" width="258.4" height="{{ 114 - $cut - $lessHeight }}"><div xmlns="http://www.w3.org/1999/xhtml" style="font:bold 10px/12px 'Times New Roman',Times,serif;color:#000;overflow:hidden;height:{{ 114 - $cut - $lessHeight }}px;white-space:pre-wrap;word-wrap:break-word">{{ $particulars }}</div></foreignObject>
<text x="329.3" y="241.2" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($rc, 80, 10) }}" font-weight="bold">{{ $rc }}</text>
<text x="588.8" y="241.2" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="10.2" font-weight="bold">₱{{ $amount }}</text>
<text transform="translate(0 {{ 0 - $cut }})" x="588.8" y="351.4" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="10.2" font-weight="bold">₱{{ $amountDue }}</text>
@if($deductions->isNotEmpty())
<text x="24" y="{{ 337 - $cut - $deductions->count() * 11 }}" font-family="'Times New Roman',Times,serif" font-size="9.8" font-weight="bold">Less:</text>
@foreach($deductions as $deduction)
@php $deductionY = 337 - $cut - ($deductions->count() - 1 - $loop->index) * 11; @endphp
<text x="36" y="{{ $deductionY }}" font-family="'Times New Roman',Times,serif" font-size="9.8" font-weight="bold">{{ $deduction['label'] }}</text>
<text x="588.8" y="{{ $deductionY }}" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="9.8" font-weight="bold">₱{{ number_format($deduction['value'], 2) }}</text>
@endforeach
@endif
{{-- Accounting Entry: the double-entry journal saved with the DV (account title, UACS code, debit, credit) --}}
@foreach($report->journalLines as $line)
@php $rowY = $entryFirst + $loop->index * $entryPitch; $title = \Illuminate\Support\Str::limit($line->account_title, 64); @endphp
<text x="23" y="{{ $rowY }}" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($title, 300 - ($line->credit > 0 ? 8 : 0), $entrySize) }}" font-weight="bold" dx="{{ $line->credit > 0 ? 8 : 0 }}">{{ $title }}</text>
<text x="354" y="{{ $rowY }}" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $entrySize }}" font-weight="bold">{{ $line->account_code }}</text>
<text x="489.6" y="{{ $rowY }}" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="{{ $entrySize }}" font-weight="bold">{{ $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}</text>
<text x="588.8" y="{{ $rowY }}" text-anchor="end" font-family="'Times New Roman',Times,serif" font-size="{{ $entrySize }}" font-weight="bold">{{ $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}</text>
@endforeach
<!-- A. certified -->
<text transform="translate(0 {{ 0 - $cut }})" x="294.6" y="404.8" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($headName, 190, 10.2) }}" font-weight="bold">{{ $headName }}</text>
<text transform="translate(0 {{ 0 - $cut }})" x="294.6" y="417.3" text-anchor="middle" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($headRole, 190, 10.2) }}">{{ $headRole }}</text>
<!-- C. certified / D. approved -->
<text transform="translate(0 {{ $shift }})" x="192.4" y="636.5" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($accountantName, 190, 10.2) }}" font-weight="bold">{{ $accountantName }}</text>
<text transform="translate(0 {{ $shift }})" x="192.4" y="655" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($accountantRole, 190, 10.2) }}">{{ $accountantRole }}</text>
<text transform="translate(0 {{ $shift }})" x="483.1" y="636.5" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($headName, 150, 10.2) }}" font-weight="bold">{{ $headName }}</text>
<text transform="translate(0 {{ $shift }})" x="483.1" y="655" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($headRole, 150, 10.2) }}">{{ $headRole }}</text>
<!-- E. receipt of payment -->
<text transform="translate(0 {{ $shift }})" x="142.4" y="712.2" text-anchor="middle" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="{{ $fit($report->payment_reference, 130, 10) }}" font-weight="bold">{{ $report->payment_reference }}</text>
<text transform="translate(0 {{ $shift }})" x="240.13" y="712.2" dominant-baseline="central" font-family="'Times New Roman',Times,serif" font-size="9.8" font-weight="bold">{{ $report->paid_at?->format('M d, Y') }}</text>

</g>
</svg></div>
</body>
</html>
