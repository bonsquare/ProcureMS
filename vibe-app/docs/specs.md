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

- Controls are UI only and sit outside the document: Close, Paper Size, Custom width and height, Orientation, Zoom, Print / Save as PDF, and a short context label (for example `RFQ · RFQ-2026-001 · PR-2026-011`). They never print.
- The preview shows a grey desk, a page shadow, and page guides. All of it disappears in print: white background, black text, official borders, no shadow, no controls.
- Source: `partials/official-toolbar.blade.php` (markup; one copy for every document, do not write document-specific toolbars) and `partials/print-clean.blade.php` (styles and behavior). The owner's earlier "Close and Print only" toolbar is **not** in force; the paper-size toolbar was restored on 2026-10-09.
- The browser print is authoritative.

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
- The NTP comes before the PO because the PO says "within N days of receipt of the NTP". The PO picks up the delivery terms (days, delivery term, payment term, mode, fund) from the NTP when it is not given its own.
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
- Tests: `php artisan test` (all pass at the time of writing: 121).

## 9. Station transfer

- One user manages one school (the Official Station). Users cannot add other users. The subscription is personal (`subscriptions.user_id`) and goes with the user.
- A user asks for a transfer in School Settings, **Station Transfer** tab (school users only): pick a **vacant** registered school (no active user) or "My school isn't listed" with the school's details, plus a reason. One pending request at a time; it can be cancelled while pending.
- The master user decides under the user menu, Transfer requests (`/transfer-requests`). Approving, in one database transaction: creates the school if it is new (no subscription of its own), ends the user's old employee record (kept as history, `school_staff.ended_at`), creates a new employee record at the new school with no roles, and moves the account (`organization_id`, `school_id`). The user's role, username, name and subscription do not change. The school they leave keeps all its data and becomes vacant.
- After approval the user must confirm the new station (`/station-confirm`) before using any other page; they can only confirm or log out. Confirmation is stored on the request (`confirmed_at`).
- School data is never copied or moved: records are scoped by the user's organization, so changing the account is enough.
- Tests: `tests/Feature/StationTransferTest.php`. Design and plan: `docs/superpowers/specs/2026-10-09-station-transfer-design.md`, `docs/superpowers/plans/2026-10-09-station-transfer.md`.
