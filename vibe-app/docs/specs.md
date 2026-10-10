# ProcureMS specifications

Standing specifications for the whole system. They apply to every existing and future module. Product rules and phase order are in `CLAUDE.md` and `docs/PROCUREMS_CLAUDE_HANDOFF.md`. Last reviewed 2026-10-09.

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
- A document can be prepared only after the PR is approved and the document before it exists (`ProcurementWorkspaceService::PREREQUISITES`, `blockedReason()`). The Procurement Documents page shows a locked card with the reason; the server enforces it on save and in the live preview.
- IAR, IARS, RIS, ICS, and PAR live under **Receiving** (`?stage=receiving`, tab "Receiving documents"); the Receiving tab links straight there. The first five documents live on the normal Procurement Documents page.

### 2.3 Stage, progress, completion

- Stages: Request, Approval, Canvass, Award (Abstract, NOA, NTP), Purchase Order, Receiving (IAR, IARS, RIS), Complete.
- A request is **Complete** only when its status is `completed` **and** all eight required documents exist. A status flipped early does not make a stage complete. "Mark complete" appears only when the documents are complete and is refused by the server otherwise.
- Document Center: one **Process** button per row (opens that request's documents, or the Receiving documents for a receiving document) and an eye icon beside the progress to open the document. Progress chip: **On processing** until the request is Complete, then **Delivered**; with "x of 8 steps".
- Request page: one box holds **Next action** and the button that fits the **stage** (Start canvass only at Canvass; Open documents at Award and Purchase Order and Complete; Open receiving documents at Receiving; Mark complete when allowed). Start canvass sends the user straight to the documents.

### 2.4 Documents page

- Cards are colored by state: prepared green, not prepared light red, coming soon grey. The PR card is always prepared.
- The document form opens as a split view: form on the left, the real document on the right (2.5). The modal starts to the right of the sidebar and widens by itself when a form needs room (bidder tables, IARS distribution).
- The RIS generation summary is a list: number, staff name and position, issued items, RIS number.

### 2.5 Live document preview

`POST /procurement/{request}/documents/preview` runs the real save inside a transaction, renders the print page, and rolls back. It reuses every validation and rule of saving, so it can never disagree with the saved document. It debounces input (1 s) and offers fit-width and 50 / 75 / 100% zoom.

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
- **School details:** school logo, **School ID (auto-generated, read-only)**, school name, type, address, contact no., email.
- The School ID is generated when the school is created and never typed. The User ID is shown per account in System Users, not here.
- Fiscal year and document numbering are not editable here; documents keep numbering as before.

### 5.2 System Users

- Table: name, email, User ID and username, position, **official station (the school)**, system role, last sign-in, password date, status. Edit, Password. Users cannot be added here: one user manages one school.
- **Full name and username never change** after the account exists (read-only on screen and ignored by the server, even for the master user).
- **Master User tab** (`/user-management?tab=master-user`, master user only): the master edits their own account (full name, username, e-mail, phone, position; unique e-mail and username) and changes their password (current password required, at least 12 characters, different from the current one). Role and status are not part of this form. Written to the audit log (`master_profile_updated`, `master_password_changed`). The master user may change their own name and username; for other accounts the rule below still applies. Tests: `tests/Feature/MasterUserTabTest.php`.
- **Only the master user changes a role.** A school administrator adds people as Viewer and cannot assign `school_admin`; role changes sent by a non-master are ignored. Nobody changes their own role or status.
- Changing your own password needs the current password; an administrator can set another user's password. An inactive account cannot sign in. Sign-in accepts the email address or the username. User IDs look like `USR-000009`.

### 5.3 Employees and roles

- Employees (not necessarily system users) with a position (for example Administrative Officer II) and an auto employee number (`EMP-000012`).
- One person holds many roles in three groups: **BAC** (Chairperson, Vice Chairperson, Secretariat, Member, TWG Member, Observer), **Procurement** (Requesting Officer, Procurement Officer, Approver, Canvasser, Supply Officer), **Documents** (Disbursing Officer, Inspection Officer, Property Custodian, Accountant, Budget Officer, Cashier). A school adds its own role to any group.
- Roles are stored comma separated in `bac_role`, `procurement_role`, `document_role`; always read them through `SchoolStaff::hasRole()` / `rolesFor()`, never with `=`.

### 5.4 Logos

- Formats PNG, JPG, WebP, GIF; up to 2 MB; square; at least 300 x 300 px; transparent or white background. The same "Logo requirements" box under each logo. Current file name, size, and dimensions are shown; a new choice is checked in the browser (type, size, small-image warning) before saving. The server refuses other types and sizes with a plain message.
- Uploads need a writable `upload_tmp_dir` (section 8).

## 6. Pre-registration

- The first person of a school is entered as Given Name, Middle Initial (one letter or blank), Surname, Username, Position, Email, Contact Number, Password; the full name is assembled ("Maria D. Santos"). The role (School Administrator) is automatic and not shown.
- Before submitting, a dialog shows the full name and username as **final and cannot be changed** and needs a tick; the server requires the confirmation too.

## 7. Screen design standards

- **Cards:** white card, thin neutral border, a colored edge on the left, tinted icon chip; color means state (green fine, amber needs attention, red problem, blue information). Same language on every dashboard and list.
- **Highlight blocks:** soft blue `#eef4fa`, 3px navy left edge, no shadow; used sparingly (KPI header, System KPIs, section headers).
- **Module tabs:** segmented pill, navy active tab (`.civic-module-tab`). Procurement pages use the civic shell (`layouts/procurement`); other pages hide the Procurement tabs with `@section('hide-module-tabs')`.
- **Density:** root font 14px in the procurement shell, compact inputs (about 2.35rem), sidebar 15rem.
- **Forms:** numbered section cards, one calm field style, sticky action bar; field values are normal weight, labels bold.
- **Account menu:** the round user icon opens name, email, role, school, School Settings, User Management (master), Sign out.
- **Flash messages:** one per page; a page that prints its own declares `@section('flash-handled')`.
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
