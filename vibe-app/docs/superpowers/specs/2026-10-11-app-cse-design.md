# APP-CSE (Annual Procurement Plan - Common Supplies and Equipment) - design

Date: 2026-10-11. Status: awaiting the owner's review. Source form: the owner's `APP.xlsx` ("APP-CSE 2026 FORM", Procurement Service template, filled in for Lubas Elementary School). The printed form replicates that file.

## Purpose

The system has an APP (generated from the approved School Operating Budgets) but no official APP form. The official form for common supplies and equipment is the Procurement Service (PS) APP-CSE: a catalog of 368 PS items, each with a quantity for every month of the year, a price catalogue price and totals, then the school's own items not available at PS, totals A to G, a warranty paragraph and four signatures. The owner wants it made in the app and printed as the official document.

## What the owner decided

- The quantities are **entered in the app** (not imported from Excel, not derived from the existing APP/SOB).
- The catalog (Part I) is loaded **from the owner's file** as reference data, with the 2026 price catalogue prices from that file.
- The print **always contains every catalog row** (zeros where unused), then Part II, like the official file. There is no "used items only" mode.
- The totals are calculated **as their labels say**, not by copying the file's formulas (its A and G.1 cell references are broken).
- The existing APP (from the SOB) is not changed.

## Out of scope

- Importing a filled APP-CSE Excel, exporting to Excel, or submitting to the PS portal.
- A screen to edit the catalog (a new year's catalog is loaded by a command).
- Changing `docs/specs.md` (protected; only if the owner asks, with the exact change shown first).

## Data

All schema changes are additive.

- `cse_catalog_items` (reference data, shared by every school, not tenant-scoped): `id`, `catalog_year` (2026), `category`, `code` (e.g. `10191509-IN-A01`), `description`, `unit`, `price` (decimal, nullable: 17 items have none in the file), `sort_order`. Unique on (`catalog_year`, `sort_order`).
- `app_cse_plans` (tenant data: `organization_id`, `school_id`): `fiscal_year`, `catalog_year` (the catalog the plan uses, fixed when the plan is created: the newest loaded catalog year at or below the fiscal year, else the newest loaded; creating a plan needs a loaded catalog), `status` (`draft` or `approved`), `approved_at`, `created_by`; header fields `office_name`, `region`, `address`, `account_code`, `organization_type`, `contact_person`, `contact_position`, `contact_email`, `contact_phone`; `approved_budget` (line E, decimal, nullable) and `approved_budget_words` (text, nullable); `date_prepared` (date, nullable); `agency_head_name`, `agency_head_position`; `signatories` (JSON: snapshots of the three staff signers, same shape as the other forms). Unique on (`school_id`, `fiscal_year`).
- `app_cse_items` (tenant data): `app_cse_plan_id`, `cse_catalog_item_id` (null = a Part II item), `category` (Part II group label), `description`, `unit`, `unit_price` (a snapshot, so a later catalog update never changes a plan), `m1` to `m12` (decimal, default 0), `sort_order`.
- The amounts and totals are always computed from the items; none are stored.

### Loading the catalog

The 368 items are extracted once from the owner's workbook into `database/data/cse-catalog-2026.json` (category, code, description, unit, price, order) and loaded by `php artisan cse:import-catalog <file.json> --year=2026`, which refuses a year that is already loaded. A migration runs it for 2026 so the deploy fills the catalog. A new year is another file and the same command.

## Calculations (per plan, from the items)

- Quarter quantity: Q1 = m1+m2+m3, Q2 = m4+m5+m6, Q3 = m7+m8+m9, Q4 = m10+m11+m12. Quarter amount = quarter quantity x unit price.
- Total quantity for the year = Q1+Q2+Q3+Q4. Total amount for the year = total quantity x unit price.
- Line **A** = total of every item's total amount (Part I and Part II). **B** = 10% of A (inflation). **C** = 5% of A (transport and freight). **D** = A + B + C. **E** = the typed approved budget.
- **F, monthly cash requirements:** **G.1** (available at PS) and **G.2** (other items) are each given per quarter as the sum of that quarter's amounts over Part I and over Part II; the total row is G.1 + G.2 per quarter and for the year.
- Amounts are shown with two decimals; quantities without trailing zeros.

## Entry (Planning page)

- A new **APP-CSE** block inside the existing APP tab of Planning (below the APP, so the five-step flow cards stay as they are) lists the school's plans by fiscal year and creates one for a chosen year. A school has one plan per year. Header defaults are filled in from the school profile and School Settings when the plan is created and stay editable.
- The plan page (`/planning/app-cse/{plan}`): the header form; the signatories ("Prepared by", "Noted", "Certified funds available" picked from active staff with the same "Default (Name)" picker, plus a typed "Approved by" name and position for the Head of Office/Agency, defaulting to the agency head from School Settings); "Add from the catalog" (search by description or code, grouped by category); "Add my own item" for Part II (category, description, unit, price); a row per added item with the 12 month boxes and the computed quarters and totals; the totals block A to G with the typed approved budget; "Approve".
- A catalog item with no price in the catalog asks for its price when it is added. Otherwise the price comes from the catalog and is not typed.
- Editing, adding and removing items, and the header and signatories, are allowed while the plan is a draft. **Approve** locks the plan (nothing can be changed or removed); it can only be approved when it has at least one item with a quantity above zero.

## Official print (`/planning/app-cse/{plan}/print`)

Built on the shared print engine (paper-size toolbar, `data-official-page`, normal paper and orientation as the default). Landscape long bond. Contents, in the form's own wording:

1. Title lines: "APP-CSE 2020 FORM - 10 September 2019" and "ANNUAL PROCUREMENT PLAN-COMMON SUPPLIES AND EQUIPMENT (APP-CSE) {fiscal year} FORM".
2. The form's own introduction, instructions 1 to 10 and closing note, word for word as in the owner's file (spaces, typos and the years in it included: "APP-CSE 2024 template", "FY 2020 ... October 31, 2025"), with the form's emphasis (instruction 4 bold and underlined, the note's circular and date in bold italic). The owner corrected an earlier decision to leave them out: they are part of the official document.
3. The header block: Department/Bureau/Office, Region, Address, Agency Account Code, Organization Type, Contact Person, Position, E-mail, Telephone/Mobile Nos.
4. The table headings: "Item & Specifications", "Unit of Measure", "Monthly Quantity Requirement" over Jan, Feb, Mar, Q1, Q1 AMOUNT, April, May, June, Q2, Q2 AMOUNT, July, Aug, Sept, Q3, Q3 AMOUNT, Oct, Nov, Dec, Q4, Q4 AMOUNT, then "Total Quantity for the year", "Price Catalogue", "Total Amount for the year". Headings repeat on every page.
5. "PART I. AVAILABLE AT PROCUREMENT SERVICE STORES": every catalog row of the plan's catalog year, in the catalog's order under its category headings, numbered, with its code, description and unit. Items the plan has quantities for show them; every other row shows zeros.
6. "PART II. OTHER ITEMS NOT AVALABLE AT PS BUT REGULARLY PURCHASED FROM OTHER SOURCES (Note: Please indicate price of items)": the plan's own items grouped by their category, numbered, with a sub total.
7. The totals block: "A. TOTAL", "B. ADDITIONAL PROVISION FOR INFLATION (10% of TOTAL)", "C. ADDITIONAL PROVISION FOR TRANSPORT AND FREIGHT COST", "D. GRAND TOTAL (A + B+ C)", "E. APPROVED BUDGET BY THE AGENCY HEAD In Figures and Words:", "F. MONTHLY CASH REQUIREMENTS" with "G.1 Available at Procurement Service Stores", "G.2 Other Items not available at PS but regulary purchased from other sources" and "TOTAL MONTHLY CASH REQUIREMENTS"; then "*Agency must put the monthly requirement for air tickets both local and international."
8. The warranty paragraph ("We hereby warrant that the total amount reflected in this Annual Supplies/ Equipment Procurement Plan ... has been included in or is within our approved budget for the year.") and the signature block: "Prepared by:" Property/Supply Officer, "NOTED:" School Head, "Certified Funds Available / Certified Appropriate Funds Available:" Accountant / Local Budget Officer, "Approved by:" Head of Office/Agency, and "Date Prepared:". Signature blocks never split across pages (spec 1.7).

Typography, borders, paper and pagination follow `docs/specs.md` section 1.

**Decided by the owner (2026-10-11): no logos.** `docs/specs.md` section 1.4 puts the agency, division and school logos in the header of every official printed document, but the Procurement Service form in `APP.xlsx` has none. The APP-CSE prints exactly like the Excel form, with no logo header. This is an exception to section 1.4. It is **not yet recorded in `docs/specs.md`**, which is protected: if the owner wants the exception written there, the exact wording is shown to the owner first.

## Safety, permissions, audit

- **Permissions:** `planning.manage` to create, edit, remove items and approve; `planning.view` or `planning.manage` to open and print. Same as the SOB.
- **Isolation:** every plan and item is organization-scoped (`BelongsToOrganization`); the school must be one of the user's schools; another organization's plan is refused. The catalog is shared reference data, read-only to schools.
- **Fiscal year:** creating or changing a plan of a closed fiscal year is refused (`FiscalYearService::assertOpen`).
- **Signers:** only active staff of the plan's school (the same validation as the other forms); the typed head of office is plain text.
- **Audit:** create, header and signatories changes, item add/edit/remove and approval each write an audit log entry. When the school has an AIP for that fiscal year, creation and approval are also recorded as events on that year's master transaction; otherwise none.
- Quantities and prices are validated as numbers of 0 or more with sane maximums; month boxes accept at most two decimals.

## Delivery

One design, built in three stages with a commit after each, tests first:

1. **Data and catalog:** the three tables, models, `AppCseService` (create, add, update, remove, totals), the catalog JSON, the import command and its migration.
2. **Entry:** routes, `AppCseController`, the Planning panel and the plan page with the signatory picker, approval and the lock.
3. **Print:** the official form and its toolbar.

## Testing

- Catalog: the import loads 368 items for 2026 and refuses a second load; the JSON has the codes, units and prices of the owner's file (spot checks).
- Calculations: a plan with known quantities gives the exact quarter quantities and amounts, A, B (10%), C (5%), D and the G.1/G.2/F figures.
- Entry: create one plan per school and year; add, update and remove items; a catalog item without a price needs one; approval needs a quantity; an approved plan refuses every change; a closed fiscal year, a missing permission and another organization's plan are refused; signers from another school are refused.
- Print: contains the form's wording above, every catalog row (checked by count), Part II grouped with a sub total, the totals block with the right figures, the signature block with the chosen names, and the header fields.
- Existing planning, signatory and print tests keep passing; the full suite is run before each commit.
