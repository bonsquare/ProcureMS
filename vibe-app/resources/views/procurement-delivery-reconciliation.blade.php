<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Reconciliation Report · {{ $procurementRequest->request_number }}</title>
    <style>
        *{box-sizing:border-box}html,body{margin:0;color:#111;font-family:Arial,sans-serif}body{background:#e9e9e9}.toolbar{position:sticky;top:0;z-index:100;display:flex;align-items:center;gap:8px;padding:12px 20px;background:#fff;border-bottom:1px solid #bbb}.toolbar a,.toolbar button{border:1px solid #00236f;border-radius:4px;padding:9px 14px;background:#fff;color:#00236f;font-weight:700;cursor:pointer;text-decoration:none}.toolbar .primary{background:#00236f;color:#fff}.toolbar span{margin-left:auto;font-size:12px;color:#555}.page{position:relative;width:215.9mm;min-height:279.4mm;margin:16px auto;padding:12mm 13mm;background:#fff;box-shadow:0 2px 12px #999;font-size:10pt;line-height:1.28}.letterhead{position:relative;text-align:center;line-height:1.25}.letterhead img{position:absolute;top:0;width:20mm;height:20mm;object-fit:contain}.letterhead .left-logo{left:0}.letterhead .right-logo{right:0}.letterhead .department{font-weight:700}.letterhead .school{margin-top:2px;font-size:12pt;font-weight:700}.rule{margin-top:8px;border-top:1px solid #111}.eyebrow{margin-top:12px;font-size:9pt;font-weight:700;color:#333}.title{text-align:center;margin:14px 0 12px;font-size:16pt;font-weight:700}.top-details{margin:10px 0 12px;padding:8px 10px;border:1px solid #111}.top-row{display:grid;grid-template-columns:31mm 1fr;gap:6px;margin:3px 0}.top-row .label{font-weight:700}.top-row .value{font-weight:700}.meta{display:grid;grid-template-columns:30mm 1fr 28mm 1fr;gap:4px 6px;margin:12px 0 14px}.meta .label{font-weight:700}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:10px 0 14px}.summary div{border:1px solid #111;padding:6px}.summary .value{display:block;margin-top:3px;font-size:13pt;font-weight:700}.section-title{margin:14px 0 6px;font-size:11pt;font-weight:700}.report-table{width:100%;border-collapse:collapse;table-layout:fixed}.report-table th,.report-table td{border:1px solid #111;padding:5px;vertical-align:middle}.report-table th{text-align:center;font-size:8.5pt}.center{text-align:center}.right{text-align:right}.status-complete{font-weight:700;color:#006c4a}.status-partial{font-weight:700;color:#9a3412}.small{font-size:8.5pt}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:20mm;margin-top:22mm;text-align:center}.sign-line{border-top:1px solid #111;padding-top:3px}.empty{padding:12px;text-align:center;color:#444;font-style:italic}@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:100%;min-height:0;box-shadow:none}}
    </style>
@include('partials.input-fixes')
@include('partials.print-clean')
@include('partials.procurement-emphasis')
</head>
<body>
@include('partials.official-toolbar', ['closeUrl' => route('procurement.documents', $procurementRequest), 'context' => 'Delivery reconciliation'])
<main class="page" data-official-page data-doc="reconciliation" data-paper="letter">
    @php
        [$leftLogo, $rightLogo] = \App\Support\OfficialDocument::logos($procurementRequest->school, $agency);
    @endphp
    <header class="letterhead">
        @if($leftLogo)<img class="left-logo official-logo" src="{{ $leftLogo }}" alt="Department logo" onerror="this.remove()">@endif
        @if($rightLogo)<img class="right-logo official-logo" src="{{ $rightLogo }}" alt="School logo" onerror="this.remove()">@endif
        <div>{{ $agency?->republic_name ?: 'Republic of the Philippines' }}</div>
        <div class="department">{{ $agency?->department_name ?: 'Department of Education' }}</div>
        <div>{{ $procurementRequest->school?->region }}</div>
        <div>{{ $procurementRequest->school?->division }}</div>
        <div>{{ $procurementRequest->school?->district }}</div>
        <div class="school">{{ strtoupper($procurementRequest->school?->name ?? '') }}</div>
        <div class="rule"></div>
    </header>

    <div class="eyebrow">Internal Working Report</div>
    <h1 class="title">DELIVERY RECONCILIATION REPORT</h1>

    @php
        $transactionDescription = $procurementRequest->transaction_description ?: data_get($iar->metadata, 'transaction_description') ?: data_get($purchaseOrder->metadata, 'transaction_description');
        $projectTitle = data_get($iar->metadata, 'project_title') ?: data_get($purchaseOrder->metadata, 'project_title') ?: $procurementRequest->title;
        $purposeText = collect([$transactionDescription, $projectTitle])->filter()->implode(' for ');
    @endphp
    <section class="top-details">
        <div class="top-row"><div class="label">Supplier:</div><div class="value">{{ strtoupper($purchaseOrder->supplier_or_recipient ?? '') }}</div></div>
        <div class="top-row"><div class="label">Purpose:</div><div class="value">{{ $purposeText ?: '—' }}</div></div>
    </section>

    <section class="meta">
        <div class="label">PR No.:</div><div>{{ $procurementRequest->request_number }}</div>
        <div class="label">PR Date:</div><div>{{ optional($procurementRequest->date_needed)->format('F d, Y') ?: optional($procurementRequest->created_at)->format('F d, Y') }}</div>
        <div class="label">PO No.:</div><div>{{ $purchaseOrder->document_number }}</div>
        <div class="label">PO Date:</div><div>{{ $purchaseOrder->document_date->format('F d, Y') }}</div>
        <div class="label">IAR No.:</div><div>{{ $iar->document_number }}</div>
        <div class="label">IAR Date:</div><div>{{ $iar->document_date->format('F d, Y') }}</div>
    </section>

    <section class="summary">
        <div>PO Amount<span class="value">PHP {{ number_format($totalPoAmount, 2) }}</span></div>
        <div>Received Amount<span class="value">PHP {{ number_format($totalReceivedAmount, 2) }}</span></div>
        <div>Partial Items<span class="value">{{ $partialRows->count() }}</span></div>
        <div>Complete Items<span class="value">{{ $completeRows->count() }}</span></div>
    </section>

    <div class="section-title">Items for Reconciliation</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width:9%">Stock No.</th>
                <th>Description</th>
                <th style="width:9%">Unit</th>
                <th style="width:10%">PO Qty</th>
                <th style="width:12%">Received Qty</th>
                <th style="width:10%">Balance</th>
                <th style="width:12%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="center">{{ strtoupper($row['unit']) }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format($row['po_quantity'], 2), '0'), '.') }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format($row['received_quantity'], 2), '0'), '.') }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format($row['balance'], 2), '0'), '.') }}</td>
                    <td class="center {{ $row['status'] === 'Partial' ? 'status-partial' : 'status-complete' }}">{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Partial Deliveries</div>
    @if($partialRows->isEmpty())
        <div class="empty">No partial deliveries recorded.</div>
    @else
        <table class="report-table">
            <thead><tr><th>Description</th><th style="width:12%">PO Qty</th><th style="width:14%">Received Qty</th><th style="width:12%">Balance</th><th style="width:18%">Remarks</th></tr></thead>
            <tbody>
                @foreach($partialRows as $row)
                    <tr><td>{{ $row['description'] }}</td><td class="center">{{ rtrim(rtrim(number_format($row['po_quantity'], 2), '0'), '.') }}</td><td class="center">{{ rtrim(rtrim(number_format($row['received_quantity'], 2), '0'), '.') }}</td><td class="center">{{ rtrim(rtrim(number_format($row['balance'], 2), '0'), '.') }}</td><td></td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Completed Deliveries</div>
    @if($completeRows->isEmpty())
        <div class="empty">No completed deliveries recorded.</div>
    @else
        <table class="report-table">
            <thead><tr><th>Description</th><th style="width:12%">PO Qty</th><th style="width:14%">Received Qty</th><th style="width:18%">Remarks</th></tr></thead>
            <tbody>
                @foreach($completeRows as $row)
                    <tr><td>{{ $row['description'] }}</td><td class="center">{{ rtrim(rtrim(number_format($row['po_quantity'], 2), '0'), '.') }}</td><td class="center">{{ rtrim(rtrim(number_format($row['received_quantity'], 2), '0'), '.') }}</td><td></td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <section class="signatures">
        <div><div class="sign-line">Prepared by</div></div>
        <div><div class="sign-line">Reviewed by</div></div>
    </section>
</main>
</body>
</html>
