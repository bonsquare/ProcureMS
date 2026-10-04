@php
    $meta = $document->metadata ?? [];
    $supplier = $document->supplier_or_recipient ?: '____________________________';
    $addressee = $meta['supplier_addressee'] ?? 'The Manager';
    $supplierAddress = $meta['supplier_address'] ?? '____________________________';
    $awardAmount = (float) ($meta['winning_bid_amount'] ?? $procurementRequest->amount);
    $awardAmountWords = $awardAmountInWords ?? $amountInWords;
    $schoolName = $procurementRequest->school?->name ?? '____________________________';
    $schoolHeadName = $schoolHead?->name ?? $procurementRequest->school?->school_head ?? '____________________________';
@endphp

<style>
    .noa-page{width:210mm;min-height:297mm;padding:9mm 16mm 16mm;font-family:Arial,sans-serif;font-size:10.5pt;line-height:1.35}
    .noa-letterhead{text-align:center;line-height:1.24;font-size:9.5pt}
    .noa-letterhead .department{font-size:10.5pt;font-weight:700}
    .noa-letterhead .school{margin-top:2px;font-size:12.5pt;font-weight:700}
    .noa-rule{margin-top:10px;border-top:1px solid #111}
    .noa-bac{margin-top:12px;font-size:10.5pt}
    .noa-title{margin:25px 0 45px;text-align:center;font-size:16pt;font-weight:700;text-decoration:underline}
    .noa-date{text-align:right;margin:0 0 20px;font-size:10.5pt}
    .noa-recipient{margin:0 0 31px;line-height:1.65}
    .noa-recipient .supplier-name,.noa-recipient .supplier-address{font-style:italic}
    .noa-salutation{margin-bottom:17px}
    .noa-message{margin:0;text-align:justify;line-height:1.65}
    .noa-message .intro{display:inline-block;padding-left:7mm}
    .noa-award-amount{font-weight:700;font-style:italic;text-decoration:underline}
    .noa-signature{width:48%;margin:35px 10% 0 auto;text-align:center}
    .noa-signature .name{margin-top:50px;font-weight:700;font-style:italic;text-decoration:underline;text-transform:uppercase}
    .noa-conforme{margin-top:67px}
    .noa-sign-line{width:58mm;margin-top:28mm;border-top:1px solid #111}
    .noa-conforme-date{margin-top:4mm}
    .noa-date-line{display:inline-block;width:48mm;border-bottom:1px solid #111}
    @media print{@page{size:A4;margin:0}.noa-page{margin:0;width:210mm;min-height:297mm;box-shadow:none}.noa-title{margin-top:25px}}
</style>

<main class="page noa-page">
    <header class="noa-letterhead">
        <div>{{ $agency?->republic_name ?? 'Republic of the Philippines' }}</div>
        <div class="department">{{ $agency?->department_name ?? 'Department of Education' }}</div>
        <div>{{ $agency?->region_name ?? $procurementRequest->school?->region }}</div>
        <div>{{ $agency?->division_office ?? $procurementRequest->school?->division }}</div>
        <div>{{ $agency?->district_name ?? $procurementRequest->school?->district }}</div>
        <div class="school">{{ strtoupper($schoolName) }}</div>
        <div class="noa-rule"></div>
    </header>

    <div class="noa-bac">Bids and Awards Committee</div>
    <h1 class="noa-title">NOTICE OF AWARD</h1>

    <div class="noa-date">Date: <strong>{{ $document->document_date->format('F d, Y') }}</strong></div>

    <div class="noa-recipient">
        <strong>{{ $addressee }}</strong><br>
        <span class="supplier-name">{{ strtoupper($supplier) }}</span><br>
        <span class="supplier-address">{{ $supplierAddress }}</span>
    </div>

    <div class="noa-salutation">Dear {{ str_ends_with(strtolower(trim($addressee)), 'manager') ? 'Manager' : trim($addressee) }} :</div>

    <p class="noa-message"><span class="intro">We are pleased to inform you that your Bid for execution by the</span> <strong><em>{{ strtoupper($supplier) }}</em></strong> relative to the project <strong>{{ $procurementRequest->title }}</strong> for the contract price of equivalent to <span class="noa-award-amount">{{ $awardAmountWords }} (Php {{ number_format($awardAmount, 2) }})</span> is hereby accepted.</p>

    <div class="noa-signature">
        <div>Very truly yours,</div>
        <div class="name">{{ $schoolHeadName }}</div>
        <div>School Head</div>
    </div>

    <div class="noa-conforme">CONFORME:</div>
    <div class="noa-sign-line"></div>
    <div class="noa-conforme-date">Date: <span class="noa-date-line"></span></div>
</main>
