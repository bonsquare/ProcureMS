<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer margin test</title>
    <style>
        @page { size: {{ $width }}mm {{ $height }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e5e7eb; color: #000; font-family: Arial, sans-serif; }
        .bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #c3ccd6; font-size: 13px; }
        .bar a, .bar button { min-height: 36px; padding: 0 14px; border: 1px solid #00236f; border-radius: 6px; background: #fff; color: #00236f; font: 600 13px Arial, sans-serif; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; }
        .bar button { background: #00236f; color: #fff; }
        .sheet { position: relative; width: {{ $width }}mm; height: {{ $height }}mm; margin: 16px auto; background: #fff; overflow: hidden; }
        .ln { position: absolute; background: #000; }
        .lb { position: absolute; font-size: 5.5pt; line-height: 1; white-space: nowrap; }
        .note { position: absolute; left: 30mm; right: 30mm; top: 50%; transform: translateY(-50%); text-align: center; font-size: 11pt; line-height: 1.45; }
        .note h1 { margin: 0 0 3mm; font-size: 15pt; }
        @media print { html, body { background: #fff; } .bar { display: none; } .sheet { margin: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <strong>Printer margin test · {{ $paperName }}{{ $landscape ? ' · landscape' : ' · portrait' }}</strong>
        <a href="{{ route('school-settings.printing.margin-test', ['paper' => request('paper'), 'orientation' => 'portrait']) }}">Portrait</a>
        <a href="{{ route('school-settings.printing.margin-test', ['paper' => request('paper'), 'orientation' => 'landscape']) }}">Landscape</a>
        <a href="{{ route('school-settings', ['tab' => 'printing']) }}">Back to Printing</a>
        <button type="button" onclick="window.print()">Print this page</button>
        <span>In the print window choose Margins: None and Scale: 100.</span>
    </div>
    <div class="sheet">
        {{-- Lines every 1 mm, 0 to 20 mm from each edge, each with its number: the first line the printer shows on a side is the smallest margin it can print. --}}
        @for($mm = 0; $mm <= 20; $mm++)
            @php $col = ($mm % 3) * 7; $lw = 38; @endphp
            {{-- top edge: horizontal lines, centred across the page --}}
            <div class="ln" style="left: calc(50% - {{ $lw / 2 }}mm); top: {{ $mm }}mm; width: {{ $lw }}mm; height: .25mm"></div>
            <div class="lb" style="left: calc(50% + {{ $lw / 2 + 1 + $col }}mm); top: {{ $mm - 0.4 }}mm">{{ $mm }}</div>
            {{-- bottom edge --}}
            <div class="ln" style="left: calc(50% - {{ $lw / 2 }}mm); bottom: {{ $mm }}mm; width: {{ $lw }}mm; height: .25mm"></div>
            <div class="lb" style="left: calc(50% + {{ $lw / 2 + 1 + $col }}mm); bottom: {{ $mm - 0.4 }}mm">{{ $mm }}</div>
            {{-- left edge: vertical lines, centred down the page --}}
            <div class="ln" style="top: calc(50% - {{ $lw / 2 }}mm); left: {{ $mm }}mm; height: {{ $lw }}mm; width: .25mm"></div>
            <div class="lb" style="top: calc(50% + {{ $lw / 2 + 1 + $col }}mm); left: {{ $mm - 0.5 }}mm">{{ $mm }}</div>
            {{-- right edge --}}
            <div class="ln" style="top: calc(50% - {{ $lw / 2 }}mm); right: {{ $mm }}mm; height: {{ $lw }}mm; width: .25mm"></div>
            <div class="lb" style="top: calc(50% + {{ $lw / 2 + 1 + $col }}mm); right: {{ $mm - 0.5 }}mm">{{ $mm }}</div>
        @endfor
        <div class="note">
            <h1>Printer margin test</h1>
            <p>{{ $paperName }}, {{ $landscape ? 'landscape' : 'portrait' }}. Printed with margins None and scale 100.</p>
            <p>On each side of this page, find the <strong>smallest number you can see</strong> next to a line. That number is the margin (in mm) your printer can print on that side. Enter it, plus 1 mm to be safe, in Settings, Printing.</p>
            <p>Top: ____ mm &nbsp; Right: ____ mm &nbsp; Bottom: ____ mm &nbsp; Left: ____ mm</p>
        </div>
    </div>
</body>
</html>
