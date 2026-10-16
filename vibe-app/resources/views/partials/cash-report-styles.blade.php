{{-- The look shared by the three cash report print pages (black and white, Arial; the print engine and toolbar come from partials.print-clean). --}}
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.3; }
    .paper { width: 100%; margin: 0; padding: 0; background: #fff; }
    .masthead { display: grid; grid-template-columns: 80px 1fr 80px; align-items: center; column-gap: 8px; text-align: center; line-height: 1.4; }
    .masthead-logo { display: flex; align-items: center; min-height: 60px; }
    .masthead-logo-left { justify-content: flex-start; }
    .masthead-logo-right { justify-content: flex-end; }
    .masthead-logo img { height: 62px; width: auto; max-width: 80px; object-fit: contain; }
    .masthead .dept { font-size: 13px; font-weight: bold; }
    .masthead .line { font-weight: bold; text-transform: uppercase; }
    .doc-title { text-align: center; margin: 10px 0 6px; }
    .doc-title h1 { margin: 0; font-size: 14px; letter-spacing: .4px; }
    .doc-title p { margin: 2px 0 0; font-size: 11px; font-weight: bold; }
    .appendix { text-align: right; font-style: italic; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.info td { padding: 2px 4px; vertical-align: top; overflow-wrap: break-word; }
    table.info td.label { width: 17%; font-weight: bold; }
    table.info td.value { font-weight: bold; text-transform: uppercase; }
    table.report th, table.report td { border: 1px solid #222; padding: 3px 4px; vertical-align: top; overflow-wrap: break-word; }
    table.report th { text-align: center; font-weight: bold; vertical-align: middle; }
    table.report td.num { text-align: right; white-space: nowrap; }
    table.report td.center { text-align: center; }
    table.report tr.total td { font-weight: bold; }
    table.report tr.muted td { font-style: italic; }
    .certify { border: 1px solid #222; border-top: 0; padding: 8px 10px; text-align: justify; }
    .certify h2 { margin: 0 0 6px; text-align: center; font-size: 11px; letter-spacing: 3px; }
    .signature { margin: 26px 0 0 auto; width: 45%; text-align: center; }
    .signature .name { font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #222; padding: 0 6px; }
    .signature .role { margin-top: 2px; }
    .signature .date { margin-top: 10px; text-align: left; }
    .sign-row { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 14px; }
    .sign-row .box { text-align: center; }
    .sign-row .box .name { margin-top: 30px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #222; }
    @media print {
        thead { display: table-header-group; }
        thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tr { break-inside: avoid; }
        .certify, .sign-row, .signature { break-inside: avoid; }
    }
</style>
