# ProcureMS specifications

Standing specifications for the whole system. They apply to every existing and future module. Product rules and phase order are in `CLAUDE.md` and `docs/PROCUREMS_CLAUDE_HANDOFF.md`.

## 1. Official documents: presentation and printing

Applies to every official printed document: SIP, AIP, PPMP, APP, PR, RFQ, Abstract, NOA, PO, NTP, IAR, RIS, IARS, ICS, ORS, DV, Liquidation Report, and any future form.

### 1.1 Principle

These are official government and school documents. Enhance their presentation only. Never invent a new template and never redesign them into cards, dashboards, or decorative layouts.

- Do not change the official structure, wording, sections, fields, labels, sequence, or required information unless technically necessary.
- Keep a formal, simple, black-and-white appearance that stays professional on a normal office printer.
- Do not add colored backgrounds, gradients, icons, shadows, rounded cards, decorative borders, illustrations, background graphics, watermarks, unofficial headings or fields, or decorative fonts. Light fills that already exist in an official template (for example the SIP and AIP column headers) are kept.
- Formatting must never change underlying data (amounts, names, dates, reference numbers, quantities, totals, approvals).
- Do not show `undefined`, `null`, `NaN`, or `[object Object]`. Show a blank or `N/A` as the official field requires.

### 1.2 Typography

- Primary font Arial, then Helvetica, then sans-serif. Keep Times New Roman where the official template requires it (PR, DV, ORS, AIP, SIP).
- Header and agency or school name 10-12pt; document title 12-14pt bold, centered; section headers 9-11pt bold; labels 8-10pt; content 8-10pt; table content 7.5-10pt; footnotes 7-8pt; signatory names 9-10pt bold; positions 8-9pt.
- Bold only where it carries meaning: title, section headings, column headers, important labels, totals, signatory names. Do not bold every field.
- Do not enlarge text needlessly and do not shrink text excessively just to force one page.

### 1.3 Borders, tables, alignment

- Normal table border 0.5-0.75pt solid black; outer border and section separators 0.75-1pt. Use thicker lines only where the official form requires them.
- `border-collapse: collapse`; vertical and horizontal lines aligned; no overlapping or double borders; borders must not disappear when printed.
- Cell padding about `2px 4px` (`3px 5px` for larger fields).
- Descriptions left; column headers centered; quantities centered or right; currency and numeric totals right; dates centered or as the form requires; signatures centered.
- Currency uses one format everywhere: peso sign, thousands separators, two decimals, right aligned.
- Long text (descriptions, purpose, remarks, specifications, supplier names, addresses, project names) wraps inside its cell. Never overflow a border and never truncate official information unless the user chooses to.

### 1.4 Header, logo, school profile

- The header is dynamic. Never hard-code a school name, logo, region, division, district, or office in a template.
- The School Profile (School Settings) is the single source of truth: logo, school name, school ID, region, division, district, address, contact number, email, school head name and position. Do not copy these values into individual documents.
- Header content: Republic of the Philippines, Department of Education, then region, division, district, and school name from the profile.
- Logos come only from `App\Support\OfficialDocument::logos($school, $agency)`: the school's uploaded logo, then the division logo; the left logo is the agency or department logo, then the national DepEd seal. There is deliberately no school-specific fallback.
- A school with no logo prints a blank logo area. No broken-image icon and no placeholder on the printed page.
- Keep the aspect ratio (`object-fit: contain`); roughly 18-25mm maximum printed size; scale down very large uploads.
- Accept PNG, JPG, JPEG, and WEBP; prefer transparent PNG.

### 1.5 Paper size, orientation, margins

- The user chooses the paper before printing. Required options: A4 (210 x 297mm), Letter / Short Bond (215.9 x 279.4mm), Legal (215.9 x 355.6mm), Long Bond (215.9 x 330.2mm), Folio (210 x 330mm), and Custom (width and height in mm).
- Portrait and landscape are both available. Each form keeps its normal orientation by default and never changes orientation on its own.
- Never assume the browser's default paper size. The selected size is written to `@page`.
- Use real print units (mm, cm, pt), not pixels, for final dimensions. Margins follow the selected paper; nothing may touch the paper edge.
- When a form does not fit the chosen paper, adapt in this order: preserve structure, preserve table proportions, preserve readable fonts, adjust margins slightly, adjust padding slightly, and only then scale. Do not shrink excessively.
- A form that fits one page on its own paper is a one-page form on any paper: it is scaled to the sheet, not spilled onto a second page. A longer document (item lists, reports) flows across pages with a small top and bottom margin on every page.

### 1.6 Print controls and preview

- Controls are UI only and sit outside the document: Paper Size, Custom width and height, Orientation, Zoom, Print / Save as PDF. They never print.
- The on-screen preview may show a grey desk, a page shadow, and page guides. All screen-only effects disappear in print: white background, black text, official borders, no shadow, no rounded corners, no controls, no scrollbars.
- The preview shows the selected paper, orientation, margins, header, logo, tables, signatures, and approximate page breaks. The browser print is authoritative.

### 1.7 Pagination

- Avoid splitting signature blocks, approval and certification sections, summary totals, the header, and small tables (`break-inside: avoid`).
- Long tables continue across pages and repeat their column headings (`thead { display: table-header-group }`). This matters most for procurement documents, liquidation, accounting schedules, budget reports, abstracts, itemized lists, and purchase orders.
- No unexpected blank pages, no clipped content, no overlapping text.
- Keep enough blank space for physical signatures: name line, then position or designation. Do not compress signature blocks to fit a page.
- Do not add a footer, page numbers, or document metadata unless the form specification allows it. If a form already has a footer, keep it.

### 1.8 Implementation standard

- Every official document uses the shared print engine: `resources/views/partials/print-clean.blade.php` (styles and script) and `resources/views/partials/official-toolbar.blade.php` (controls).
- A document opts in by putting `data-official-page` on its page element, plus:
  - `data-doc`: key used to remember the user's paper choice per kind of document;
  - `data-paper`: `a4`, `letter`, `legal`, `longbond`, or `folio`;
  - `data-orientation`: `portrait` or `landscape`;
  - `data-margin`: page margin in mm for forms that rely on `@page` margins (default 0);
  - `data-single-page`: fixed one-page forms such as the SVG-based DV and ORS.
- A document must not declare its own `@page` rule or its own toolbar. A multi-copy form (such as the RFQ) separates its copies with `break-before: page`.
- `?paper=a4&orientation=landscape` (or `paper=custom&width=&height=`) opens a document on that paper without saving the choice. Use it for tests.
- Shared values (font family, sizes, border widths, cell padding, margin) live as CSS variables in the print engine. Different forms keep their own columns, sections, certifications, signatures, and reference numbers; the shared standard only keeps them visually consistent.
- Reuse the existing architecture. Do not rebuild working modules or modify unrelated ones.

### 1.9 Acceptance checklist

Before an official-document change is finished, verify with a real browser print (Chrome or Edge, including Save as PDF):

1. A4, Letter, Legal, and Long Bond, each in portrait and landscape.
2. Custom paper size.
3. With a logo and without a logo.
4. Very long school name and very long item descriptions.
5. A large number of table rows and a multi-page document.
6. A signature area near a page boundary.
7. No overlapping elements, cut-off text, broken borders, unexpected blank pages, stretched logos, or unreadably small text.
8. Physical print preview where a printer is available.

Automated coverage lives in `tests/Feature/OfficialPrintTest.php`. The headless-Chrome PDF check used during development opens each document with the `?paper=` query, prints it to PDF, and compares page count and page size.
