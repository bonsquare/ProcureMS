@php
    $meta = $document->metadata ?? [];
    $variant = $meta['template_variant'] ?? 'sbfp';
    $supplier = $document->supplier_or_recipient ?: '____________________________';
    $addressee = $meta['supplier_addressee'] ?? 'The Manager';
    $supplierAddress = $meta['supplier_address'] ?? '____________________________';
    $schoolName = $procurementRequest->school?->name ?? '____________________________';
    $schoolHeadName = $schoolHead?->name ?? $procurementRequest->school?->school_head ?? '____________________________';
    $salutation = str_ends_with(strtolower(trim($addressee)), 'manager') ? 'Manager' : trim($addressee);
    $leftLogo = $agency?->department_logo_path ? asset('storage/'.$agency->department_logo_path) : asset('images/official-deped-logo.png');
    $rightLogo = $procurementRequest->school?->logo_path ? asset('storage/'.$procurementRequest->school->logo_path) : ($agency?->division_logo_path ? asset('storage/'.$agency->division_logo_path) : asset('images/official-school-logo.png'));
@endphp

<style>
    .ntp-page{width:210mm;min-height:297mm;padding:9mm 16mm 16mm;font-family:Arial,sans-serif;font-size:10.5pt;line-height:1.35}
    .ntp-letterhead{position:relative;text-align:center;line-height:1.24;font-size:9.5pt}
    .ntp-letterhead img{position:absolute;top:0;width:20mm;height:20mm;object-fit:contain}
    .ntp-letterhead .left-logo{left:0}
    .ntp-letterhead .right-logo{right:0}
    .ntp-letterhead .department{font-size:10.5pt;font-weight:700}
    .ntp-letterhead .school{margin-top:2px;font-size:12.5pt;font-weight:700}
    .ntp-rule{margin-top:10px;border-top:1px solid #111}
    .ntp-bac{margin-top:12px;font-size:10.5pt}
    .ntp-title{margin:25px 0 45px;text-align:center;font-size:16pt;font-weight:700;text-decoration:underline}
    .ntp-date{text-align:right;margin:0 0 20px;font-size:10.5pt}
    .ntp-recipient{margin:0 0 31px;line-height:1.65}
    .ntp-recipient .supplier-name,.ntp-recipient .supplier-address{font-style:italic}
    .ntp-salutation{margin-bottom:17px}
    .ntp-message{margin:0 0 14px;text-align:justify;line-height:1.65}
    .ntp-message .intro{display:inline-block;padding-left:7mm}
    .ntp-signature{width:48%;margin:35px 10% 0 auto;text-align:center}
    .ntp-signature .name{margin-top:50px;font-weight:700;font-style:italic;text-decoration:underline;text-transform:uppercase}
    .ntp-conforme{margin-top:67px}
    .ntp-sign-line{width:58mm;margin-top:28mm;border-top:1px solid #111}
    .ntp-conforme-date{margin-top:4mm}
    .ntp-date-line{display:inline-block;width:48mm;border-bottom:1px solid #111}
    @media print{@page{size:A4;margin:0}.ntp-page{margin:0;width:210mm;min-height:295.5mm;box-shadow:none}.ntp-title{margin-top:25px}}
</style>

<main class="page ntp-page">
    <header class="ntp-letterhead">
        @if($leftLogo)<img class="left-logo" src="{{ $leftLogo }}" alt="Department logo">@endif
        @if($rightLogo)<img class="right-logo" src="{{ $rightLogo }}" alt="School logo">@endif
        <div>{{ $agency?->republic_name ?: 'Republic of the Philippines' }}</div>
        <div class="department">{{ $agency?->department_name ?: 'Department of Education' }}</div>
        <div>{{ $procurementRequest->school?->region }}</div>
        <div>{{ $procurementRequest->school?->division }}</div>
        <div>{{ $procurementRequest->school?->district }}</div>
        <div class="school">{{ strtoupper($schoolName) }}</div>
        <div class="ntp-rule"></div>
    </header>

    <div class="ntp-bac">Bids and Awards Committee</div>
    <h1 class="ntp-title">NOTICE TO PROCEED</h1>

    <div class="ntp-date">Date: <strong>{{ $document->document_date->format('F d, Y') }}</strong></div>

    <div class="ntp-recipient">
        <strong>{{ $addressee }}</strong><br>
        <span class="supplier-name">{{ strtoupper($supplier) }}</span><br>
        <span class="supplier-address">{{ $supplierAddress }}</span>
    </div>

    <div class="ntp-salutation">Dear {{ $salutation }} :</div>

    <p class="ntp-message"><span class="intro">We wish to inform you that the Contract Agreement for the</span> <strong>{{ $procurementRequest->title }}</strong> has been approved.</p>

    @if(in_array($variant, ['mooe', 'thirty_days'], true))
        <p class="ntp-message"><span class="intro">You are now hereby notified to commence delivery of goods</span> and shall fully complete the delivery within <strong><u>{{ $meta['delivery_days'] ?? 30 }}</u></strong> calendar days from receipt of this notice.</p>
    @else
        <p class="ntp-message"><span class="intro">You are hereby notified to commence the delivery of the goods</span> in accordance with the schedule of delivery for each item and to ensure the timely and complete delivery of all items within their respective scheduled delivery periods upon receipt of this notice.</p>
    @endif

    <div class="ntp-signature">
        <div>Very truly yours,</div>
        <div class="name">{{ $schoolHeadName }}</div>
        <div>School Head</div>
    </div>

    <div class="ntp-conforme">CONFORME:</div>
    <div class="ntp-sign-line"></div>
    <div class="ntp-conforme-date">Date: <span class="ntp-date-line"></span></div>
</main>
