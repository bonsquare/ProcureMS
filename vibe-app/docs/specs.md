# ProcureMS specifications

Standing specifications for the whole system. They apply to every existing and future module. Product rules and phase order are in `CLAUDE.md` and `docs/PROCUREMS_CLAUDE_HANDOFF.md`. Last reviewed 2026-10-09.

> **PROTECTED DOCUMENT. Nothing in this file is changed without the owner's permission.**
> - This file is the owner's standing decision record. No person, assistant or tool may edit, reword, reorder, shorten, delete or add to it on its own initiative, including "small" fixes, typo fixes and tidy-ups.
> - To change it: describe the exact change in the conversation (which section, old text, new text) and wait for the owner's explicit yes to that change. A yes to a feature or a code change is **not** permission to edit this file. A yes to one edit is not permission for another.
> - New work is recorded here only after the owner has asked for it to be recorded.
> - If the code or a request contradicts something written here, stop and ask the owner. Do not change the specification to match the code, and do not change the code against the specification.
> - Every approved change gets its own commit whose message says `specs:` and what changed.

Contents: 1 Official documents (presentation and printing) · 2 Procurement workflow · 3 Supplier directory · 4 Dashboards · 5 School Settings · 6 Pre-registration · 7 Screen design standards · 8 Local development.

## 1. Official documents: presentation and printing

Applies to every official printed document: SIP, AIP, PPMP, APP, PR, RFQ, Abstract, NOA, PO, NTP, IAR, RIS, IARS, ICS, ORS, DV, Liquidation Report, and any future form.

### 1.1 Principle

These are official government and school documents. Enhance their presentation only. Never invent a new template and never redesign them into cards, dashboards, or decorative layouts.

- Do not change the official structure, wording, sections, fields, labels, sequence, or required information unless technically necessary.
- Keep a formal, simple, black-and-white appearance that stays professional on a normal office printer.
- Do not add colored backgrounds, gradients, icons, shadows, rounded cards, decorative borders, illustrations, background graphics, watermarks, unofficial headings or fields, or decorative fonts. Light fills that already exist in an official template (for example the SIP and AIP column headers) are kept.
- Formatting must never change underlying data (amounts, names, dates, reference numbers, quantities, totals, approvals).
- Do not show `undefined`, `null`, `NaN`, or `[object Object]`. Show a blank or `N/A` as the official field requires. A document must still print when its saved details are empty (seeded and legacy records have no metadata); `tests/Feature/ProcurementOfficialToolbarTest.php` prints every document type with no details to guard this.

### 1.1a Templates are fixed: only the content mapping changes

Every official document follows the template file the owner provided for it: its columns, headings, captions, notes, order, colors and layout. A request about a document is a request about what is filled in (the content mapping: which data goes into which cell, what is typed or chosen, defaults and calculations), never about the template itself.

- Do not remove, add, rename, reorder or restyle a column, heading, caption, note or section of a template because a request mentions it.
- When a request could mean either the template or its content, open the provided template file first and keep the template as it is. If the request still conflicts with the template, ask the owner.
- A template changes only when the owner supplies a new template file or says in so many words that the template itself is to change.

### 1.2 Typography

- Primary font Arial, then Helvetica, then sans-serif. Keep Times New Roman where the official template requires it (PR, AIP, SIP).
- Header and agency or school name 10-12pt; document title 12-14pt bold, centered; section headers 9-11pt bold; labels 8-10pt; content 8-10pt; table content 7.5-10pt; footnotes 7-8pt; signatory names 9-10pt bold; positions 8-9pt.
- Bold only where it carries meaning: title, section headings, column headers, important labels, totals, signatory names, and filled-in data (1.3a). Do not bold every field.
- Do not enlarge text needlessly and do not shrink text excessively just to force one page. The one exception is a printed name that is too long for its cell: it shrinks only as far as it needs (down to about 6pt) so it stays on one line, and wraps only if even that is not enough (RIS, `official-ris.blade.php`).

### 1.3 Borders, tables, alignment

- Normal table border 0.5-0.75pt solid black; outer border and section separators 0.75-1pt. Use thicker lines only where the official form requires them.
- `border-collapse: collapse`; vertical and horizontal lines aligned; no overlapping or double borders; borders must not disappear when printed.
- Cell padding about `2px 4px` (`3px 5px` for larger fields).
- Descriptions left; column headers centered; quantities centered or right; currency and numeric totals right; dates centered or as the form requires; signatures centered.
- Currency uses one format everywhere: peso sign, thousands separators, two decimals, right aligned.
- Long text (descriptions, purpose, remarks, specifications, supplier names, addresses, project names) wraps inside its cell. Never overflow a border and never truncate official information unless the user chooses to.
- Letters (NOA, NTP) use block paragraphs: no first-line indent, justified, so a highlighted paragraph is one clean block.

### 1.3a Bold data

**Owner decision (2026-10-08):** filled-in data stands out from the printed labels.

- **Bold:** the agency or department name, division, district, region, school name, entity, department, office, fund cluster, document and reference numbers (PR No., RIS No., ORS serial no.), dates, fiscal year, payee or supplier name and address, TIN, responsibility center and UACS codes, purpose, and signatory names. Labels keep the weight the official form gives them.
- **Never bold:** item descriptions, units, quantities, unit costs, line amounts, particulars lines, and the AIP and SIP activity tables. Totals and grand totals may be bold.
- Implementation: the procurement documents (PR, RFQ, Abstract, NOA, NTP, PO, IAR, RIS, IARS, ICS, delivery reconciliation) include `resources/views/partials/procurement-emphasis.blade.php`, which lists their data cells. AIP and SIP bold the department name and fiscal year in their own styles. A new form must apply the same rule.
- Long bold values must still wrap and never break a column.
- The heavier 1.5px borders tried on 2026-10-08 are **not** in force; borders stay as in 1.3. Bring them back only on the owner's request.

### 1.3b ORS and DV forms

The Obligation Request and Status (COA Appendix 11) and the Disbursement Voucher (COA Appendix 32) are the original SVG replicas on Long Bond portrait (`ors-print.blade.php`, `dv-print.blade.php`, `data-single-page`). An HTML rebuild was tried on 2026-10-08 and **reverted on 2026-10-09** at the owner's request; do not rebuild them without being asked.

- Keep every label, section letter, and wording of the official form.
- The header is dynamic from Agency Settings and the school profile. Never hard-code a division name.
- `liquidation_reports.dv_include_appropriation` and the Accounting "Appropriation table" option exist, but the SVG DV does not draw that table. Do not describe it as printed until the DV supports it.

### 1.4 Header, logo, school profile

- The header is dynamic. Never hard-code a school name, logo, region, division, district, or office in a template.
- School Settings is the source of truth (section 5): Public Header (republic, department, address, email, phone, agency logo), Division Office Details (region, school division office, address, email, contact, division logo, then the District Office), and School details (name, type, address, contact, email, school logo). Do not copy these values into individual documents.
- Header content: Republic of the Philippines, Department of Education, then region, division, district, and school name from the profile.
- Logos come only from `App\Support\OfficialDocument::logos($school, $agency)`: the school's uploaded logo, then the division logo; the left logo is the agency or department logo, then the national DepEd seal. There is deliberately no school-specific fallback. The district has no logo of its own on screen or on paper.
- A school with no logo prints a blank logo area. No broken-image icon and no placeholder on the printed page.
- Keep the aspect ratio (`object-fit: contain`); roughly 18-25mm maximum printed size.
- Upload rules (section 5.4): PNG, JPG, WebP or GIF, up to 2 MB, square, at least 300 x 300 px. SVG is not accepted.
- A master user sees every organization, so any code that reads the agency record must take the one of the school or request in hand (`AgencySetting::withoutGlobalScopes()->where('organization_id', ...)`), never a bare `AgencySetting::first()`. School Settings does this; the print controllers still use `first()` and should be moved over.

### 1.5 Paper size, orientation, margins

- The user chooses the paper before printing from the toolbar of the print page: A4 (210 x 297mm), Letter / Short Bond (215.9 x 279.4mm), Legal (215.9 x 355.6mm), Long Bond (215.9 x 330.2mm), Folio (210 x 330mm), and Custom (width and height in mm).
- Portrait and landscape are both available. Each form keeps its normal paper and orientation by default (`data-paper`, `data-orientation`) and never changes orientation on its own.
- The selected size is written to `@page`. Never assume the browser's default paper.
- Use real print units (mm, cm, pt), not pixels, for final dimensions. Nothing may touch the paper edge.
- When a form does not fit the chosen paper, adapt in this order: preserve structure, preserve table proportions, preserve readable fonts, adjust margins slightly, adjust padding slightly, and only then scale. A form that fits one page on its own paper is a one-page form on any paper: it is scaled to the sheet, not spilled onto a second page. A longer document flows across pages.

### 1.6 Print controls and preview

- The preview bar is UI only and sits outside the document. It never prints. It shows only what is needed to read the document: **Close**, the document title, a **page setup** gear, and **Print / Save as PDF**. Paper size, orientation and zoom live in the page setup popover so the document keeps the space (decided 2026-10-10, after the first redesign showed everything in one row and was "too much information").
- The preview shows a soft desk, a page shadow, and page guides. All of it disappears in print: white background, black text, official borders, no shadow, no bar.
- One copy for every document (PR, RFQ, ABQ, NOA, NTP, PO, IAR, IARS, RIS, ICS, PAR, ORS, DV, AIP, SIP, delivery reconciliation): do not write document-specific toolbars. The title comes from the `context` value passed when the partial is included, for example `@include('partials.official-toolbar', ['closeUrl' => ..., 'context' => 'Disbursement Voucher · '.$report->dv_number])`. Without it the bar says "Official document".
- Source: `resources/views/partials/official-toolbar.blade.php` (markup and the small popover script) and `resources/views/partials/print-clean.blade.php` (all styles, the print rules and the paper/zoom behavior).
- The browser print is authoritative.

#### 1.6a Print preview UI: layout and how to change it

Layout (screen only):

```
[ <- Close | PRINT PREVIEW            ]                    [ gear ] [ printer  Print / Save as PDF ]
[           Disbursement Voucher · DV-2026-0001            ]
                                                            └─ gear opens "Page setup": Paper Size,
                                                               (Width/Height when Custom), Orientation,
                                                               Zoom, and the print-dialog tip
```

| Part | Markup (`official-toolbar.blade.php`) | Style class (`print-clean.blade.php`) |
|---|---|---|
| Bar | `.official-toolbar.no-print` (`role="toolbar"`) | `.official-toolbar` |
| Close button | `a.ot-btn.ot-btn--ghost` | `.ot-btn--ghost` |
| Title and label | `.ot-title`, `.ot-eyebrow` ("Print preview") | `.ot-title`, `.ot-eyebrow` |
| Gear button | `details.ot-setup > summary.ot-btn--icon` | `.ot-setup`, `.ot-btn--icon` |
| Page setup popover | `.ot-pop` with `.ot-fields`, `.ot-row`, `.ot-custom`, `.ot-pop__hint` | `.ot-pop*`, `.ot-field` |
| Print button | `button#op-print.ot-btn--primary` | `.ot-btn--primary` |

Look and feel (change these first):

| What | Where | Current value |
|---|---|---|
| Bar background | `.official-toolbar` `background` | gradient `#0b2a66` to `#103967` to `#1b5088` |
| Bar accent line | `.official-toolbar` `border-bottom` | `3px solid #369878` |
| Bar height | `.official-toolbar` `padding`, `.ot-btn` `height` | `8px 18px`, buttons `38px` |
| Print button colors | `.ot-btn--primary` `background` and shadow | green gradient `#3fae8a` to `#2a7f64` |
| Ghost and gear buttons | `.ot-btn--ghost`, `.ot-btn--icon` | `rgba(255,255,255,.1)` with a light border |
| Popover | `.ot-pop` | white card, width `320px`, radius `14px`, soft shadow |
| Fields in the popover | `.ot-pop select`, `.ot-pop input[type="number"]` | height `36px`, radius `9px`, background `#f4f7fa` |
| Desk behind the page | `body` `background` | soft blue glow on `#e6ebf1` |
| Page shadow | `.official-sheet` `box-shadow` | two layers, `0 10px 32px` at 20% |
| Font | `.official-toolbar` `font` | Inter, then Segoe UI, system-ui |

Common changes:

- **Show Paper Size, Orientation and Zoom in the bar again:** move the `.ot-fields` block out of the `<details class="ot-setup">` into `.ot-right` (before the gear), and remove the `<details>` wrapper. The ids must stay.
- **Remove the gear completely:** delete the `<details class="ot-setup">` block. The settings then default to the document's own paper and Fit width, and the paper cannot be changed.
- **Change the title:** pass `'context' => '...'` where the partial is included.
- **Change the colors:** edit the hex values above; the brand colors are primary `#103967`, action `#286da8`, secondary `#369878`.
- **Add a button:** add an `a` or `button` with class `ot-btn ot-btn--ghost` inside `.ot-right` (before `#op-print`).

Rules that must keep working:

- Element ids used by the preview script: `op-paper`, `op-orientation`, `op-zoom`, `op-width`, `op-height`, `op-custom`, `op-print`. Do not rename them.
- The bar must stay `class="official-toolbar no-print"` and the print CSS must keep `.official-toolbar, .no-print { display: none !important; }`.
- The labels "Paper Size" and "Orientation", and the text "Print / Save as PDF" and "Long Bond", are asserted by tests (`ProcurementOfficialToolbarTest`, `OfficialPrintTest`). If you rename them, update the tests.
- Only styles inside the toolbar and `.official-sheet` screen rules change the preview; the `@media print` rules decide what is printed and should not be touched for a visual change.
- The preview iframes on the Documents page and the PR form hide the bar with a small injected style; keep the `.official-toolbar` class name.

### 1.7 Pagination

- Avoid splitting signature blocks, approval and certification sections, summary totals, the header, and small tables (`break-inside: avoid`).
- Long tables continue across pages and repeat their column headings (`thead { display: table-header-group }`).
- No unexpected blank pages, no clipped content, no overlapping text.
- **The page filler never exceeds the real page.** A form page is stretched to the paper chosen in the toolbar (`min-height`, set by the print engine). In print that height is capped to the real page height (`.official-fill`, `100vh`), so a printer that falls back to a shorter paper (A4 instead of Legal) never prints an empty page after every form page. If the dialog paper differs from the toolbar paper the content can still run onto an extra page; pick the printer's real paper in the toolbar first.
- Keep enough blank space for physical signatures. Do not compress signature blocks to fit a page.
- Do not add a footer, page numbers, or document metadata unless the form specification allows it.

### 1.8 Implementation standard

- Every official document uses the shared print engine and puts `data-official-page` on its page element, plus `data-doc` (key used to remember the paper choice), `data-paper`, `data-orientation`, `data-margin` (mm, default 0) and `data-single-page` for fixed one-page forms (ORS, DV).
- A document must not declare its own `@page` rule or its own toolbar. A multi-copy form (such as the RFQ) separates its copies with `break-before: page`.
- `?paper=a4&orientation=landscape` (or `paper=custom&width=&height=`) opens a document on that paper without saving the choice. Use it for tests.
- Live previews (sections 2.5 and 2.6) embed the real print pages in an iframe with the toolbar hidden (`.official-toolbar{display:none}`). Do not build a second renderer.
- Reuse the existing architecture. Do not rebuild working modules or modify unrelated ones.

### 1.9 Acceptance checklist

Before an official-document change is finished, verify with a real browser print (Chrome or Edge, including Save as PDF):

1. The form's normal paper (page count and page size correct), and A4, Letter, Legal, and Long Bond in portrait and landscape; custom paper size.
2. With a logo and without a logo.
3. Very long school name, very long printed names, and very long item descriptions.
4. A large number of table rows and a multi-page document.
5. A signature area near a page boundary.
6. No overlapping elements, cut-off text, broken borders, unexpected blank pages, stretched logos, or unreadably small text.
7. Physical print preview where a printer is available.

Automated coverage: `tests/Feature/OfficialPrintTest.php` and `tests/Feature/ProcurementOfficialToolbarTest.php`.

## 2. Procurement workflow

Source of truth: `App\Services\ProcurementWorkspaceService`. Every list, card, rail, and button that shows a stage, progress, or next action reads it (`summary()` or `present()`); no screen computes its own.

### 2.1 Purchase Request

- Created in the civic shell (`/procurement/create`) with a live preview of the PR on the right (1280px and wider). The preview is built in memory from the form (`POST /procurement/preview`); nothing is saved, no number is reserved.
- Form sections are numbered cards. Item rows: Unit comes from the Units list (2.7); **Add Item** sits under the items. **Extra blank print rows**: 0 to 100, stepper control.
- Status path: `draft`/`returned` -> `pending_approval` (submit) -> `approved` | `returned` | `rejected` (approver, needs `procurement.approve`) -> `for_canvass` (Start canvass) -> `completed`.

### 2.2 Document order

PR (approved) -> **RFQ -> Abstract -> NOA -> NTP -> PO** -> receiving: **IAR -> IARS -> RIS** -> optional **ICS** and **PAR** (only when needed; they never hold up completion).

- Eight required steps: RFQ, Abstract, NOA, NTP, PO, IAR, IARS, RIS. Progress reads "x of 8 steps".
- The NTP comes before the PO because the PO says "within N days of receipt of the NTP". The PO picks up the delivery terms (days, delivery term, payment term, mode, fund) from the NTP when it is not given its own. The number of calendar days is typed by hand on the NTP (no default; required for the MOOE wording, not used by the scheduled-delivery wording); the PO form shows it read only. The NTP never reads from a PO.
- Wherever documents are listed in a series (the cards on a request's documents page, the Document Center type filter) the order is the workflow order: RFQ, Abstract, NOA, **NTP, then PO**, IAR, IARS, RIS, then ICS and PAR. `HomeController::procurementDocumentTypes()` holds that order; keep it in step with `ProcurementWorkspaceService::PREREQUISITES`.
- A document can be prepared only after the PR is approved and the document before it exists (`ProcurementWorkspaceService::PREREQUISITES`, `blockedReason()`). The Procurement Documents page shows a locked card with the reason; the server enforces it on save and in the live preview.
- IAR, IARS, RIS, ICS, and PAR live under **Receiving** (`?stage=receiving`, tab "Receiving documents"); the Receiving tab links straight there. The first five documents live on the normal Procurement Documents page.

### 2.3 Stage, progress, completion

- Stages: Request, Approval, Canvass, Award (Abstract, NOA, NTP), Purchase Order, Receiving (IAR, IARS, RIS), Complete.
- A request is **Complete** only when its status is `completed` **and** all eight required documents exist. A status flipped early does not make a stage complete. "Mark complete" appears only when the documents are complete and is refused by the server otherwise.
- Document Center: **one row per request**, showing its most recently updated document (among those that match the Document type and Status filters) with a "+N more" badge when the request has other documents; paging counts requests. One **Process** button per row (opens that request's documents, or the Receiving documents for a receiving document) and an eye icon beside the progress to open the document shown. Progress chip: **On processing** until the request is Complete, then **Delivered**; with "x of 8 steps".
- Request page: one box holds **Next action** and the button that fits the **stage** (Start canvass only at Canvass; Open documents at Award and Purchase Order and Complete; Open receiving documents at Receiving; Mark complete when allowed). Start canvass sends the user straight to the documents.

### 2.4 Documents page

- Cards are colored by state: prepared green, not prepared light red, coming soon grey. The PR card is always prepared.
- The document form opens as a split view: form on the left, the real document on the right (2.5). The modal starts to the right of the sidebar and widens by itself when a form needs room (bidder tables, IARS distribution).
- The RIS generation summary is a list: number, staff name and position, issued items, RIS number.
- **Abstract of Bids, a missing company:** the form has "Add supplier in Supplier Management" (users with `supplier.manage` only). It opens `suppliers.create?return_to=abstract&return_request={id}`, which shows a **Back to Abstract of Bids** button (and Cancel returns there); saving returns to `/procurement/{id}/documents?open=abstract_of_bids_quotation`, which reopens the abstract with the new supplier (saved under that request's school) in the company list. The companies and prices typed before leaving are kept in `sessionStorage` and restored. The return target is checked server side (digits only, a request the user may open); anything else is ignored.
- **Notice to Proceed days:** typed by hand in "Number of calendar days to complete delivery" (no default, 1 to 365). Required by the server for the MOOE wording (`template_variant` `mooe`/`thirty_days`), hidden for the scheduled-delivery wording (SBFP). The NTP never reads from a Purchase Order. The PO form shows the NTP's days read only, and the server copies the NTP's delivery terms to the PO when the PO has none.

### 2.5 Live document preview

`POST /procurement/{request}/documents/preview` runs the real save inside a transaction, renders the print page, and rolls back. It reuses every validation and rule of saving, so it can never disagree with the saved document. It debounces input (1 s) and offers fit-width and 50 / 75 / 100% zoom. A preview that fails (server restarting, dropped connection, HTTP 5xx) is retried twice by itself (1.5 s, 4 s); a failure that stays says why ("server error 503", "no connection to the server", "your session expired, reload the page") and the message can be clicked to retry. A 419 is not retried.

### 2.6 Dashboards of work

- Overview (Procurement) shows requests needing attention and a task queue; Requests, Documents, Receiving, Suppliers, and Units are tabs of the module.
- Admin school filter (master user) is kept while moving between the tabs.

### 2.7 Units

`/procurement/units` lists the built-in units (piece, box, pack, ream, set, liter) and the organization's own. Anyone with `supplier.manage` can add or remove their own; duplicates (any letter case) are refused; removing a unit never changes requests that already use it. Units are per organization.

## 3. Supplier directory

- List: compact table (business, school for the master user, contact, tax and TIN, registrations with Expired / Expires soon warnings, status, edit icon); search covers business, contact, TIN, email, phone.
- Add and edit use one full page (`/procurement/suppliers/create`, `.../{id}/edit`) in two columns, in the order the documents show the information: Business information, Company Owner Details (salutation, given name, middle initial, surname, letter addressee such as "The Manager"), Complete address (auto-filled from its parts, editable); then BIR Registration (TIN, tax type VAT / Non-VAT / VAT exempt, rate, BIR certificate), Business Registrations and Permits (Mayor's permit and validity, PhilGEPS and validity, DTI, SEC, CDA), Contact (person, position, contact number), Payment details, Notes.
- Only `supplier.manage` roles add or edit; others see the list.

## 4. Dashboards

- **School user:** "School Dashboard" for their own school and fiscal year: eight KPI cards (budget utilization and balance, purchase requests, pending approval, in progress, completion rate, liquidation to review, active suppliers), request pipeline, requests per month, budget flow, needs-your-attention list, budget by quarter, recent requests. No action buttons in the header; the sidebar leads to the work.
- **Master user:** two views switched in the top bar: **System Master Dashboard** (platform numbers, System KPIs strip above Recent Schools & Organizations, schools list, system health, tasks, audit log) and **KPI Dashboard** (the KPI cards for all schools plus a School performance table). Schools for Activation lives on the Subscriptions page, not on the dashboard.
- Figures come from `App\Services\DashboardKpiService` (budget numbers use the same calculation as the Budget module).
- A school user sees only their own school's figures (covered by tests).

## 5. School Settings

Three tabs (`?tab=info|users|staff`) under a small dashboard (profile complete %, system users, employees, roles assigned, logos set x of 3).

### 5.1 School Information

Each section is locked until **Edit** is pressed, then **Cancel** and **Save** (first time) or **Update**; each saves on its own and leaves the others untouched.

- **Public Header:** agency logo, republic name, department / agency, address, email, phone.
- **Division Office Details:** division logo, region, school division office, division address, email, contact number; a small separator, then **District Office** (district office, address, email, contact no.). Only the division has a logo.
- **School details:** school logo, **School ID (auto-generated, read-only)**, **DepEd School ID** (typed, optional; `schools.deped_school_id`), school name, type, address, contact no., email, and the school's **Bank**, **Account name** and **Account no.** (optional; the defaults of Record Payment, 33.5).
- The School ID is generated when the school is created and never typed. The User ID is shown per account in System Users, not here.
- Fiscal year and document numbering are not editable here; documents keep numbering as before.

### 5.2 System Users

- Table: name, email, User ID and username, position, **official station (the school)**, system role, last sign-in, password date, status. Edit, Password. Users cannot be added here: one user manages one school.
- **Full name and username never change** after the account exists (read-only on screen and ignored by the server, even for the master user).
- **Master User tab** (`/user-management?tab=master-user`, master user only): the master edits their own account (full name, username, e-mail, phone, position; unique e-mail and username) and changes their password (current password required, at least 12 characters, different from the current one). Role and status are not part of this form. Written to the audit log (`master_profile_updated`, `master_password_changed`). The master user may change their own name and username; for other accounts the rule below still applies. Tests: `tests/Feature/MasterUserTabTest.php`.
- **Only the master user changes a role.** A school administrator adds people as Viewer and cannot assign `school_admin`; role changes sent by a non-master are ignored. Nobody changes their own role or status.
- Changing your own password needs the current password; an administrator can set another user's password. An inactive account cannot sign in. Sign-in accepts the email address or the username: the box is a plain text field labelled "Email or username" (a type=email field made the browser refuse a username), and a username is matched in lower case like it is stored. User IDs look like `USR-000009`.

### 5.3 Employees and roles

- Employees (not necessarily system users) with a position (for example Administrative Officer II) and an auto employee number (`EMP-000012`).
- One person holds many roles in three groups: **BAC** (Chairperson, Vice Chairperson, Secretariat, Member, TWG Member, Observer), **Procurement** (Requesting Officer, Procurement Officer, Approver, Canvasser, Supply Officer), **Documents** (Disbursing Officer, Inspection Officer, Property Custodian, Accountant, Budget Officer, Cashier). A school adds its own role to any group.
- Roles are stored comma separated in `bac_role`, `procurement_role`, `document_role`; always read them through `SchoolStaff::hasRole()` / `rolesFor()`, never with `=`.
- **Add employee** and **Employee roles** (edit) dialogs both carry "Need a role that is not listed?": the role is added in the background (`school-settings.roles.store`, JSON when requested), appears ticked in the chosen group of both dialogs, and nothing typed is lost. Buttons: **Add employee** and **Update employee**.
- **Status Active / Inactive** (`school_staff.is_active`, default active; set in both dialogs). An inactive employee stays in the list (greyed, "Inactive" badge) and can be switched back on, but is left out of the signatory lookups for documents (`SchoolStaff::active()` in the procurement and liquidation document pages). This is separate from `ended_at` (left the station, hidden everywhere).

### 5.4 Logos

- Formats PNG, JPG, WebP, GIF; up to 2 MB; square; at least 300 x 300 px; transparent or white background. The same "Logo requirements" box under each logo. Current file name, size, and dimensions are shown; a new choice is checked in the browser (type, size, small-image warning) before saving. The server refuses other types and sizes with a plain message.
- Uploads need a writable `upload_tmp_dir` (section 8).

## 6. Pre-registration

- The first person of a school is entered as Given Name, Middle Initial (one letter or blank), Surname, Username, Position, Email, Contact Number, Password; the full name is assembled ("Maria D. Santos"). The role (School Administrator) is automatic and not shown.
- Before submitting, a dialog shows the full name and username as **final and cannot be changed** and needs a tick; the server requires the confirmation too.
- The dialog opens from a script on the page (`#identity-confirm`): submit is held until the browser's own validation passes, the dialog shows the assembled full name and the lower-case username, "Confirm and submit" stays disabled until the tick, "Go back and edit" closes it without losing anything, and confirming sets `system_user_confirmed` and sends the form. Without that script the server refused every registration.
- **Privacy Policy:** a short plain-English policy (what is collected, why, where it is kept, consent) sits above Submit with a required checkbox `privacy_accepted`, for new-school and takeover registrations alike; the server refuses either without it. The server's date and time and the policy version are saved on the user (`users.privacy_accepted_at`, `users.privacy_policy_version`, `User::PRIVACY_POLICY_VERSION`, currently `2026-10`) and in the pre-registration audit entry. Change the version whenever the policy text changes. Existing users keep a blank value.

## 7. Screen design standards

- **Cards:** white card, thin neutral border, a colored edge on the left, tinted icon chip; color means state (green fine, amber needs attention, red problem, blue information). Same language on every dashboard and list.
- **Highlight blocks:** soft blue `#eef4fa`, 3px navy left edge, no shadow; used sparingly (KPI header, System KPIs, section headers).
- **Module tabs:** segmented pill, navy active tab (`.civic-module-tab`). Procurement pages use the civic shell (`layouts/procurement`); other pages hide the Procurement tabs with `@section('hide-module-tabs')`.
- **Density:** root font 14px in the procurement shell, compact inputs (about 2.35rem), sidebar 15rem.
- **Forms:** numbered section cards, one calm field style, sticky action bar; field values are normal weight, labels bold.
- **Form design standard (approved by the owner 2026-10-16; the Program of Works page is the model, `resources/views/partials/pow-fields.blade.php`):** a form is a stack of **numbered section cards** (white card, `rounded-xl`, thin `outline-variant` border, soft shadow, `overflow-hidden`). Each card starts with a header band (`#eef4fa` with a bottom border): a navy numbered circle, the section title in bold navy and one short hint line under it. Field labels are small, bold, uppercase and spaced (`text-[11px] tracking-wider`, on-surface-variant); inputs are `rounded-lg` with a 1px `outline-variant` border, white background, a soft shadow and a navy focus ring (`focus:ring-2 focus:ring-primary/15`); related fields share a row (title and address full width, dates in three columns, paired text areas in two). Tables sit inside their card with a light heading band, soft row stripes, a light-blue row hover and inputs that fill their cells; money is right aligned, bold in the total column. Totals go in a light-blue summary box (labels in on-surface-variant, the grand total large and navy). People or signature blocks are bordered cards with a navy left edge and a small uppercase navy label. Destructive actions sit apart in a soft red box under the form. **Design only: a restyle never changes a field name, id, `data-` attribute, route, validation or behavior.** Applied so far (2026-10-16): the Program of Works fields (Form Generator and POW page), the SOB page (Add item and Details and signatories cards, the item and particulars fields), the AIP Add / Edit KRA and Activities form with its activity cards, and the APP-CSE page (Details and Signatories cards). The shared `$inputClass` (`AppServiceProvider`) is not changed, so the other screens keep their look until they are restyled in their own batch; new forms use this standard.
- **Account menu:** the round user icon opens name, email, role, school, School Settings, User Management (master), Sign out.
- **Flash messages:** one per page; a page that prints its own declares `@section('flash-handled')`.
- **Show/hide password eye:** `partials/password-toggle.blade.php` adds one eye to every password field. It skips a field that already has its own eye next to it (the sign-in page), and hides the browser's built-in reveal button (`::-ms-reveal`), so two eyes never overlap.
- **Extra blank rows:** a form that can add empty bordered rows to the end of a printed item table (Purchase Request, Purchase Order, Inspection and Acceptance Report, Requisition and Issuance Slip) shows one control, `partials/extra-blank-rows.blade.php`: the label "Extra Blank Rows", a plain number box named `extra_blank_rows` (0 to 100), and the hint "Empty bordered rows added at the end of the printed table (up to 100)." No stepper and no other label. A new form that needs blank rows uses the same partial.
- Blade pitfall: a directive glued to the one before it (`@endif@if(`) is not compiled. Put a space or newline between them.

## 8. Local development

- Run the app with `.claude/launch.json` (`laravel`) or this command from the repository root:

```bash
php -d zend_extension=opcache -d opcache.enable_cli=1 -d "upload_tmp_dir=<repo>/vibe-app/storage/framework/uploads-tmp" -d upload_max_filesize=20M -d post_max_size=24M -S 127.0.0.1:8000 -t vibe-app/public vibe-app/dev-server.php
```

- Why not `php artisan serve`: it does not pass `-d` settings to the real server, so opcache and `upload_tmp_dir` would be missing. Without `upload_tmp_dir` PHP may fail every upload with "unable to create a temporary file". `vibe-app/dev-server.php` is the router; it must `return` the framework router's result or static files (css, images) are served as HTML and the pages lose their design.
- Opcache makes pages about three times faster (about 0.9 s down to 0.03 s per page here).
- Tests: `php artisan test` (all pass at the time of writing: 189).

## 9. Station transfer

- One user manages one school (the Official Station). Users cannot add other users. The subscription is personal (`subscriptions.user_id`) and goes with the user.
- A user asks for a transfer in School Settings, **Station Transfer** tab (school users only): pick a **vacant** registered school (no active user) or "My school isn't listed" with the school's details, plus a reason. One pending request at a time; it can be cancelled while pending.
- The master user decides under the user menu, Transfer requests (`/transfer-requests`). Approving, in one database transaction: creates the school if it is new (no subscription of its own), ends the user's old employee record (kept as history, `school_staff.ended_at`), creates a new employee record at the new school with no roles, and moves the account (`organization_id`, `school_id`). The user's role, username, name and subscription do not change. The school they leave keeps all its data and becomes vacant.
- After approval the user must confirm the new station (`/station-confirm`) before using any other page; they can only confirm or log out. Confirmation is stored on the request (`confirmed_at`).
- School data is never copied or moved: records are scoped by the user's organization, so changing the account is enough.
- Tests: `tests/Feature/StationTransferTest.php`. Design and plan: `docs/superpowers/specs/2026-10-09-station-transfer-design.md`, `docs/superpowers/plans/2026-10-09-station-transfer.md`.

## 10. School Management (master user)

- Sidebar item **School Management** (`/school-management`), master user only. Lists every school with its user, status (Active, Vacant, Inactive) and pending transfers; search by name or School ID and filter by status.
- Open a school to see two cards: **School details** (School ID, type, region, division, district, head, contact, address, registered date) and **User details** (username, User ID, email, mobile, position, role, last sign-in, subscription). Employees are not shown here; they stay in School Settings.
- Actions: set the **school** Active or Inactive (also updates its organization; an inactive school cannot sign in and cannot receive a transferred user); set its **user** inactive (reason Retired, Resigned, Transferred or Other, effective date up to today, note) which makes the school vacant, or reactivate them (refused when the school is inactive or already has another active user).
- Nothing is ever deleted. Subscriptions and all school records are untouched. Every action writes an audit log row with the reason.
- A signed-in user who becomes inactive, or whose school becomes inactive, is signed out on the next request (`EnsureAccountActive`).
- Code: `SchoolManagementService`, `SchoolManagementController`, tests in `tests/Feature/SchoolManagementTest.php`. Design and plan: `docs/superpowers/specs/2026-10-10-school-management-design.md`, `docs/superpowers/plans/2026-10-10-school-management.md`.

## 11. Transfer handover (review by the other school)

- A transfer to a school that has an active user waits for that user to **Accept or Decline** (Station Transfer tab, "Incoming transfer request"). The destination has 5 days from the request; after that the request **expires** automatically and the user can send a new one. A vacant destination needs no review.
- The master cannot approve until the destination accepted. Accepting starts a **5-day handover**, counted from the acceptance: after the master approves and the arriving user confirms, both users have access to the destination school. When the 5 days end the previous user is set inactive (reason Transferred) and signed out. The master can end a handover early ("End handover now" on the school page).
- Only one incoming transfer per school at a time. A destination whose user left before approval counts as vacant.
- Expiry and handover end run from the daily command `transfers:maintain` and also lazily (when the request is used, or when the previous user comes back after the deadline), so access never outlives the date.
- Design: `docs/superpowers/specs/2026-10-10-transfer-handover-design.md`. Tests: `tests/Feature/TransferHandoverTest.php`.

## 12. Vacant school takeover (new staff)

- A school left vacant stays vacant, with its data, until a new person registers and ticks **Take over a vacant school** on the registration page. **The person does not choose a school**: only the school fields are replaced by a short "Take Over Request" note and they fill in the **System User Information** (name, username, e-mail, phone, position, password). An optional note (up to 500 characters, "Which school do you manage?") helps the master. No school is created.
- The person is created as `pending` with no school: they cannot sign in (they see "waiting for the master account approval") or see any school data.
- The request waits in **Official Station Requests** at the top of **Official Station Management** (`/transfer-requests`, the module that used to be called Station Transfer; master, or a Sub-master with the `transfers` access), above the ordinary transfer requests. The sidebar menu item of the same name counts both. The master chooses the **Official Station**, a vacant school (active, no user, no other waiting request), and approves: the person gets that school, its data, the school_admin role, an employee record and their own 30-day trial subscription, and the request then names the school. Approving without a school, or with one that is not vacant, is refused. Decline keeps them out. School Management no longer lists these requests. Requests made before this change that already name a school are still approved from that school's page.
- The **History** on the same page lists the decided Official Station Requests together with the decided transfers, newest first: the person, the Official Station assigned (or none when declined), the status, who decided and the note.
- The form calls the option **Official Station Request**. The user-side tab in School Settings and the master's page are called **Official Station Management** (the Sub-master checklist item too).
- Registration page headings are in Title Case: **School Details** and **System User Information**.
- Design: `docs/superpowers/specs/2026-10-10-vacant-school-takeover-design.md`. Tests: `tests/Feature/VacantSchoolTakeoverTest.php`.

## 13. Journal entry (double entry) on the Disbursement Voucher

- The **Create DV** window has a **Journal entry** table: each line is an account (from the Chart of Accounts, shown as UACS code and title) with a debit or a credit. It starts with two suggested lines for the ORS amount: **debit** the expense account of the budget line the ORS is charged to, and **credit** Cash - MDS, Regular for an MDS Check or ADA (left blank for other payment modes, so the accountant picks the bank account). Lines can be added (up to 4, which is what fits the printed form) and removed.
- A badge shows **Balanced**, **Out of balance by P...** or **Entry must total P...**, and **Create DV** stays disabled until total debit equals total credit and the DV amount.
- The server checks the same rules: at least 2 lines, a known account on every line, each line is a debit or a credit (not both, not neither), total debit = total credit = the ORS amount. Nothing is saved otherwise.
- The lines are saved in `dv_journal_lines` (account code and title as in the chart at that time) and printed in the **Accounting Entry** table of the DV (title, UACS code, debit, credit). DVs created before this change have no lines and print as before.
- Not included: editing the entry after the DV exists, and a separate general journal page.
- Tests: `tests/Feature/DvJournalEntryTest.php`.

### Accounting page (Finance → Accounting)

- Tabs are pills with an icon and a count badge (red for For Review and Awaiting payment, amber for Pending Documents, green for Ready for DV). A search box filters the rows on the page by ORS, payee, school or DV number.
- Each row has a status-colored edge, the payee and how long ago it was approved or submitted, a status chip with icon, and for a DV the DV number and date. A DV's journal entry shows as a "Journal entry · N lines" summary that opens to the lines (Dr / Cr).
- The main action is a filled button (Approve, Create DV, Record Payment); Print, Request Docs and Return are outlined.

## 14. Entering a School Improvement Plan from a document (`sip:import`)

- The SIP module stores a plan as programs (`sip_projects`: pillar, KRA, organizational outcome, strategy, 5-point agenda, program) with activities (`sip_activities`: activity, physical targets for years 1 to 3, financial targets for years 1 to 3, source of fund, responsible person, remarks) and the three signatories (`sip_plans`). These are exactly the columns of the official SIP table.
- A plan prepared as a document can be entered in one step: `php artisan sip:import <file.json> <school id or code>`. The JSON file holds the programs, their activities and the signatories. It refuses a school that already has that plan, so it never duplicates; delete the school's programs first to enter it again.
- The first plan entered this way is **Lubas Elementary School, SIP FY 2026-2028** (`database/seed-data/sip-lubas-2026-2028.json`, from the school's PDF): 33 programs, 116 activities, totals of ₱766,000 (year 1), ₱802,000 (year 2) and ₱693,000 (year 3), signed by Chiqueto E. Domingo (School Head/Team Leader), Julie B. Lumogdang, EdD (Chief, School Governance Operation Division) and Romelito G. Flores, CESO V (Schools Division Superintendent).
- The pillars are stored with the app's own names: Access, Equity, Quality, Well-Being (the PDF prints "Well-Being and Resilience") and Enabling Mechanism.
- Print it from Planning → SIP, which uses the official template (Print preview). Tests: `tests/Feature/SipImportTest.php`.

### Planning page (Planning)

- The top of the page is a guided flow, not four plain cards: a **progress and next-step** panel (plan progress "N of 4", the next thing to do for the selected school and fiscal year, and a button that opens that plan), then the four steps **SIP → AIP → PPMP → APP** as connected cards. Each card shows the step number, its figure (programs, plans, items), its amounts, and a status chip: **done** (green: SIP entered, or AIP / PPMP / APP approved), **awaiting approval** (amber: started, not approved) or **not started** (grey). Settings stays at the end as a dashed tile.
- When the school has a SIP, two summaries follow: the SIP financial target by year (Year 1, 2, 3 bars with the three-year total) and the programs per pillar as colored chips.
- Clicking a card or the button opens that plan's panel below, as before (the cards are the tabs; the address keeps `#sip`, `#aip`, and so on).
- **Deleting an AIP** (Planning → AIP, next to Open and Print): allowed only for a **draft** AIP with no PPMP and no budget built on it, and only for someone who manages the budget. It asks for confirmation, removes the AIP with its KRAs and activities, removes its tracking record when nothing else uses it, and writes an audit entry. An approved or revised AIP cannot be deleted (budget allotments follow it); the Delete link is greyed with an explanation. Tests: `tests/Feature/AipDeleteTest.php`.

#### Edit and delete on the Planning lists

| Record | Edit | Delete | Locked when |
|---|---|---|---|
| SIP program | pillar, KRA, outcome, strategy, 5-point agenda, program, start year | with its activities; recorded in the audit log | an AIP is linked to it (delete or relink that AIP first) |
| SIP activity | activity, Year 1 to 3 physical and financial targets, source of fund, responsible person, remarks (the program budget follows) | yes | the fiscal year is closed |
| AIP | "Open / Edit" opens the AIP page where it is edited | draft only, with no PPMP or budget built on it | approved or revised (budget allotments follow it) |
| PPMP | draft only: title, mode, schedule, fund source, and the item (name, specifications, quantity, unit, unit cost) | draft only | approved (it feeds the APP) |
| APP item | draft APP only: item, specifications, quantity, unit, unit cost, mode, schedule, fund source | draft APP only; "Generate" brings a removed item back | the APP is approved, or a Purchase Request already draws from the item |

- Editing uses one shared window per kind of record, filled from the row's button (`data-edit-dialog`, `data-action`, `data-payload`). Deleting always asks first (`data-confirm`) and the server refuses anything the table above locks.
- Only people with `planning.manage` see the buttons; a locked row shows why (for example "Approved plans are locked"). Tests: `tests/Feature/PlanningEditDeleteTest.php`, `tests/Feature/AipDeleteTest.php`.

#### Breaking the SIP into three AIPs

- A SIP covers three years. In Planning → SIP, each plan has a **Break into AIP** block with **Year 1 · FY 2026**, **Year 2 · FY 2027** and **Year 3 · FY 2028**, each showing that year's financial target, its AIP (status and an Open / Edit link) or a **Create AIP FY …** button. Only someone who manages both planning and the budget sees the button.
- Creating it builds a **draft** AIP for that fiscal year from that year of the SIP (`SipAipService`):
  - each SIP program becomes a KRA block (pillar, KRA, outcome, strategy, 5-point agenda, program);
  - each SIP activity with a target in that year becomes an AIP activity with its physical target, responsible person and remarks; activities and programs with nothing in that year are left out;
  - the year's financial target becomes the **Q1 to Q4 amounts**. **The split is even (25% each, the leftover cent in Q4) for now; the quarterly schedule (SOB) will replace it once its template is available.** The shares live in one place, `SipAipService::QUARTER_SHARES`;
  - the SIP's source of fund names several funds, so the AIP activity's source of fund and account code are left empty (the SIP text is kept in the activity's remarks). Choose them on the AIP page before approving; the AIP cannot be approved until then.
- It never replaces an AIP: if the school already has an AIP for that fiscal year it asks you to delete that one first. After creation the AIP is independent of the SIP.
- The AIP list shows where it came from ("from SIP 2026-2028 · Year 1"), stored in `aips.sip_start_year` and `aips.sip_year_no`. Tests: `tests/Feature/SipToAipTest.php`.

## 15. Putting the app online with a Cloudflare Tunnel

The app runs on this computer; a Cloudflare Tunnel gives it an https address on a domain that is on Cloudflare. It is online only while this computer, the local server and the tunnel are running. Cloudflare does not run PHP, so for an always-on system the app must move to a server (Cloudflare then provides the domain, DNS and https in front of it).

1. The domain is **Active** in the Cloudflare dashboard (its nameservers point to Cloudflare).
2. Install the tunnel program once: `winget install --id Cloudflare.cloudflared`.
3. Sign in: `cloudflared tunnel login` (a browser opens; choose the domain). Create the tunnel and its address:
   - `cloudflared tunnel create procurems`
   - `cloudflared tunnel route dns procurems procure.<your-domain>`
4. Start the app server (see section 8), then the tunnel: `cloudflared tunnel run --url http://127.0.0.1:8000 procurems`.
5. In `.env` set `APP_URL=https://procure.<your-domain>`, `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`, then run `php artisan config:clear`. **Never leave `APP_DEBUG=true` on a public address**: it shows code and settings on every error.
6. The app trusts the forwarded headers (`bootstrap/app.php`, `trustProxies`), so links are https and the visitor's own address is recorded. Tests: `tests/Feature/BehindCloudflareTest.php`.

Good practice once it is public: protect the address with Cloudflare Access (an email one-time code) if only your own users should reach it; the registration page is public by design, so schools can pre-register.

## 16. Deploying on Railway (always online)

Files: `Dockerfile` (PHP 8.4 with FrankenPHP), `deploy/Caddyfile`, `deploy/php.ini`, `deploy/entrypoint.sh`, `.dockerignore`, `railway.json`. Run from the `vibe-app` folder (the service root directory is `vibe-app`).

- **Database and files:** SQLite on a Railway **volume mounted at `/data`** (`DB_DATABASE=/data/database.sqlite`); uploaded logos live in `/data/public` (linked into `storage/app/public`). Without the volume the data is lost at every deploy; the container prints a warning if `/data` is not mounted. One replica only (SQLite).
- **Start-up** (`entrypoint.sh`): prepare the volume, `migrate --force`, cache config, routes and views, run `schedule:work` (the daily transfer jobs) in the background, then the web server on `$PORT`. Health check: `/up`.
- **Variables to set on the service:** `APP_KEY` (from `php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<the address>`, `DB_CONNECTION=sqlite`, `DB_DATABASE=/data/database.sqlite`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=stderr`.
- **First master user:** `php artisan master:create you@example.com --name="Your Name"` prints a strong password once. Do **not** run `DatabaseSeeder` on a public server: it creates demo accounts with the password `password`.
- **Domain:** add the address under the service's Networking settings; for `celsys.trade` add a CNAME in Cloudflare to the target Railway shows (and remove the tunnel record of the same name first).
- **Updates:** deploy again with `railway up` from `vibe-app`, or connect the GitHub repository with the root directory set to `vibe-app` for automatic deploys.
- Tests: `tests/Feature/CreateMasterUserTest.php`.

### Railway project (set up 2026-10-10)

- Project `procurems` (service `procurems`, environment `production`), one replica, volume `procurems-volume` at `/data`. Address: `https://procurems-production.up.railway.app`. The master account was created with `master:create` (fresh data, no demo accounts).
- **Deploy a new version:** from the `vibe-app` folder run `railway up --detach --service procurems`. In Git Bash set `MSYS_NO_PATHCONV=1` first, otherwise `/data/...` values are rewritten as Windows paths.
- **Logs:** `railway logs` (and `railway logs --build`). **Variables:** `railway variables --kv`.
- **Run a command on the server** (for example `master:create`): `railway ssh` needs an SSH key registered with Railway (`railway ssh keys add`); register a key, run the command, and remove the key again.
- The first deploy ran the migrations by itself (`entrypoint.sh`); every later deploy runs new migrations the same way.

## 17. Shared logos, Google Drive and database backup

Design: `docs/superpowers/specs/2026-10-10-google-drive-and-shared-logos-design.md`.

### Shared logos
- The **department (DepEd) logo is one for the whole system** and the **division logo is one per division**, kept in `shared_logos` on the Railway volume (`public/logos`). The school logo stays one per school. The district has no logo of its own.
- A school's division is its own profile first, then the agency record (`division_name`, then `division_office`), written as `region|division` in lower case with spaces collapsed (`SharedLogo::keyForSchool`). A blank division has no shared logo.
- The first upload of a shared logo sets it for everyone. **Replacing an existing one is for the master user only**; a school admin sees it read-only ("Shared logo. Only the master user can change it.").
- `OfficialDocument::logos()` order: left = shared department logo, the agency's own old logo, the DepEd seal; right = the school's logo, the shared division logo, the agency's old division logo. Tests: `tests/Feature/SharedLogoTest.php`.

- **Place names:** while typing a Region, Division or District (pre-registration, School Settings, add school) the names already in the system are suggested (`App\Support\PlaceNames`, a `<datalist>`; names only, never school or user details). On save a name that differs only in case or spacing takes the system's spelling, and a region written in numbers becomes Roman ("Region 7", "region 07", "7" give "Region VII"; "4a" gives "Region IV-A"). The shared division logo key uses the same rule. Tests: `tests/Feature/PlaceNamesTest.php`.

### Google Drive (one user, one Drive)
- Every user connects **their own** Google Drive on `/google-drive` (OAuth, scope `drive.file` only: the app sees just the folders and files it created). Tokens are stored encrypted in `google_drive_connections`. The app creates `ProcMS` with `Backup`, `Logo` and `Files` and recreates them if they are deleted.
- **Files are private to their owner.** `/drive-files` lists, uploads (up to 20 MB), opens in Drive and deletes the signed-in user's own files; nobody else, not even the master user or a school admin, can see them. A user who wants to show a file e-mails it.
- **Uploads are blocked until the user is connected** (`drive.connected` middleware, redirect with "Connect your Google Drive before uploading files."; JSON gets 409). If Google revokes access the status becomes `needs_reconnect` and a banner on every page asks to reconnect. Logo uploads are never blocked; a connected user also gets a best-effort copy of the logo in `ProcMS/Logo`.
- No Google package is used: `App\Services\GoogleDriveService` calls Google with the Laravel HTTP client. Tests fake Google (`tests/Concerns/FakesGoogleDrive.php`).
- **Railway variables:** `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI=https://procms.celsys.trade/google-drive/callback`. Never put them in the repository or in chat.
- **Google Cloud setup (once):** create a project, enable the Google Drive API, create an OAuth client of type *Web application* with that redirect address, and set the consent screen to **In production** (in Testing mode tokens expire after 7 days and only 100 test users are allowed; `drive.file` needs no Google review).

### Database backup (master user only)
- `/backup`: **Backup now** and a history (date, manual or automatic, file, size, status, who downloaded it and when). A backup is a consistent SQLite copy (`VACUUM INTO`) uploaded to the master user's `ProcMS/Backup` as `procms-YYYY-MM-DD-HHMM.sqlite`; **Download** fetches the stored file from Drive and logs the download.
- `php artisan backup:database` runs **every day at 12:00 midnight Philippine time** (`Asia/Manila`; the app itself runs on UTC) through the scheduler that `deploy/entrypoint.sh` starts. The latest 30 automatic backups are kept in Drive, older ones are deleted there (their history rows stay). A failed run is recorded with its error and the master dashboard shows a warning until a later backup succeeds.
- A backup file larger than 40 MB is recorded as a failed run (the upload is built in memory); resumable upload is the next step if the database ever grows that big. Download uses the Drive of the master who made the backup.
- The file contains every school's data, so it is never given to anyone but the master user. Only SQLite is supported; any other database records a failed run.
- Tests: `tests/Feature/DatabaseBackupTest.php`, `DatabaseBackupCopyTest.php` (the real copy), `DriveFilesTest.php`, `DriveGateTest.php`, `GoogleDriveConnectionTest.php`.

## 18. Sub-master

Design: `docs/superpowers/specs/2026-10-10-sub-master-design.md`.

- A **Sub-master** (`role = sub_master`, no school, `users.access` JSON) works like the master across every school, with the access the master ticks on a checklist. Only the real master (`isMaster()`) creates, edits, resets the password of, deactivates or reactivates Sub-masters, in the **Master User** tab (`/user-management?tab=master-user`). Sub-masters are deactivated, never deleted. The role is not in `User::ROLES`, so a school can never give it.
- **Checklist** (`App\Support\SubMasterAccess`): work areas `planning`, `budget`, `procurement`, `suppliers`, `accounting`, `cash`, `liquidation`, `school_settings` (each grants a permission family such as `accounting.*`; `dashboard.view` and `reports.view` are always on); management areas `schools` (School Management, pre-registration approval, takeover, add school), `subscriptions`, `transfers` (decisions on transfer requests), `users` (the Users tab of User Management), `backup`; switches `delete`, `deactivate_user`, `close_budget`, `edit_own_name`. A new Sub-master gets the 13 areas on and the 4 switches off. A null, empty or unknown checklist means no access.
- **Central questions on `User`:** `isMaster()`, `isSubMaster()`, `seesAllSchools()` (master or Sub-master: replaces every "is master" visibility check and the exemptions from the school, subscription and station-transfer checks), `hasAccess($key)` (true for the master; for a Sub-master the key must be on its checklist), and `hasPermission()` (a Sub-master gets the families of its work areas). `User::MASTER_ROLES` excludes both roles from a school's user counts. Management gates use `hasAccess('schools'|'subscriptions'|'transfers'|'users'|'backup')`; menus follow the same keys.
- **Delete guard** (`EnforceSubMasterLimits`, runs before route-model binding): for a Sub-master it refuses with 403 "Your account's access does not allow this action." every `DELETE` (method spoofing included) without `delete`, `school-management.users.deactivate` without `deactivate_user`, and `budget.allocation.close` without `close_budget`. A user's own Drive actions (`google-drive.disconnect`, `drive-files.destroy`) are never refused. Each refusal is audited as `sub_master_action_refused`.
- **Own account:** a Sub-master uses the "Account details" and "Change password" forms of the tab; full name and username are read-only (and ignored by the server) unless `edit_own_name` is on; role, status and checklist are never read from these forms. The Users tab needs `hasAccess('users')`; the Master User tab is open to every master and Sub-master.
- A deactivated Sub-master cannot sign in and an open session ends on its next request. The nightly automatic backup, `master:create` and `sip:import` stay with the real master; a Sub-master with `backup` saves backups to **their own** Google Drive, and every download is logged.
- **Rules added after the final review:** a Sub-master with **no area on** (only switches, or nothing) sees nothing (`seesAllSchools()` is false) but keeps the Master User tab for their own account (`isAnyMaster()`). Masters and Sub-masters are managed only in the Master User tab: School Settings refuses them as targets (403). `deactivate_user` also covers deactivating a school user in School Settings, ending a handover, deactivating a school (`school-management.status` with active off, or the status in School Details), and `close_budget` also covers closing a fiscal year (`planning.fiscal-year.status` with `closed`). A school's status in School Details needs the `schools` access. The whole audit-log export needs the master or a Sub-master with `users`.
- Audit actions: `sub_master_created`, `sub_master_updated` (old and new checklist), `sub_master_password_reset`, `sub_master_status_changed`, `sub_master_action_refused`.
- Tests: `SubMasterAccessTest`, `SubMasterVisibilityTest`, `SubMasterGatesTest`, `SubMasterDeleteGuardTest`, `SubMasterManagementTest`, `SubMasterAccountTest`.

## 19. User Management - Users tab

- `/user-management` (Users tab) lists every school account (master and Sub-master accounts never appear; they live in the Master User tab). Real metrics (total, active, pending, administrators), search (name, username, e-mail, User ID), filters (role, status, school), 15 per page, "Users by Role" and "Access Activity" from the audit log.
- Access: the master, or a Sub-master with the **Users** access. Setting an account inactive also needs the **Deactivate users** switch (the Users access is enough; School Management access is not required).
- **Add User**: the master creates a school account directly with a temporary password (12+ characters). Only active schools without an active user are offered; role is any school role, never master or Sub-master. It also creates the school staff row and a 30-day trial subscription. The password must be handed over in person. A forced password change on first sign-in is not implemented.
- User page (`/user-management/users/{id}`): edit e-mail, phone, position and role (name and username stay the user's own), reset the password, set inactive (reason, date, note) or activate again, and see the account history. All actions write audit rows (`user_created`, `user_updated`, `user_password_reset`, `user_set_inactive`, `user_reactivated`).
- `/audit-logs` is a paginated audit log with action and school filters.
- Tests: `UserDirectoryTest`.

## 20. Make a request (School Settings, Official Station tab)

- The old "Request a station transfer" form is now **Make a request**. Request type: A registered school, My school isn't listed (both are transfers, unchanged), **Request to Official Station** (the user names no school; the master picks a vacant school when approving, and the user confirms at next sign-in) and **Other** (the user types the request).
- `station_transfer_requests.kind` (`transfer`, `official_station`, `other`) and `subject` (the typed request). Only `transfer` goes through the destination school's acceptance and the handover. `other` has nothing to move: approving it is the master's answer and needs no confirmation.
- The status, the 3-step guide, My requests and the master's Official Station Management queue are worded for any request. Only transfers appear in School Management.
- One pending request per user, whatever its type. Tests: `GenericRequestTest`, `StationTransferTest`.

## 21. Demo copy for client presentations

- The demo is a **separate deployment** with its own database (a Railway `demo` environment), never the live system. `DEMO_MODE=true` turns it on (`config('app.demo')`); it is off everywhere else.
- In demo mode the sign-in page lists only the demo School Admin (a client never sees the master or Sub-master accounts; they still exist for the owner), and the master sees **Reset demo data** in User Management → Master User. It wipes the database and loads `DatabaseSeeder` + `DemoShowcaseSeeder` again, then signs everybody out. The same reset is `php artisan demo:reset --force`; both refuse to run when `DEMO_MODE` is off.
- The demo also resets itself **every day at 12:00 midnight Philippine time** (scheduled `demo:reset --force`, only when `DEMO_MODE` is on), so nothing a client enters is kept.
- A new empty demo database loads the demo data by itself on first start (`deploy/entrypoint.sh`, `demo:reset --if-empty`).
- Demo accounts (all `password`): master `admin@procurems.test`, Sub-master `submaster@procurems.test`, school admin `demo@gmail.com` (Test School, with the full planning, procurement, budget and liquidation sample), plus an admin for each of the other seeded schools. The showcase adds two vacant schools, a new registrant waiting for an Official Station, and one open request of each kind (transfer, Official Station, Other).
- `DatabaseSeeder` no longer mutes model events, which had broken the AIP approval in `PlanningDemoSeeder` and skipped user codes. Tests: `DemoModeTest`.

## 22. SIP official print and the SIP panel (approved by the owner 2026-10-14)

The SIP print (`/planning/sip/print`, `resources/views/sip-print.blade.php`) and the SIP panel of Planning are approved as they are. Do not change them without the owner's permission.

### 22.1 Printed SIP

- Paper: long bond, landscape, 10 mm margins, through the shared print engine (1.5, 1.6). Fonts: Arial.
- Size: every size is in `rem`, and 1rem is 1/117.1 of the page width (the same as the AIP), so the table lays out the same on any paper, on screen and in print.
- Header: one logo in the middle, Republic of the Philippines, Department of Education (1.4rem), Region, Division (black); title block "SCHOOL IMPROVEMENT PLAN" (1.4rem), "FY 2026-2028" and the school name (small and balanced, not large).
- Table text and pillar letters share one size that is chosen by the page: the largest from 10pt down to 8pt (on the long bond paper) that does not add a page compared with 8pt. The table headings keep their own smaller size.
- The Pillar column heading is smaller (.72rem) so that "Mechanism" is not broken; the KRA column is 7% and DepEd Organizational Outcomes 8.5%. The other columns, heading colors (peach, yellow Physical, green Financial, blue pillar) and the official SIP columns are unchanged.
- The Pillar column heading reads "Pillar (Access, Equity, Quality, Resiliency and Well-Being) / Enabling Mechanism", with no underline.
- Pillar: written downward, one letter on each line. A name of two or more words goes in two columns of letters, split where they are the most even (Resiliency and Well-Being: RESILIENCY | AND WELL-BEING; Enabling Mechanism: ENABLING | MECHANISM).
- Merged cells: the pillar, the KRA to Program cells and the Source of Fund merge across the rows that share them.
- Automatic text flow with border-preserving pagination: the table has `data-paginate data-split-rows`. A row that does not fit the room left on a page is split in two rows; the first keeps the words of each cell that fit and the second carries the rest to the next page, and both keep every border. Merged cells cut by the break keep their text on each page, the heading rows repeat on every page and a page never holds more than the paper can print. The engine is `partials/print-pages.blade.php` (also used by the AIP).
- Footer on every page: "Page X of Y" in the middle and "School Improvement Plan" on the right (`data-page-footer`), in a small size; two lines at the bottom of each page are kept for it.
- Signatures: three, side by side, after the last rows (Prepared by, Recommending Approval, Approved by; names and positions from the SIP plan).

### 22.2 SIP panel in Planning

- "Break into AIP" is shown at the top of the SIP panel, under the "School Improvement Plan (SIP)" header, as small chips: Year 1 to Year 3 of each SIP year. A year that already has its AIP is a **blue** chip that opens it (with its total and status); a year without one is a white "Create AIP FY ..." chip (people allowed to manage the budget) or a gray "No AIP yet" chip.
- Order of each SIP year: the year header and Print SIP, then the pillars (Access first) with their programs, then the signatories.
- The signatories are shown horizontally (three columns side by side) with the Edit signatories box under them (`horizontal` option of `partials/signatory-editor`).

Tests: `SipTemplateTest`, `OfficialPrintTest`, `PlanningFlowTest`.

## 23. AIP official print and the AIP pages (approved by the owner 2026-10-14)

The AIP print (`/aip/{aip}/print`, `resources/views/aip-print.blade.php`), the AIP page (`/aip/{aip}`, `resources/views/aip-show.blade.php`) and the AIP list in Planning are approved as they are. Do not change them without the owner's permission.

### 23.1 Printed AIP

- Paper: long bond, landscape, 10 mm margins, through the shared print engine (1.5, 1.6). Fonts: Arial. Same size rule as the SIP (22.1): every size in `rem`, 1rem = 1/117.1 of the page width.
- Header: the department logo on the left and the school logo on the right, Republic of the Philippines, Department of Education (1.4rem), Region, Division; title block "ANNUAL IMPLEMENTATION PLAN" (1.4rem), "FISCAL YEAR ..." and the school name, all small and balanced (not large).
- The Pillar column heading reads "Pillar (Access, Equity, Quality, Resiliency and Well-Being) / Enabling Mechanism" (the same as the SIP, 22.1).
- Pillar: written downward, one letter on each line; a name of two or more words in two columns of letters, split where they are the most even (the same rule as the SIP). The pillar cell keeps the color of its pillar.
- Merged cells: identical data merges down the rows. Each column from the pillar to the Specific Program / Project merges a run of rows whose value (and every value to its left) is the same; Source of Fund merges the same fund within one KRA. Activity, targets, timeline, quarters, Responsible Person and Remarks are never merged.
- Specific Timeline: follows the quarters that have an amount (1st and 2nd = January - June, up to the 3rd = January - September, 1st to 4th = January - December, only the 1st = January - March); the saved text is printed only when no quarter has an amount.
- Automatic text flow with border-preserving pagination: the table has `data-paginate data-split-rows` (the same engine and rules as the SIP, 22.1).
- Footer on every page: "Page X of Y" in the middle and "Annual Implementation Plan" on the right (`data-page-footer`), in a small size (shared with the SIP and the SOB, whose title on the right is its document title).
- Signatures: three, side by side, after the last rows (Prepared by, Noted by, Approved by).

### 23.2 AIP page

- Same Key Result Area is one box: inside a pillar, KRAs with the same title are merged into one disclosure ("N programs · M activities"); each of its programs keeps its own details (Intermediate Outcome, Strategy, 5-Point Agenda, Specific Program / Project), Edit KRA &amp; Activities, Delete and activities, separated by a thick line.
- Each activity is highlighted: a blue bar on its left, a light blue heading with a blue "ACTIVITY" label and a small shadow.
- Financial target per quarter: the four amount boxes and the Total box are aligned on the same line.
- Frequency (timeline): follows the quarters that have an amount whenever an amount changes (same rule as the print). A frequency typed by hand is kept when the quarters are not changed.
- Order of the page: pillars and KRAs, Financial Target per Source of Fund, Allotments created from this AIP (when there are any), then Signatories at the very bottom, shown horizontally (three columns, `horizontal` option of `partials/signatory-editor`).
- Access (owner's decision 2026-10-17): the AIP page, its print and its live previews need the `aip.view` permission; a role without it (for example `office_user`, `encoder`, `bac_user`, `procurement_officer`) is refused with 403. Changing, approving and deleting an AIP stay with the Budget Officer or an administrator (`budget.manage`); each school has one user, so the School Head role is not used for this. Tests: `RoutePermissionGapsTest`.

### 23.3 AIP list in Planning

- Open / Edit, Print and Delete are icon buttons (pencil, printer, trash, with a tooltip and a label for screen readers). Delete is red; when an AIP cannot be deleted it is gray and not clickable, with the reason in its tooltip. The confirmation before deleting and the rule for when it is allowed are unchanged.

Tests: `AipPrintFundTest`, `AipActivityBudgetTest`, `PlanningFlowTest`, `OfficialPrintTest`.

## 24. SOB print, SOB page, APP / APP-CSE from the SOB and the Planning steps (approved by the owner 2026-10-14)

The SOB print (`/planning/sob/{id}/print`, `resources/views/sob-print.blade.php`), the SOB page (`/planning/sob/{id}`, `resources/views/sob-show.blade.php`), the SOB list in Planning and the Planning step bar are approved as they are. Do not change them without the owner's permission.

### 24.1 Printed SOB

- Paper: legal, portrait, 10 mm margins, through the shared print engine (1.5, 1.6); long item tables flow page by page with the shared pagination (`data-paginate`).
- School ID: centered, above the bar line under the letterhead (`.school-id`, bar kept).
- Footer on every page: "Page X of Y" in the middle and the document title (for example "SOB 4th Quarter 2026") on the right (`data-page-footer`), in a small size (8px, `--doc-footer-size`); two lines at the bottom of each page are kept for it. The old title in the left page margin is gone.

### 24.2 SOB page

- First box (the document strip): "Makes the official form", "Based on", "Status", "Next". For an approved SOB (people allowed to manage planning) the **Reopen as a draft** button is in this box, under the form name; the yellow "This SOB is approved" notice keeps only its explanation.
- **Add item** is a chip on the right (blue, rounded, "+ Add item"); it opens and closes the item form under it. The form is open by itself when the SOB has no item yet or the form has an error.
- **Details and signatories** (Source of fund, Quarter, School ID and the three signatures) is at the bottom of the page, above the Danger zone. Its Save details posts to `planning.sob.update`.
- The order of the page: first box, notices, Add item chip (and its form), the pillars with their items, Grand total and Summary per object of expenditure, Details and signatories, Danger zone.
- The yellow notices ("1 program goes over the AIP amount…" and "This SOB is approved") are small (text-xs, little padding) and each has an **X** that removes it until the page is opened again.
- Each item (one account of one program) is a box: a bar with the account, its total and two icon buttons (Edit, pencil; Remove item, trash), and under it the table **Category · Particulars · Frequency · Quantity · Unit · Unit cost · Amount** (the Category of each particulars comes first; an "other line" has none).
- **Edit** works in the same place: the table is replaced by the item's fields (Cancel brings the table back, Save item saves). There is no disclosure under the table. The fields: AIP activity (PPA) and Object of expenditure side by side; each particulars row as Category (where it belongs in the APP), Particulars, Frequency, Quantity, Unit, Unit cost in that order, flowing to the next line by the width of the form (so they fit when the official form is open at the right); Add particulars; the Note; the other lines and Add other line. Add item has the same fields.
- The chosen activity and account are not written again under their boxes; their full text is the tooltip of the box.

### 24.3 SOB list in Planning

- Edit, Delete and Open are icon buttons (pencil, trash, open in new, each with a tooltip and a label for screen readers). Delete is red and asks before deleting: "Delete this draft SOB and its items?" or, for an approved SOB, "Delete this approved SOB, its items and release its Budget allotments?"; the same route and rules as the Danger zone of the SOB page. Open has a blue edge.
- The Edit SOB window has two columns: the title and a short line across the top, Quarter and Source of fund side by side, Cancel and Save changes at the bottom right.

### 24.4 Planning step bar

- Steps 1 to 4 (SIP, AIP, SOB, APP) and the separate plan (APP-CSE) show the page that is open in **solid navy** (white text) so it cannot be mistaken for the other steps; the other steps keep their state colors (green done, amber current, gray to do). Clicking the open step again goes back to the overview. Opening the APP-CSE also marks the APP-CSE button in navy.

### 24.5 From the SOB to the APP and the APP-CSE

- **Approving an SOB puts every item of it into the school's APP of the fiscal year by itself**, under the item's Category, with its Particulars, quantity (Frequency × Quantity), Unit, Unit cost, total, source of fund and, as specifications, the AIP activity (the APP is made when there is none). An item added to an approved SOB later goes in too; an item the user took out of the APP is not brought back by an edit (it is offered again under "Choose what goes into the APP", which is shown only while some item is not in the APP). An approved APP that receives a new item goes back to a draft. Items already in an approved APP, or drawn on by a Purchase Request, still protect the SOB item as before.
- **Categories of a particulars (2026-10-16):** the Category choice of every SOB particulars row is, in this order, **-** (blank), Common Office Supplies, Drugs and Medicines, Construction Components and Supplies, Paper Materials and Products, Common ICT Equipment, Consumables and Other Categories (new rows start at Common Office Supplies). A particulars left at **"-"** is not goods (for example a Travel Budget): it is **not put in the APP** or in the APP-CSE, and it is not offered under "Choose what goes into the APP"; it still counts in the SOB, its amount and the Budget. In an approved SOB, changing a particulars to "-" takes its APP line out (refused, as any change, when a Purchase Request or an approved APP holds it) and changing it back to a category puts it in the APP. The old names of the categories were renamed in the data (SOB, APP and the APP-CSE lines written from the SOB), and an old particulars with no category became Other Categories so it stays in the APP. Tests: `AppCategoriesPrintTest`, `AppCseFromSobTest`.
- **The APP-CSE (Procurement Service form) takes the items of every category** of the approved SOBs as Part II lines under their own category (Common Office Supplies, Drugs and Medicines, Construction Components and Supplies, Paper Materials and Products, Common ICT Equipment, Other Categories): the quantity in the first month of the SOB's quarter, one line per category, particulars, unit and unit cost. The lines are changed in the SOB, not in the APP-CSE. An approved APP-CSE is left as it is while its SOB lines are the same; when a line comes or changes it goes back to a draft and follows the SOB (approve it again).
- **Order of the APP panel** (Planning → APP), from the top: the first box (document strip), the **APP-CSE** (Procurement Service form) card, the **APP** with its Procurement Item list, and the **APP signatories** card at the very bottom (the Print APP button stays in the APP). The APP-CSE card says that its Part II lines come from the approved SOB.
- **APP signatories:** "Suggested, not saved yet" names are not official: only saved names are printed on the APP.
- **APP-CSE page** (`/planning/app-cse/{id}`): the first card is **Details** (office, region, address, contact, date, approved budget); the **Signatories** (Prepared by, Noted by, Certified Funds Available, Approved by) are at the very bottom of the page, after Approve, side by side in four columns, in a form of their own with Save signatories (only the signatory fields are sent, so the Details are not touched).

Tests: `SobFlowTest`, `SobRevisionTest`, `SobPrintDesignTest`, `SobAppTest`, `SobApprovedEditTest`, `AppCseFromSobTest`, `PlanningFlowTest`.

## 25. Planning page refinements (approved by the owner 2026-10-15)

These refine sections 22 to 24. Where a line below differs from an older line of 24.2 to 24.5 (the SOB list icons, the step bar, the order of the APP and APP-CSE pages), this section is the later decision.

### 25.1 Jump buttons

- Every planning page shows **Back to Planning** with four small jump buttons, **SIP, AIP, SOB, APP**, at the top (`resources/views/partials/planning-jump.blade.php`): the AIP page, the SOB page, the APP-CSE page and the SIP Excel import preview. Each opens Planning on that tab for the same school and fiscal year. Print pages (the paper forms) do not have them.

### 25.2 AIP page

- The KRA button is **Edit KRA**. Each activity has its own **Edit** (opens the KRA edit page scrolled to that activity) and a **Delete** (trash, asks first). Deleting an activity follows the rules of deleting a KRA: refused in a closed fiscal year and while an SOB uses the activity; on an approved AIP it marks the AIP as revised so it needs approval again.

### 25.3 SOB list and SOB page

- A row of the SOB history in Planning has only **Open**, and **Delete** (red trash) for a draft SOB that is not approved. There is no Edit icon there (the quarter and fund are chosen in Create SOB); this replaces the Edit and Delete icons of 24.3.
- The pillar bar of the SOB page says **Pillar** (not "Pillar / Enabling Mechanism", because Enabling Mechanism is one of the pillars) and shows no program name.

### 25.4 Document strip (all planning documents)

- **Review before generating** and **History** are two cards side by side in the strip (icon, title, a line such as "4 of 5 ready" or "7 recorded events", open icon). Each opens a card window (title, close button, scrolling list); clicking outside or Esc closes it.

### 25.5 APP panel

- **Unlock APP** (approved) or **Delete APP** (draft) is a red rounded chip in the same row as **Print APP**, at the right, with its explanation beside it, asking before it acts. The Danger zone box of the APP panel is gone. The APP signatories are shown horizontally (three columns).

### 25.6 APP-CSE in the Planning flow

- The APP-CSE is **Step 5** of the step bar ("APP-CSE · FY", items, Not started / Draft / Approved); the separate-plan button is gone. It waits for the SOB. Plan progress counts five plans.
- It has its own panel (`#app-cse`), not inside the APP panel. The panel lists the plans as a table: Plan, Catalog, Status, Items, Grand total, Created, with Print and Open icons. The create button is **Generate APP-CSE**. A show / hide arrow is at the left of the panel title.

### 25.7 APP-CSE page

- **Details**, **Part I** and **Part II** are folded sections with the arrow at the left; open by default.
- Items are grouped by category. Each category has a solid blue bar with its name (a long name wraps), and under it the heading **Item, Unit Price, Q1 to Q4 over Jan to Dec, Qty, Amount**. Each item is one row in the same columns (months as small cells, an empty month is a faint dash). The row scrolls sideways on a narrow screen.
- **Totals** are two boxes: Summary of amounts (A, B, C, D highlighted, E) and Monthly cash requirements (F: G.1, G.2, total by Q1 to Q4 and Year).
- **SOB signatories** (history of SOB created, view only) is under the Totals: for each SOB of the school and year, its Prepared by, Recommending approval and Approved by, with **Edit in the SOB**. They are changed in the SOB, not here.
- **Approve APP-CSE** is at the top right, next to Print official form (greyed until an item has a quantity; the confirmation says that approving locks it). An approved APP-CSE has **Reopen as a draft** in the first box (people allowed to manage planning, open fiscal year); it is recorded in the audit log and the history.
- The boxes **Add from the Procurement Service catalog (Part I)** and **Add my own item (Part II)** are removed from the page: the lines come from the approved SOB. Part I is filled by search: see section 43. SOB lines still go to Part II.)

### 25.8 Planning Settings

- Two cards: **Fund sources** (the funds offered as Source of fund in the AIP and the SOB, listed with code, name and Active / Inactive) and **Fiscal year** (Open / Closed with the lock explained). The Settings button stays in the same row as View.

Tests: `AppCseEntryTest`, `AppCseFromSobTest`, `SobRevisionTest`, `PlanningFlowTest`, `DocumentStripTest`.

## 26. SOB labor print, Allotment Registry, Budget Allocation page and AIP amounts (approved by the owner 2026-10-15)

These refine sections 24 and 25 and the Finance pages. Where a line below differs from an older line, this section is the later decision.

### 26.1 Printed SOB: other lines (labor) and Sub-Totals

- An Item (one account of one program) that has other lines prints, in this order: its particulars, a **Sub-Total** of the particulars, each other line, then a second **Sub-Total** (particulars plus other lines, in bold, across the full width). An Item without other lines has no Sub-Total of its own.
- An other line (for example "35 % Labor") is written in the **left column**, under the program and the note ("POW required"), and the word **Labor** in italics is in the Particulars column; its amount is in the Amount column. The left cell of the Item covers its particulars and the first Sub-Total only.
- The pillar Sub-Total is left out when the pillar has one Item only and that Item has other lines (it would repeat the Item's last Sub-Total). A pillar with two or more Items keeps its pillar Sub-Total.
- On the SOB page the Item's table shows the same Sub-Total, other lines and Sub-Total.

### 26.2 Allotment Registry (`/allotment-registry`)

- The page is the summary of the budget, not the per-line ledger: summary cards (Total allotment, Obligated, Disbursed, Not yet obligated, and a "Budget used" bar), **Summary per Source of Fund**, **Items funded from the SOB** and **Manual allotments**. The per-line obligation and disbursement boxes are not shown; Export to Excel still has every entry.
- Only funded lines are listed: a line with no allotment in the chosen period (and nothing obligated on it) is left out. The Budget lines written by an approved SOB (their remarks say "From SOB FY ... Q...") are the **Items funded from the SOB**; every other line is a **Manual allotment** (funding with no project: a donation, a transfer, a grant). **Add manual allotment** (people allowed to manage the budget) opens the Budget allocation form; each manual line has an Open icon. The cards and the summary count both.
- **Allotment** is one plain heading (same size and style as the other headings, no box or highlight) over Q1 to Q4; the four quarters have the same width. All number columns are centered. The account (UACS) code is shown larger, on its own line above the account title, and the title wraps.
- The first button next to Export to Excel is **SOB** (it opens the SOB tab of Planning).

### 26.3 Budget Allocation page (`/budget/allocation`)

- It is **live**: the summary cards, the Budget Ledger and the Quarterly breakdown are read again every 30 seconds without reloading (and on Refresh, and when the tab comes back), with a "Live · Updated hh:mm:ss" note. Folded sections stay as they are.
- **Obligated** (by quarter and in all), Remaining, Unliquidated, the status and the warnings come from the Registry of Allotments, Obligations and Disbursements (the ORS; `App\Services\AllotmentRegistryService`, `BudgetService::matrixFromRegistry`), so this page and the Allotment Registry always agree. Liquidated is unchanged. The Budget page (`/budget`) is not changed by this.
- The **Office / Department** column (and the office filter and the office in the Quarterly breakdown) is shown only to a user who sees more than one school; a user of one school does not see it. The school name is not repeated under the office when it is the same.

### 26.4 AIP page: the amounts save by themselves

- The **Save amounts** button is gone. The quarter amounts and the Frequency (timeline) of an activity save in the background when a box is left or Enter is pressed (one save at a time), showing "Saving…" then "Saved", like the Source of fund. The page does not reload unless the AIP's status changes (an approved AIP becoming revised). The Total and the timeline still follow the amounts as they are typed. The totals lower on the page are refreshed when the page is opened again.

Tests: `SobItemGroupTest`, `SobFlowTest`, `AipActivityBudgetTest`, `PlanningFlowTest`.

### 26.5 Printed SOB follows the owner's workbook (approved by the owner 2026-10-16; the file `SOB 4th 2026.xlsx` is the source of truth)

Where a line below differs from 24.1 or 26.1, this one is the later decision.

- Pillar names print as in the workbook: **RESILIENCY & WELL-BEING** (the stored name stays "Resiliency and Well-Being"). A pillar with no entries prints its bar only (no PPA row, no Sub-Total).
- The program (PPA) is a **bold row of its own** across the table, printed once; under it each Item shows the account title (or its note) in the left column.
- The account title of an Item is indented under the program row; when the Item has a note (for example POW required), the title comes first and the note is under it after one blank line (the note no longer replaces the title).
- Other lines (labor): the name (for example 35% Labor) in the left column and the word **Labor** in **bold**, right-aligned, in the Unit of Measure column; the amount in the Amount column.
- **GRAND TOTAL** of the table is bold, right-aligned, on the same light green (#92D050) as the GRAND TOTAL of the summary table.
- The District line of the letterhead is in normal weight and in the case it was typed. The signature names are bold, capitals and **underlined**. The School ID line is bold, in the same size as the school name (12px).
- The Summary per object of expenditure stays **per actual account title** (the Budget uses the same accounts); the workbook's own summary labels are not used.
- Not done: a program and its only item on one row (the workbook does it for some programs and not for others, so it cannot be derived).
- The School ID line of the printed SOB is, in this order: the number typed on the SOB (Details and signatories), the **DepEd School ID** of the school profile, then the system School ID (`code`).
- Tests: `SobPrintDesignTest`, `SobItemGroupTest`.

## 27. Allotment Registry fund details and system-wide contrast (approved by the owner 2026-10-15)

This refines 26.2 and the screen design standards (7).

### 27.1 Allotment Registry: the items are the details of the fund

- The separate box "Items funded from the SOB" is gone. Each source of fund in the **Summary per Source of Fund** is clickable (the whole row, also with Enter or Space; an arrow at its name turns, the row is lightly highlighted): it opens a detail row right under it, "Items funded from the SOB · fund · N items", with Program / Project, Object of Expenditure (the account code larger on its own line, the title wrapping), Allotment Q1 to Q4, Total Allotment, Obligated and Not Yet Obligated, and a TOTAL row of that fund. Clicking the fund again closes it; several funds can be open at once; printing opens all of them. The details are closed when the page opens.
- The **Manual allotments** section stays apart, as in 26.2.

### 27.2 Contrast of the screens (grouping of data must be easy to see)

- The shared colors of every application page (the Tailwind color settings of the layouts and of the standalone Finance, Budget, Liquidation, Reports, Subscriptions, User Management, Backup and Google Drive pages) are: surface `#e6edf4` (page background), surface-low `#dbe5ee` (table heading and summary bands), surface-container `#cfdbe6`, surface-high `#c0cfdd`, on-surface-variant `#374a5d` (labels and subtitles), outline-variant `#7f95a9` (borders and dividers). The sign-in and registration pages and the printed official forms keep their own colors.
- A new page uses these tokens (`surface-low` for bands, `outline-variant` for borders) and must not use lighter colors of its own; a group of data (a card, a pillar, a fund, a table) is always set apart by a visible border or band.

Tests: `SobFlowTest`; the whole suite is run after a color change.

## 28. Dashboard planning panels, step colors and AIP wording (approved by the owner 2026-10-15)

### 28.1 KPI dashboard (school dashboard and the administrator's KPI view)

- Under the KPI cards, two panels are added (`resources/views/partials/dashboard-kpi.blade.php`, data from `DashboardKpiService::planning`):
  - **Planning flow**: SIP, AIP, SOB, APP and APP-CSE of the fiscal year, each with its count (programs, plans, quarters), a numbered circle and a status chip (green Approved or Entered, amber "x of y approved", rose Not started); each row opens that step in Planning.
  - **Budget funded from the SOB**: the Budget lines written by an approved SOB (remarks "From SOB ..."), by source of fund, with Q1 to Q4, total and obligated, a TOTAL row and a link to the Allotment Registry. The approved SOB is the actual budget; without one the panel says to approve a quarter.
- The look is modern: a gradient banner with the school name, larger KPI numbers with larger icon tiles, rounder cards with a soft shadow, thicker chart bars, titles in the brand blue. The administrator's "system" dashboard and the server dashboard are separate layouts and keep their own look.

### 28.2 Planning step bar

- A step that is **Not started** is rose (chip, number circle and card edge), not gray, so it can be seen; on the open (navy) step the chip stays readable.

### 28.3 AIP wording

- The "Based on" box of the AIP page shows **SIP 2026-2028 · Year 1** (a link to the SIP tab) for an AIP made from the SIP; "SIP program: ..." for an older AIP tied to one program; and "Entered directly: this AIP was not made from the SIP." for an AIP entered by hand. It no longer says "Not linked to a SIP program".
- The AIP list in Planning has the column **Programs · Activities**: the number of different Specific Programs / Projects of the AIP, and under it the number of activities (for example 25 programs, 89 activities).

Tests: `DashboardKpiTest`, `PlanningFlowTest`, `DocumentStripTest`.

## 29. AIP list, docked preview and Print SIP (approved by the owner 2026-10-15)

This refines 28.3 and the docked preview of the planning pages.

### 29.1 AIP list in Planning

- The columns are **Fiscal Year, Programs · Activities, Total Financial Target, School Improvement Plan, Status** (the heading is spelled out, not "SIP"); the headings and the content of all of them are centered (the icon buttons stay at the right).
- **Programs · Activities** shows two lines, for example **25** Programs and **89** Activities: the numbers in bold, the words capitalized (singular for one: Program, Activity).

### 29.2 Docked preview of the official form (AIP page, Edit KRA page, SOB page)

- When the preview of the official form is open beside a page (screen 1100 px or wider), the **page is scaled to fit the room that is left** (the whole page: text, boxes and buttons together), never below **60%** (the smallest text, 10px, is then 6px). The preview and its Show button stay at their normal size (they are kept outside the page content). Hiding the preview puts the page back to its normal size; the scale follows when the window is resized. On a narrower screen the preview covers the page and nothing is scaled.
- The **Edit KRA and Activities** page (`/aip/{id}/kras/{id}/edit`) keeps the preview beside the form like the AIP page, with the same open or closed state (it shows the saved form; **Refresh** or Save updates it).

### 29.3 Print SIP

- **Print SIP** is the highlighted button (navy, bold, printer icon, shadow and ring) at the top right of the School Improvement Plan panel, with Import from Excel and Add program; everyone who sees the SIP panel sees it, and it prints the latest SIP. The year bar of each SIP period keeps a small printer icon for that period.

Tests: `PlanningFlowTest`, `SipTemplateTest`, `AipPrintFundTest`.

## 30. Planning panels and the Obligation Request and Status (approved by the owner 2026-10-15)

This refines 24.2, 25.5, 25.6, 28 and 29, and records the ORS (Create ORS window on the Budget page and the printed ORS, `resources/views/ors-print.blade.php`).

### 30.1 Planning panels

- **SOB panel:** under the title, one line per quarter (1st to 4th Quarter): the quarter name at the left, the amount and a status chip at the right (rose Not created, amber Draft, green Approved); a quarter with one SOB opens it when clicked, and the funds are in the tooltip. **Create SOB** (bold) is beside them. On the SOB page an item that has other lines (for example 35 % Labor) shows a blue chip with that line and its amount next to the yellow note chip (POW).
- **APP panel:** **Print APP** (highlighted like Print SIP) and **Unlock APP** (approved) or **Delete APP** (draft) are at the right of the panel header, with the explanation of Unlock or Delete under them; the separate row of buttons is gone.
- **APP-CSE panel:** chips show the number of plans and how many are approved; **Generate APP-CSE** has a plus icon; the table (Plan, Catalog, Status, Items, Grand total, Created) is centered except the plan name, the Items and Grand total are bold, and **Print** is a labelled navy button next to the Open icon. The list is always open.
- **Reopen as a draft** (the strip of a planning document) and **Unlock APP** are **yellow** (solid, bold, lock-open icon) so they are easy to find; Delete APP stays red.

### 30.2 Create ORS window (Budget page)

- The preview of the official ORS is the template from the start: what is still missing never stops it ("Preview not ready yet" is not shown); it is drawn with what has been typed so far, blank where nothing was. Nothing is saved by a preview.
- **Date** comes first, with the **Linked Procurement Request** beside it (Serial No. and, for the administrator, School have their own rows). The date is the date of the ORS (saved as the date of the obligation, so the Allotment Registry counts it in that quarter) and prints in the Date line.
- **Payee / Supplier** is connected to **Supplier Management**: it suggests the active suppliers of the school's schools, and choosing one fills in its address and TIN; a payee that is not listed can be typed with its details (Payee Office, Payee Address, Payee TIN), or added in Supplier Management (link under the field). A linked PR with an awarded supplier still fills the payee from it.
- The tick box "Include Section C" is gone: **Section C, Status of Obligation, is always part of the ORS.**
- **Show the UACS code on the ORS** is a tick box beside Charge to Budget Line (Fund · Account Code), ticked by default; unticked, the UACS Object Code column is left empty.

- **Layout (2026-10-16):** the form has three framed parts with a heading each: **Document** (Serial No. with "Manually encode", School for the administrator, Date, Purchase Order), **Payee** (Payee / Supplier, Office, TIN, Address) and **Obligation** (Purpose / Particulars, Amount, Fund Cluster, Responsibility Center Code, Charge to Budget Line with the UACS tick box); then Signatories, Notes and the buttons.
- **No Source of Fund field (2026-10-16):** it is not typed any more. It follows the budget line chosen (or the PO) and is MOOE when neither gives one; it still drives the budget check.
- **Fund Cluster** is a field of the form, 101101 by default and free to change (see 32.2).

### 30.4 An ORS that Accounting returned (2026-10-16)

- In the list of ORS on the Budget page, an ORS that is **Returned** or **Pending Documents** (no DV, not paid) has an **Edit ORS** icon button beside Print, for whoever can create ORS. The Accounting remarks are shown under its Stage and in the window.
- The window changes Payee, Office, Address, TIN, Purpose / Particulars, Amount, Fund Cluster and Notes (not the School, PO, budget line, serial number or date). The amount of an ORS made from a PO is the PO's and cannot change; the amount of a direct ORS is checked again against its budget line without counting the ORS twice.
- The window is the Create ORS window with the ORS in it: the same header, the form on the left in the parts **Document** (serial number, date and Purchase Order, shown and not changeable), **Payee** and **Obligation** (the Responsibility Center Code and the budget line are shown and not changeable), and the **official ORS on the right**, drawn from the form as it is now (nothing is saved by it; a value that is not valid yet is left out of the drawing).
- Saving sends the ORS back to **For Review Accounting**; the remarks stay as history and the old values go to the audit log. Test: `ReturnedOrsEditTest`.
- The Stage of an ORS waiting in Accounting reads "For Review Accounting".

### 30.3 Printed ORS

- The **Serial No., the Date and the Fund Cluster** values are bold and underlined.
- The **Payee, Office and Address** lines all describe the payee: Office is the Payee Office and Address is the Payee Address (the school's name and address are not used there).
- **Responsibility Center, Particulars, MFO/PAP, UACS Object Code and Amount** start two lines below the heading line (the Particulars a little away from the left line) and the Amount is on the same line as the first line of the Particulars. **MFO/PAP is left blank.**
- Section C: the column **ORS/JEV/Check/ADA/TRA No.** shows the ORS number without the letters (2026-0001), and **Payable (b)** is the same amount as the **Obligation (a)**. Once a check is recorded in Cash, the same column prints **Check No.** and, on the line under it, the check number (`payment_reference`), one blank line below the ORS number; before payment, or after the check is cancelled, there is no check line.

Tests: `OrsStatusPrintTest`, `SobFlowTest`, `PlanningFlowTest`, `AppCseEntryTest`.

## 31. Purchase Request from the SOB, obligation by the Purchase Order and the procurement documents (approved by the owner 2026-10-15)

This refines 2 (procurement workflow), 24.5 and 30.

### 31.1 Purchase Request form

- A user of one school does not choose a school: the school is shown as a line (the choice stays only for a user who sees several schools).
- The **SOB activity is the basis of the request.** The form has **Activity (from the SOB)** (required): it lists the activities of the school's approved SOBs that have an APP item with quantity left. Choosing one adds the items of that activity to the Items by themselves (name, specification, unit, unit cost and the quantity still left to request), sets the Source of Fund, and links every line to its APP item (the field "Approved APP item" is kept hidden; the checks of quantity and cost left in the APP are unchanged). Items can still be added by hand (without an APP link). The activity is saved on the request (`aip_activity_id`); the server refuses a request without one (or with one that has nothing left) when the school has such activities.
- **Charge to Budget Line is not asked:** the Budget line is the one the approved SOB wrote for the items; with items of several lines the line with the most cost is used and only its share of the cost is checked against it. A request with no SOB-linked item is refused when the school has a Budget for the year ("Add the items from the SOB activity ...").

### 31.2 Obligation by the Purchase Order

- It is the **Purchase Order that obligates the budget**, not the PR. The PR page shows the state: before the PO, a note that the budget is obligated by the PO; with a PO and no ORS, **Not obligated yet** with **Create ORS**; with an ORS, **Obligated** with its number, amount and status and **View ORS**.
- The Create ORS window lists the **Purchase Orders** (requests that have a PO and no ORS yet), each as "PO number · supplier · amount · PR number". Choosing one fills in the payee, address and TIN, the amount of the PO (its winning bid, `ProcurementRequest::obligationAmount`), the fund and the Budget line. The **Purpose / Particulars** is the Purpose of the PR (the transaction description for the project title, as printed on the PR) followed by "as per PO No." and the PO number without its letters, for example "Procurement of Office Supply for 3rd Quarter MOOE for SY 2026 as per PO No. 2026-001". An ORS without a PO (utilities, travel) is still written by hand.

### 31.3 Procurement documents

- **Abstract of Bids or Quotation** window: the supplier price comparison comes first, then the Signatories, then Save; the other documents also end with Signatories, Notes and Save. The price of each bidder is a small right-aligned box ("Price/unit"). Each bidder also has a **Status** (Qualified by default, Disqualified, No Response; `bidders.*.status`): the printed **TOTAL BID QUOTATION** cell shows DISQUALIFIED or NO RESPONSE instead of the amount, and such a bidder never becomes the Lowest Bid Winner or the awarded supplier. Test: `AbstractPreviewTest`.
- **Notice to Proceed:** the NTP Template and the Calendar days to deliver are side by side. Its preview is drawn with a blank line for the days until they are typed (saving still needs them).
- **Request for Quotation:** the line "you may contact the BAC Secretariat at ... or send email to ..." shows the **contact number and e-mail of the employee who holds the BAC Secretariat role** (School Settings -> Employees & roles has **Contact number** and **E-mail** for every employee), in bold and underlined; the school's contact is used when the employee has none. The Approved Budget of the Contract is split in two boxes, the amount in words and the amount in figures, level with In Words and In Figures.

- **Request for Quotation, Financial Offer:** under Terms of Payment the form prints the sentence "The **LUBAS ELEMENTARY SCHOOL** shall pay the supplier the actual amount of net of taxes in a send bill arrangement. Payment shall be made within **<terms of payment>** and after the submission of billing statement and completion of the deliverables and issuance of Certificate of Inspection and Acceptance." The school name is bold and italic in capitals; the terms are the ones typed in the RFQ window (default 30 days after delivery) in bold. The Payment Details lines stay under it. The default terms are **30 days after the delivery** (the sentence adds "within"); the field is filled with it when the window opens and can be edited. The RFQ window has its own **Delivery Term** field (`rfq_delivery_term`, separate from the `delivery_term` of the other documents, whose default is Pick-Up), printed under the title of the quotation page as "Delivery: <term>" (no parentheses around the line), default **within thirty (30) calendar days from receipt of the Notice to Proceed (NTP)**; the field is filled with the default when the window opens and can be edited. A blank field prints the default.
- **Purchase Order:** its **Fund Cluster** is a field of the PO window (`fund_cluster`, default "General Fund", printed as typed; left empty prints "General Fund"; it no longer prints the Source of Fund). Until the PO is saved with its own, its **Date of Delivery** starts from the RFQ's Delivery Term (`rfq_delivery_term`) and its **Payment Term** from the RFQ's Terms of Payment (`terms_of_payment`), both editable; the PO's Delivery Term (Pick-Up) is separate. In the printed PO header table the Supplier value column is 37% wide and the P.O. No. label column 19%. Test: `PurchaseOrderFundClusterTest`.

Tests: `PrAppLinkTest`, `SobAppTest`, `OrsStatusPrintTest`, `NtpDeliveryDaysTest`, `StaffStatusAndRolesTest`, `RfqFinancialOfferTest`.

## 32. ORS: date of the Purchase Order and the final look of the printed form (approved by the owner 2026-10-15)

This refines 30.3 and 31.2. The printed ORS is `resources/views/ors-print.blade.php`.

### 32.1 Date

- The **date of the ORS is the date of its Purchase Order.** In the Create ORS window, choosing a Purchase Order sets the Date to the date of the PO and locks it (a small note says "The date of the Purchase Order."); with no PO the date is typed. The server does the same whatever the form sends: an ORS made from a PO takes the PO's date as its date (and as the date of the obligation), also in the preview.

### 32.2 Printed ORS

- **Font:** one font for the whole form, **Times New Roman** (labels, headings, data and signatures); the Particulars are 11.4px, the amounts 10.6px like the rest of the form.
- **Title block:** the three lines (OBLIGATION REQUEST AND STATUS, DEPARTMENT OF EDUCATION, the Schools Division Office) are evenly spaced.
- **Serial No., Date and Fund Cluster:** the three names are the same size (12) and right-aligned on their colons; the three values start on the same line, are the same size (12.5, smaller only when a value is too long), bold and underlined.
- **Section C:** the headings **Reference**, **Amount** and **Balance** are bold, the same size (11.6) and centered over their columns.
- **Payee, Office and Address (2026-10-16):** the three values are bold, also the Office when it is typed later.
- **Section C spacing (2026-10-16):** the line under STATUS OF OBLIGATION is higher, so the row of Reference and Amount is taller and the headings sit in its middle; Balance is raised a little. The details row starts one line below its top line, and its content is bold and on one top line: the Date (65 points wide; the date of the ORS, which is the date of its Purchase Order, 32.1, on one line, never wrapped), the Particulars (up to 7 lines), the ORS number and the amounts. The ORS/JEV/Check/ADA/TRA No. column is narrower (79 points) to give room to the Date and the Particulars. Its heading is centered in the column in a smaller size (9.4), clear of the lines.
- **Signature blocks (2026-10-16):** in both blocks the labels Signature, Printed Name, Position and Date are aligned at the left, with their colons in one vertical line; the Date under each block is the date of the ORS, centered like the name and the position.
- **A. and B. Certified (2026-10-16):** the two Certified paragraphs sit 7 points higher in their boxes.
- **UACS Object Code (2026-10-16):** the code is centered in its column, under the heading.
- **Fund Cluster (2026-10-16):** the Create ORS window has a Fund Cluster field (`ors_fund_cluster`), filled in with 101101 and free to change. The printed ORS shows what was typed; left empty, 101101 is printed (also for ORS made before this field). It is separate from the Source of Fund, which still drives the budget. Test: `OrsStatusPrintTest`.

Tests: `OrsStatusPrintTest`.

## 33. Accounting by the school and the printed Disbursement Voucher (approved by the owner 2026-10-15)

### 33.1 Who processes

- Accounting and Cash are **permission-based, not master-only**: a user with `accounting.approve` can review the ORS (Approve, Pending, Return) and create the DV; a user with `cash.pay` records the payment. A school administrator has both, so a school can process its own ORS to payment. Users without the permission see no buttons. The status chips on the Accounting page do not wrap.

### 33.2 Printed DV (`resources/views/dv-print.blade.php`)

- **Payee and Address:** bold, left aligned, centered vertically in their cells; a long text takes a smaller size and a second line instead of being cut. The TIN is bold under its label.
- **C. Certified, D. Approved for Payment, E. Receipt of Payment:** the headings are centered vertically in their rows and left aligned. The Check/ADA number is bold and centered in its cell; the date of payment is bold on the same middle line.
- **Signature blocks:** Signature, Printed Name, Position and Date rows have enough height; each label and each value is centered in its row; "Printed Name" is one line.
- **Header and Fund Cluster (2026-10-16):** the box at the top right is 20 points wider than the first drawing and has no line between Fund Cluster and Date; Fund Cluster, Date and DV No. are on three evenly spaced lines in a larger font (10.5). The labels are plain; the data (Fund Cluster, date, DV number) are bold and underlined. The Fund Cluster is a field in Create DV and Edit DV (`dv_fund_cluster`), filled in with "General Fund" and printed as typed; left empty, "General Fund" is printed. A Fund Cluster of up to 12 characters sits on the line of its label; a longer one goes under it, in two lines if needed. In E. Receipt of Payment, "Bank Name & Account Number:" stays in its own cell but sits higher in it, leaving room below for the account number, and "Printed Name:" is at the top of its cell with no name printed, so it can be written by hand. Test: `DvFundClusterTest`.

### 33.3 Accounting Entry (journal)

- Up to **4 lines** are bold and spread evenly in the table of the form (credit titles are indented a little).
- **5 to 20 lines** are accepted (`FinanceController::JOURNAL_MAX_LINES`, validated on save; "The DV has room for 20 accounting entry lines."). From 5 lines the rows are smaller and closer, the table grows and everything under it (C, D, E, signatures, the optional Appropriation table and the side borders) moves down, with the column lines following. Beyond 10 lines the **Particulars box gives up part of its height** (at most 54, less when deductions are printed) so the form still fits the page; the rows then get smaller, to size 5.5 at the least. Total debit equals total credit equals the amount of the DV, as before.

### 33.4 Less: Deductions

- Everything inside the Particulars (the particulars text, the Responsibility Center, the amounts, the Less lines) is printed bold.

- The Create DV window has an optional **Less: Deductions** box, **below the Journal entry**: Withholding Tax, Liquidated Damages, Retention, Mobilization (amounts typed by hand, saved in `liquidation_reports.dv_deductions`), and an **Add other deduction** button for any other one, typed by name and amount (saved under `other`). At most 6 deduction lines in all (`FinanceController::DEDUCTION_MAX_LINES`); each other deduction needs a name and an amount; the total must be less than the amount of the DV. The box is emptied each time a DV is opened.
- The printed DV shows **Less:** and only the deductions that were typed, at the foot of the Particulars, before **Amount Due** (the usual ones, then the others); **Amount Due is the amount less the deductions**. The amount at the top of the Particulars stays whole. The journal entry still balances against the whole amount of the DV (the deductions are entered as credit lines).

Tests: `DvJournalEntryTest`, `OrsStatusPrintTest`, `DvAppropriationPrintTest`.

### 33.5 Rows of Accounting and Cash (2026-10-16)

- The row of an ORS with a DV has no journal entry list and no Signatories button: the journal entry is set in Create DV and shown by the documents, and the signatories are chosen when the DV is made. The Edit and Print icons of the Budget list sit side by side in one line at the right.
- Each row starts with the supplier name in bold, with a storefront icon before it; under it, the document numbers as badges of their own colors: **ORS** (violet), **PR** (blue) and **PO** (amber; the number of the Purchase Order of the PR, shown only when the PR has one); then the particulars.
- A DV number is shown as it is (it already starts with "DV-"), never as "DV DV-..."; a number typed without the letters gets "DV " in front of it. This holds in the rows, the Edit DV and Cancel Check windows, the Cancelled Checks register, the Stage column of the Budget page, the messages of Accounting and the references of the Allotment Registry; all of them use `LiquidationReport::dvLabel()`.
- Status / DV column of Accounting (an ORS with a DV): the status badge, then the DV number on one line, the full date of the DV on its own line below it (never wrapped), then a sky-blue badge **Cash for Disbursement**.
- **Record Payment** (Cash) asks Mode of Payment and Date Paid (required), the reference no., and optional details: Fund Cluster, Check Date, Bank, Account Name, Account No., Received / Claimed by and Remarks (`payment_fund_cluster`, `check_date`, `bank_name`, `bank_account_name`, `bank_account_number`, `received_by`, `payment_remarks` on `liquidation_reports`). **Defaults:** Date Paid and Check Date are the date of the DV; Fund Cluster is the DV's (General Fund when empty); Bank, Account Name and Account No. are the school's from School Settings; each can be changed. They show under the payment in the Cash row when filled and are cleared when the check is cancelled (37).
- Tests: `FinanceRowBadgesTest`, `CancelledCheckTest`.

## 34. APP-CSE: a disclosure for each category (approved by the owner 2026-10-15)

- In the APP-CSE entry page (`/planning/app-cse/{id}`), each **category band** (Common Office Supplies, Construction Components and Supplies, Other Categories, and so on) is a disclosure with its arrow on the left: it folds and opens the month headings and the items of that category. Every category is open when the page opens. It is the same in Part I, Part II and in the folded list of items with no quantity yet. The printed APP-CSE does not change.

Tests: `AppCse*` (the page still renders with its categories).

## 35. Cloudflare Turnstile on sign-in and registration (approved by the owner 2026-10-16)

- The **Sign in** and **Register** forms carry the Cloudflare **Turnstile** human check, above the Sign in button and above the Register button. The server verifies the answer with Cloudflare (`App\Support\Turnstile`, called first in `AuthController::store` and `storeRegistration`); without a passing answer the sign-in or registration is refused with "Please complete the security check and try again." (also when Cloudflare cannot be reached).
- It is **off until both keys are set**: `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` (environment, read in `config/services.php`; listed in `.env.example`). With either one empty the forms show no widget and behave as before. The keys come from the owner's Cloudflare dashboard (Turnstile widget, with the live domain, and `localhost` to try it locally); they are never kept in the repository.

Tests: `TurnstileTest`, `LoginIdentifierTest`.

## 36. The sign-in page (approved by the owner 2026-10-16)

`resources/views/auth/login.blade.php` is a two-part page:

- **Brand panel (large screens only):** a navy gradient with the ProcureMS emblem and name, the line "One workspace, from the plan to the payment.", and four cards for the flow: **Plan** (SIP, AIP, SOB and APP), **Procure** (PR, RFQ, abstract, PO and delivery), **Account** (ORS, DV and journal entry), **Pay and liquidate** (cash payment and liquidation report). The panel carries no figures or promises that the system does not keep.
- **Sign-in card:** "Welcome back", then email or username, password (with show/hide), the Cloudflare Turnstile check when it is on (section 35), Remember me, the Sign in button (with its "Signing in" spinner), a "New here" divider and the **Pre-register school** button. On a phone the panel is hidden and the emblem and name sit above the card.
- Nothing about how sign-in works changed: the field names, the error messages, the Turnstile check, the link to pre-registration and the demo notes (local and demo copies only) are as before.

Tests: `LoginIdentifierTest`, `TurnstileTest`.

## 37. Cancelled checks (approved by the owner 2026-10-16)

A paid check can be cancelled and the payment made again after the ORS, DV and journal entry are corrected. The ORS goes through the usual steps again.

- **Cancel (Cash > Paid tab, permission `cash.pay`):** the **Cancel Check** button asks for a reason and the signed-in user's own password. The check (check no., DV, ORS, mode, date paid, amount, reason, who and when) is saved in the **Cancelled Checks** register. The ORS loses its payment, goes back to **For Review** and is marked `reissue_pending`; its DV and journal entry are removed (the old journal and deductions are kept in the audit log, the old DV number in the register). The Allotment Registry follows by itself (it reads the payment from the ORS).
- **Edit ORS (Accounting > For Review, permission `accounting.approve`):** only for an ORS whose check was cancelled and that is not approved yet. It changes the amount, supplier (payee) and particulars; a reason and the user's own password are needed; the old and new values go to the audit log. Then the ORS is approved again.
- **Ready for DV:** the DV and its journal entry are made again. The cancelled DV number is offered again, and the signatories chosen at the start are kept (the picker is not asked again).
- **Edit DV (Accounting > With DV, permission `accounting.approve`):** on any DV that is not paid yet. It changes date, mode, payee, particulars, journal entry and deductions (same checks as creating the DV: chart accounts, balanced, equal to the ORS amount). A reason and the user's own password are needed on every save; the old values go to the audit log. A paid DV cannot be edited: cancel the check first.
- **Edit DV window (2026-10-16):** it looks like the Create DV window: a header with "Edit Disbursement Voucher" and the DV number, the ORS, the supplier and the amount, and a close button; the form on the left and the **official DV on the right**, drawn from the form as it is now (zoom: Fit width, 50%, 75%, 100%; nothing is saved by it, and the password is never sent to it). The form has parts for Voucher, Journal entry (with Account, Debit and Credit headings), Less: Deductions and **Confirm the change** (reason and the user's own password, in a framed box); Cancel and Save changes stay at the bottom. The reason and the password are not filled in by the browser (the password field is `new-password`, and both are read only until they are clicked); the same holds in the Cancel Check and Edit ORS windows. The show/hide eye of every password field is centered in its field.
- **Pay again:** the ORS returns to Cash > DVs For Payment. Recording the new payment clears `reissue_pending` and locks the records as before.
- **Rows of Accounting and Cash** show the supplier name in bold on top, then the ORS number and the PR, then the particulars. An ORS whose check was cancelled shows no Journal entry list and no Signatories button.
- **Cancelled Checks tab (Cash):** read-only list, newest first, with the search box.

Tests: `CancelledCheckTest`.

## 38. Form Generator and the Program of Works (approved by the owner 2026-10-16)

Design: `docs/superpowers/specs/2026-10-16-form-generator-pow-design.md`. The owner's workbook `Program of Works Lubas.xlsx` is the source of truth for the form.

- **Form Generator** (`/planning/forms`, route `planning.forms`): a page under the SOB, opened by the **Form Generator** button of the SOB panel in Planning. The filters are School, Fiscal Year, SOB (quarter), Program and Activity ("All activities of the program" is allowed). The forms come from the registry `App\Support\GeneratedForms`; Program of Works is the first. Under the form, the list of the saved POWs of the school (Open, Print).
- **Materials** are all the items of the chosen Program and Activity (the other lines are not materials): description = particulars, unit, quantity = frequency x quantity, unit cost; each line has a checkbox, and an unchecked line is not saved. **Labor** starts as the sum of the other lines (35% Labor) and can be changed. **Total Project Cost** = Total Materials + Labor and is also the **Budget Allocation**.
- The form also takes Project Title (default the program), School Address (default from the school profile), Date, Date Started, Date of Completion, Minimum Required Manpower and Equipment, and four signatures: Prepared by (default the School Head), Recommending Approval, Approved, and an optional second Approved (Schools Division Superintendent, printed only when filled). The three division slots start from the school's latest POW.
- Every POW is **saved** (`pow_plans`, `pow_items`) as the POW's own copy: changing or deleting the SOB items afterwards does not change it. **POW page** (`/planning/pow/{id}`): edit the fields and lines (three blank rows to add lines; unchecked or blank lines are dropped), Save, Print, Delete. `planning.manage` creates, edits and deletes; `planning.view` opens and prints; a POW of another school or organization is not found. Audit entries `pow_created`, `pow_updated`, `pow_deleted`. An SOB that has a POW cannot be deleted until the POW is deleted.
- **Look (2026-10-16):** the POW fields (Form Generator and POW page) follow the form design standard of section 7: four numbered cards, **Project details**, **A. Materials**, **Cost summary** (B. Labor Cost beside the summary box with Total Materials Cost, B. Labor Cost and C. Total Project Cost) and **Signatures**. In the Saved Programs of Works list the actions are icon buttons only: Edit (pencil, opens the POW page), Print (printer) and Delete (red trash, asks first, only with `planning.manage`).
- **Print** (`/planning/pow/{id}/print`, `resources/views/pow-print.blade.php`): the shared print engine, **Folio** (210 x 330 mm) portrait by default, 10 mm margins; the Paper Size of the toolbar lets the user switch to A4 when the POW is short. Letterhead with the DepEd logo on the left and the school logo on the right (`OfficialDocument::logos()`), PROGRAM OF WORKS, MOOE and the quarter, the information block, the table (Item No., Item Description, Unit, Quantity, Unit Cost, Total), TOTAL MATERIALS COST, B. Labor Cost, C. Total Project Cost, and the signatures (names bold, capitals, underlined). School ID is in its own cell beside its label (the DepEd School ID of the school profile, else the system School ID).

Tests: `PowModelTest`, `PowServiceTest`, `FormGeneratorTest`, `PowPageTest`, `PowPrintTest`.

## 39. Cash reports (approved by the owner 2026-10-16)

Design: `docs/superpowers/specs/2026-10-16-cash-reports-design.md`. The owner's workbooks `11REPORT OF CHECKS ISSUED.xlsx` (Appendix 35), `CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD.xlsx` and `CASH DISBURSEMENT REGISTER.xlsx` are the source of truth for the three forms.

- **Cash > Reports tab** (`/cash?tab=reports`, permission `cash.pay`): a button for each report (the school and the period are chosen inside each report's window, default the current quarter) and a fourth card, **Bank Reconciliation**. The Fund received panel is gone: the cash downloaded to the account (opening balance, NCA, cash advance; table `cash_fund_receipts`) is now recorded inside the Bank Reconciliation window (list, add, edit, delete) and still feeds every report. Each report button opens a split view (like the procurement document form): Report No. and Sheet No. on the left, the real report on the right, updating as the numbers are typed, and an **Open to print** button (2026-10-17). The left form is per report: school and period, header (Report No., Sheet No., Fund Cluster, Bank Name / Account No.; blank keeps the default), the signatories (Disbursing Officer / Cashier for the Report of Checks Issued, Disbursing Officer for the Checks and Advices, Certified correct and Received by for the Register; chosen from the school's active staff of that school only) and Remarks printed above the signature. Nothing is stored; the values travel in the report URL. The reports are live: they read the paid ORS and DV, the Cancelled Checks register and the Fund received, and store nothing.
- **Fund received** (`cash_fund_receipts`, `CashFundReceipt`, `cash.fund.store|update|destroy`): the opening balance, NCA received or cash advance of a school (date, kind, fund cluster, bank account no., amount, reference, note), typed per quarter; nothing is carried forward automatically. Each add, change and delete is audited (`cash_fund_received`, `cash_fund_updated`, `cash_fund_deleted`); a school sees only its own entries.
- **`CashReportService::build(school, from, to)`:** the check lines of the period (date = check date, else date paid; check serial no. = the payment reference; DV, ORS, responsibility center, payee, UACS code and nature of payment from the ORS budget line's account, else the debit line of the DV journal), the Cancelled Checks of the period as "Cancelled Check" lines with no amount, the ADA payments (mode ADA), the running balance (nothing is carried forward from an earlier period: the balance starts from the fund received typed for the period), the mode of each line (check, ADA, or Others for cash and the like, which the check reports leave out and the register keeps), the totals and the accounts actually paid. An unpaid ORS never appears.
- **Report of Checks Issued** (`cash.report.roci`): Appendix 35 columns Check Date, Check Serial No., DV/Payroll No., ORS/BURS No., Responsibility Center Code, Payee, UACS Object Code, Nature of Payment, Amount, total and the certification with the first and last check numbers. Checks only (ADA payments are not listed).
- **Checks and Advices to Debit Account Disbursement Record** (`cash.report.checks`, 2026-10-17, a copy of the owner's workbook sheet `chck_advice`): Calibri, no logos, the title, Entity Name and Bank Name / Bank Account No. on the left, Fund Cluster and Sheet No. on the right, a boxed record that starts with the Accountable Officer (underlined), Official Designation and Station, then one grid: NCA/DS/DV/Payroll (No., Date), Check/ADA (Serial No. Check and ADA, Date, Date Released), Payee, UACS Object Code, Nature of Payment, Amount (NCA Received Deposit Mode, Check Issued, ADA Issued, NCA / Bank Balance) starting from the balance forwarded; CERTIFICATION (the officer's position, the entity and the period typed in the sentence), the officer's name with a line, the position and a DATE line. No total row.
- **Cash Disbursement Register** (`cash.report.register`, 2026-10-17, a copy of the owner's workbook `CASH DISBURSEMENT REGISTER.xlsx`, sheet `CASH_DISBRSMNT`): Calibri, no logos, the title, the boxed header (Entity Name, Sub Office / District / Division, Municipality / City / Province, Fund Cluster on the left; Name of Accountable Officer, Official Designation, Station, Registered No. (the DepEd School ID), Sheet No. on the right, values underlined; no bank line), one grid: Date, Reference, Particulars, Working Fund (Cash Advance, Payments, Balance) and Breakdown of Payments with nine account columns always drawn (title, then the UACS code), "Beginning Balance….", the rows, the Grand Total; below it the Recapitulation (a bordered table of Account Description, UACS Object Code and Amount, with a TOTAL), the note that the Payments total must equal the sum of the Breakdown totals, and the CERTIFIED CORRECT and RECEIVED BY boxes with Date. More than nine accounts continue in a table below with the same rows. The typos of the workbook ("Disrtrict", "Recaptulation") are corrected.
- **Print:** the shared print engine (long bond, landscape, 10 mm), the header from School Settings (school, region, division, bank name and account no., fund cluster of the latest payment), the Accountable Officer = the active employee with the Disbursing Officer role. A period with no payments still prints.
- Tests: `CashFundReceiptTest`, `CashReportServiceTest`, `CashReportPrintTest`.

- **Bank Reconciliation** (`/cash/reports/bank-reconciliation`, 2026-10-17): only the bank book. Its window asks for the school and the period and keeps the records of the **cash downloaded** (kinds Opening balance, Balance forwarded, NCA received, Cash advance; add, edit, delete); the report shows the cash downloaded and the checks and ADAs paid in date order (cash downloaded first on the same day) with the running balance, the balance forwarded from before the period on top and the totals. A payment in cash (mode Others) is left out. No header details, statement comparison, signatories or remarks. **Its balance before the period is the beginning balance of the other reports**: `CashReportService` returns `opening` (cash downloaded less all payments before the period, the register's working fund) and `opening_bank` (the same without cash payments, used by the Checks and Advices and the Bank Reconciliation).
- **Report of Checks Issued layout** (2026-10-17, owner's approval): "Appendix 35" sits above the logos, the two logos are close to the title block, each label of the header table sits right beside its value, Entity Name, Fund Cluster and Bank Name / Account No. are underlined, and Report No. and Sheet No. (underlined) sit at the right edge of the header. Report No. defaults to the year and month of the day the report is made and the sequence, `YYYY-MM-001` (for example 2026-10-001), and a number typed in the window replaces it. Sheet No. is `1 of N`, N being the printed pages of the table, and the certification states the same N as its number of sheets. The signature block is one row at the right: the Disbursing Officer's name with a short line and "Name and Signature of Disbursing Officer / Cashier", then the Date. The other reports keep their original header.
- **Report window form**: "Period covered" dropdown (1st to 4th Quarter C.Y., or Custom dates) fills From and To and follows typed dates; the **Year (C.Y.)** is typed by hand (a 2026 liquidation can be made in 2027), defaults to the year of the current quarter and moves the chosen quarter to the typed year; Fund Cluster, Bank Name and Bank Account No. start from School Settings (then the last payment) and a typed value is kept; Bank Name and Bank Account No. are on one row.

- **New DV without an ORS** (2026-10-17): Accounting > **New DV (no ORS)** takes the school, payee, particulars and amount and starts a record with no ORS number, already approved and waiting in Ready for DV; the Create DV window then opens for it (journal entry, deductions, signatories from the school's staff roles, own DV number). It goes to Cash for payment and appears in the cash reports like any DV, but it is not an obligation: it is left out of the Budget ORS lists and totals, the Liquidation list, the dashboard counts and the Allotment Registry (`LiquidationReport::withOrs()`), and it has no ORS print. Permission `accounting.approve`; audit action `dv_manual_started`.

- **Report window** (2026-10-17): every signatory has a **Type a name (not in the staff list)** choice (first under the default) with Full name and Position boxes, for someone who is not school staff. The header fields of the window follow the order of the printed form of each report (register: Entity Name, Sub Office / District / Division, Municipality / City / Province, Fund Cluster, Name of Accountable Officer, Official Designation, Station, Registered No., Sheet No.; Checks and Advices: Entity Name, Bank, Fund Cluster, Sheet No., officer, designation, station) and start from the school's profile and staff roles.

## 40. Screen switch (approved by the owner 2026-10-17)

- The master user turns the screen of the **whole system** off and on in the dashboard menu: **Screen** beside System, KPI and Server Dashboard (`home?view=screen`, `PUT /screen`, master only). The setting is `system_settings.screen_off` (`SystemSetting`), the default is on, and each change is written to the audit log (`screen_turned_off`, `screen_turned_on`).
- While it is off, `EnsureScreenIsOn` (before the sign-in check) answers every visitor and every signed-in user except the master with the **"This System is Under Maintenance"** page (the owner's picture `public/images/maintenance-system.webp`; HTTP 503, no sign-in form, `noindex`), including the plain `/login`. (Until 2026-10-18 the page said only "Link is not available".)
- The master signs in at **`/login?master`** (the sign-in submit, `/logout` and the health check `/up` stay open), then turns the screen back on. A school user who signs in meanwhile sees the same message.
- **Tabs (owner's request 2026-10-18):** under the same Screen view a second card, **Tabs**, lets the master tick single tabs to put under maintenance (`PUT /screen/tabs`, `system_settings.screen_off_sections`, audited as `screen_tabs_changed`): Budget, Accounting, Chart of Accounts, Allotment Registry, Cash, Procurement, Liquidation, Planning, Google Drive, Reports, User Management, Subscriptions, School Management, School Settings (found by address in `App\Support\ScreenSections`; Chart of Accounts is matched before Accounting). Everyone but the master who opens such a tab sees the **"This Site is Under Maintenance"** page (`public/images/maintenance-site.webp`, HTTP 503, `noindex`) with the tab's name and a **Back to Dashboard** button; the Dashboard, sign-in, sign-out and the master's own access stay open, and the rest of the system keeps working. The whole-system switch wins over the tabs.
- Tests: `tests/Feature/ScreenSwitchTest.php`.

## 41. Page access by permission (owner's decision 2026-10-17)

- **Reports** (`/reports`) needs `reports.view`. The **New Purchase Request** form (`/procurement/create`) needs `procurement.create`. The **Chart of Accounts** page needs `accounting.view` or `accounting.manage` (the Budget Officer manages it with `accounting.manage`). A role without the permission is refused with 403.
- The dashboard diagnostics (`/dashboard/diagnostics`) stays open to every signed-in user: it only says whether the database and storage work.
- The AIP page rule is in 23.2. Tests: `RoutePermissionGapsTest`.
- **The Reports page** (`/reports`) shows no figures, charts or sample content. It is one list of links to the real reports: the Annual Implementation Plan, the 12 budget reports (screen and CSV), the Allotment Registry, the Chart of Accounts, Accounting (ORS and DV) and the Cash reports. A link is shown only to a role that can open it (`planning.view`, `budget.view`, `accounting.view` or `accounting.manage`, `cash.pay`); a role with none sees a plain message. Owner's decision 2026-10-17. Tests: `ReportsPageTest`.

## 42. Delivery, inspection and the Disbursement Voucher (owner's decision 2026-10-17)

- **Inspection and Acceptance Report (IAR):** a new IAR needs the actual received quantity typed for every line (0 if nothing arrived, the full quantity if everything arrived); a blank is refused, and the quantity cannot exceed the Purchase Order quantity. There is still one IAR per request, which keeps the total received so far; separate deliveries are recorded in it (section 43). Accepted and rejected quantities are in section 44.
- **Who saves it:** a role with `procurement.edit` or `inspection.edit` (the Inspector role). An inspection officer can save and preview the IAR only; every other procurement document still needs `procurement.edit`.
- **Disbursement Voucher:** the first DV of an ORS made from a Purchase Order is refused (422, also in the DV preview) until the request has an IAR: a DV for goods follows an accepted delivery. Not held: an ORS without a Purchase Order (utilities, travel), **New DV (no ORS)**, and editing a DV that already exists. The DV amount is not tied to the received amount: it is the obligation of the ORS, and the journal entry must balance and equal it (owner's decision 2026-10-18, no change of rule).
- Tests: `DeliveryAcceptanceRulesTest`.

## 43. Part I by search and partial deliveries (owner's decision 2026-10-18)

- **APP-CSE Part I:** on the APP-CSE page, the Part I section (draft, for someone who manages planning) has a search box, "Find an item sold by the Procurement Service". Typing two or more characters lists matching items of the plan's catalog year (by description, code or category, every word must match, 25 at most, items already in the plan left out) from `GET /planning/app-cse/{plan}/catalog`; **Add to Part I** adds the item (a catalog without a price asks for one) and its monthly quantities are typed like any line. An item not found is not sold by PS and belongs in Part II. Tests: `AppCseEntryTest`.
- **Partial deliveries:** the IAR form shows, per item, Ordered, Received before, **This delivery**, Total received and Balance. The first delivery starts at the full Purchase Order quantity; a later one starts at 0 and is added on top of the total already received (to correct an earlier entry, change the **Accepted before** figure (it is editable; the history records the change as a negative or positive line); the total stays between 0 and the Purchase Order quantity). `received_items` still holds the total received so far, so the delivery reconciliation, IARS and ICS are unchanged. Each save that changes a total adds a line to `deliveries` in the IAR (date, user, quantity per item), shown as "Delivery history" in the form. A save that changes nothing adds none. Tests: `DeliveryAcceptanceRulesTest`.
- **SOB feeds Part I and Part II by itself (owner's decision 2026-10-18):** in the SOB item form, the **Particulars** box searches the Procurement Service catalog as you type (`GET /planning/sob/{sob}/catalog`, the catalog the year's APP-CSE uses). Picking an item fills the particulars, unit and unit cost and marks the line (`sob_items.cse_catalog_item_id`); a note under the box says "Sold by the Procurement Service: APP-CSE Part I" or "Not from the PS catalog: APP-CSE Part II". Typing over the picked text unlinks the line. When the SOB is approved, the sync (`AppCseService::syncFromSob`) writes marked lines to **Part I** (catalog description, unit and price, one line per catalog item with the quarter quantities) and all other lines to **Part II**, as before. A catalog item already added by hand to Part I is not doubled: its SOB line goes to Part II. Tests: `AppCseFromSobTest`.
- The Part I / Part II note under the SOB Particulars box takes no height from the row, so the boxes of a particulars row stay aligned (owner's rule 2026-10-18, CLAUDE.md rule 8).
- **The Category of a SOB line decides the part (owner's decision 2026-10-18):** the SOB Category dropdown lists **Part II · Not available at PS** first (the seven categories, the default being the first) and then **Part I · Available at PS** (the Procurement Service categories of the latest catalog). A line under a Part I category, or one picked from the catalog search, goes to **Part I** of the APP-CSE; every other line goes to Part II. Because PS items and prices change, the item can be **typed by hand**: Part I uses the description, unit and the **unit cost typed in the SOB** (not the catalog price). A Part I item not in the catalog is added as a catalog item seen only by that organization (`cse_catalog_items.organization_id`, code `OWN-nnn`, shares the sort order of its category; the unique index on `catalog_year, sort_order` became a plain index) and is found by the search next time. Picking a catalog item also sets the line's Category to its PS category. A SOB line under a Part I category goes to the APP as "Other Categories". Tests: `AppCseFromSobTest`, `AppCategoriesPrintTest`.

## 44. Accepted and rejected deliveries, Part II entry, DV notice (owner's decision 2026-10-18)

- **Accepted vs rejected:** `received_items` of the IAR is the quantity **accepted** (so IARS, ICS, the receiving balance and the reconciliation need no change). The IAR form has, per item, **Rejected (this delivery)**; `rejected_items` keeps the total rejected (goods that arrived but were refused). Rejected goods stay in the balance until delivered again. Each save that changes an accepted or a rejected total adds a line to `deliveries` (date, user, accepted change per item, rejected change per item). Tests: `DeliveryAcceptanceRulesTest`.
- **Delivery history on paper:** the delivery reconciliation print has a **Delivery History** table (number, date, accepted, rejected, recorded by) before the signature.
- **Part II entry:** the Part II section of the APP-CSE page (draft, for someone who manages planning) has **Add my own item to Part II** again (category, description, unit, price). Lines from the SOB are still added by themselves. Tests: `AppCseEntryTest`.
- **DV notice:** the Create DV and Edit DV forms show an amber note, "Accepted so far: ₱X of the ₱Y obligated", when the request has an IAR and the accepted amount (at the Purchase Order prices) is below the obligation. It does not block: the DV amount stays the obligation and the journal entry must equal it. Tests: `DeliveryAcceptanceRulesTest`.
- **IAR form:** the separate **Inspection Officer / Committee Name** box is removed (owner's decision 2026-10-18); the Inspection Officer is chosen in the Signatories at the bottom of the form, as for every other document. An older IAR that saved a typed name still prints it.
- **IAR form layout:** each item is a compact block (Accepted before, This delivery: accepted, This delivery: rejected, then Total accepted, Total rejected and Balance) so it fits the form pane without sideways scrolling; the three boxes are aligned on one line. Checked on the local server 2026-10-18.

## 45. Fixes from the local walk-through of DV, payment and liquidation (2026-10-18)

- **Suggested DV number:** each **Create DV** button carries the next DV number of **the ORS's own organization** (`data-next-dv`), and the form uses it. Before, the number was computed for the signed-in user's organization, so a master or Sub-master user was offered a number that already belonged to the school ("The dv number has already been taken"). Tests: `DeliveryAcceptanceRulesTest`.
- **Liquidation page status:** the Approve, Docs and Return buttons are shown only for an ORS that is not yet approved and has no DV and no payment; the server refuses (422) to move an ORS that is **paid** ("A paid ORS cannot be reopened") or **has its DV** ("Edit the DV in Accounting instead") to any status other than approved, as the Accounting screen already did. Tests: `DeliveryAcceptanceRulesTest`.
- Walked on the local server: Create DV (journal balanced), Record Payment, the Liquidation list.- Walked on the local server with no defect found: all 17 links of the Reports page open; the cancelled-check cycle (Cancel Check with reason and password, the Cancelled Checks register, Edit ORS, Approve, Ready for DV with the same DV number, Create DV, Record Payment again).

## 46. Liquidation papers checklist (owner's decision 2026-10-18)

- **Kinds of expense** (`liquidation_reports.ors_kind`): Goods (from a Purchase Request, the default when the ORS has a PR), Direct payment (utilities, services; the default without a PR), Travel, Other. The person who manages Accounting can change the kind in the Papers window.
- **Papers by stage:** each paper is needed *before the ORS is approved* (`ors`) or *before the DV is created* (`dv`), since delivery and trip papers only exist later. Goods: before approval RFQ, Abstract, NOA, NTP, PO (the system reads these from the request; a document marked not required on the request counts as not required); before the DV the Inspection and Acceptance Report (system), sales invoice or official receipt, delivery receipt. Direct: billing statement or official receipt, then proof of service. Travel: travel order and itinerary, then certificate of appearance and receipts or trip ticket. Other: one supporting paper. The lists are in `App\Support\LiquidationChecklist`.
- **Manual papers** are Missing, Received or **Not required for this transaction** (with an optional note); the same paper is not needed in every transaction (for example the itinerary of a local trip). Saved in `liquidation_document_checks` (audited, with a transaction event).
- **Gates:** Approve ORS (Accounting, and the Approve button of the Liquidation page) is refused while a paper of the ORS stage is missing, and **Create DV** while a paper of the DV stage is missing; the message names the missing papers. This also applies to an ORS that is already For Review. A DV made by hand with no ORS, a DV that already exists and a paid ORS are not held. **Pending Documents** without a typed remark fills the remark with the missing papers.
- **Who:** only a user with the accounting approval permission (the one who manages Accounting) ticks the papers; the school cannot.
- **Screen:** every ORS row in Accounting has a **Papers** button (red with the count when something is missing) that opens the window: the type of expense, the papers by stage (system papers show Prepared or Not prepared yet), the Missing / Received / Not required choice and a note for the others. **Approve** is disabled with the reason while a paper before approval is missing.
- **School's own papers:** School Settings has a tab **Liquidation Papers** (permission `organization.settings`) where a school adds a paper for a kind of expense and a stage (`school_checklist_items`); it appears in the Papers window of every ORS of that school and kind, is ticked like any manual paper (key `school_<id>`) and holds Approve or Create DV the same way. Removing it stops it from being asked; ticks already saved stay in the record.
- **Read-only list:** every ORS on the **Liquidation** page has a folded **Papers N of M** list (done papers checked, missing ones open circles).
- File upload was not chosen.
- Tests: `LiquidationChecklistTest` (also the school's own papers and the read-only views); the fixtures of other tests use the kind Other (`ors_kind`) or tick the paper.

## 47. Purchase Request form shows why a save was refused (2026-10-19)

- The New Purchase Request form lists every validation error in a red box at the top of the form. Before, errors on the items or the Budget line (for example "Add the items from the SOB activity") had no place on the page and the form reloaded blank, so the PR looked as if it would not save.
- No rule changed: the PR still needs the items of an SOB activity.

## 48. Notice of Award project line (owner's decision 2026-10-19)

- The Notice of Award names the project as the transaction description, then the Project Title, then the school: "Procurement of Office Supplies of 3rd Quarter MOOE CY 2026 of Lubas Elementary School". Without a transaction description it starts with the Project Title. The year is only what is typed in the title. Nothing else on the NOA changed.
