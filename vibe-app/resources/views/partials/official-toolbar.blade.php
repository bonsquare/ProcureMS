{{--
    Screen-only actions for every official document: Close and Print, nothing else (docs/specs.md, "Print toolbar").
    The paper size is chosen in the browser's own print dialog. Never printed.
--}}
<style>
    .official-toolbar__context{max-width:40vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 8px;font:700 11px Arial,sans-serif;letter-spacing:.04em;color:#536273}
    @media(max-width:700px){.official-toolbar__context{display:none}.official-toolbar{right:10px;left:10px;justify-content:flex-end}}
</style>
<div class="official-toolbar no-print" role="toolbar" aria-label="Official document actions">
    @isset($context)<span class="official-toolbar__context">{{ $context }}</span>@endisset
    <a href="{{ $closeUrl ?? url()->previous() }}" class="secondary" aria-label="Close official document preview" onclick="event.preventDefault(); closePrintTab(this.href);">Close</a>
    <button type="button" class="primary" aria-label="Print or save official document as PDF" onclick="window.print()">Print / Save as PDF</button>
</div>
