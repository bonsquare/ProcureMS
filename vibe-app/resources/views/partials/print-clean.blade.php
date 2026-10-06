{{-- Shared on-screen look for every print page: plain white page, actual-size document, and a small floating action pill that never prints. --}}
<style>
    html, body { background: #fff !important; }
    .print-actions, .toolbar {
        position: fixed !important; top: 14px !important; right: 18px !important; left: auto !important; bottom: auto !important;
        width: auto !important; max-width: calc(100vw - 36px); margin: 0 !important; padding: 6px !important;
        display: flex !important; align-items: center !important; justify-content: flex-end !important; gap: 8px !important;
        background: rgba(255, 255, 255, .97) !important; border: 1px solid #d3d7e0 !important; border-radius: 999px !important;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .18) !important; z-index: 1000 !important;
    }
    .print-actions span, .toolbar span:not(.keep) { display: none !important; }
    .print-actions a, .print-actions button, .toolbar a, .toolbar button { border-radius: 999px !important; padding: 7px 16px !important; font: 600 12px Arial, sans-serif !important; white-space: nowrap; }
    .print-actions a, .toolbar a { text-decoration: none !important; border: 1px solid #00236f !important; background: #fff !important; color: #00236f !important; }
    .print-actions button, .toolbar button { border: 1px solid #00236f !important; background: #00236f !important; color: #fff !important; }
    .print-actions .secondary, .toolbar .secondary { background: #fff !important; color: #00236f !important; }
    .sheet, .page { margin-left: auto !important; margin-right: auto !important; margin-top: 0 !important; margin-bottom: 0 !important; box-shadow: none !important; }
    @media print { .print-actions, .toolbar { display: none !important; } }
</style>
<script>
    function closePrintTab(fallbackUrl) {
        window.close();
        // Browsers only close tabs that were opened from a link. If this one wasn't, return to the page it belongs to.
        setTimeout(function () { if (!window.closed) window.location.href = fallbackUrl; }, 250);
    }
</script>
