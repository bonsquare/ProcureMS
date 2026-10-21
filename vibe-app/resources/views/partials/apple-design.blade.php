{{-- Apple-style feel for every app screen (spec 73): instant press feedback, windows that arrive as a material, soft focus rings, tighter headings. Looks and motion only: no size, spacing or layout changes. Official documents and printed reports are left out (data-official-page, print). --}}
{{-- The attribute is written with a CSS escape (d is the hyphen) so the page text does not carry the marker the Drive banner looks for. --}}
@php $screen = 'body:not(:has([data\2d official-page]))'; @endphp
<style data-apple-design>
    @media screen {
        :where({!! $screen !!}) { -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        :where({!! $screen !!}) :where(h1) { letter-spacing: -0.02em; }
        :where({!! $screen !!}) :where(h2, h3):not([class*="tracking"]) { letter-spacing: -0.01em; }

        /* Feedback on the press, not on release. */
        :where({!! $screen !!}) :where(button:not(:disabled), a[class*="ring-1"], a[class*="rounded-lg"]) { transition: transform 140ms cubic-bezier(.2, .8, .2, 1), background-color 160ms ease, color 160ms ease, box-shadow 160ms ease, opacity 160ms ease; }
        :where({!! $screen !!}) :where(button:not(:disabled), a[class*="ring-1"], a[class*="rounded-lg"]):active { transform: scale(.97); }

        /* Keyboard focus is clear and soft. */
        :where({!! $screen !!}) :where(button, a, summary):focus-visible { outline: 2px solid #174b82; outline-offset: 2px; border-radius: .375rem; }

        /* Windows: a blurred, dimmed background and an arrival with a soft spring. */
        :where({!! $screen !!}) dialog::backdrop { backdrop-filter: blur(6px) saturate(140%); }
        :where({!! $screen !!}) [role="dialog"].fixed { backdrop-filter: blur(6px) saturate(140%); }
        :where({!! $screen !!}) dialog[open] { animation: apple-arrive 300ms cubic-bezier(.2, .9, .25, 1.04); }
        :where({!! $screen !!}) dialog { box-shadow: 0 24px 60px -12px rgba(16, 57, 103, .35), 0 8px 20px -8px rgba(0, 0, 0, .2); }
    }
    @keyframes apple-arrive { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) {
        :where(body) dialog[open] { animation: opacity-only 160ms ease; }
        :where(body) :where(button:not(:disabled), a):active { transform: none !important; }
    }
    @keyframes opacity-only { from { opacity: 0; } to { opacity: 1; } }
    @media (prefers-reduced-transparency: reduce) {
        :where(body) dialog::backdrop, :where(body) [role="dialog"].fixed { backdrop-filter: none !important; }
    }
</style>
