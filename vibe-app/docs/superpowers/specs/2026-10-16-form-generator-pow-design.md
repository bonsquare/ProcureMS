# Form Generator and the Program of Works (POW): design

Date: 2026-10-16. Status: design approved in chat by the owner; this written spec awaits the owner's review.

## Goal

A new page under the SOB, the **Form Generator**, makes official forms from the school's SOB. The first form is the **Program of Works (POW)**: the owner picks an SOB (quarter), a Program and an Activity, and the system fills a POW from the SOB items of that Program and Activity. The owner adds the other dates, manpower, equipment and signatures, saves it, and prints it. Other forms (documents the owner will supply later) are added to the same page.

## Evidence

- Workbook reviewed read-only: `Program of Works Lubas.xlsx` (one sheet, several POWs stacked, one per project). Each POW has: letterhead (Republic of the Philippines, Department of Education, Region XII, Division, school, school address); title "PROGRAM OF WORKS" and "MOOE <quarter> <year>"; an information block (School, School Address, School ID, Region, Division, Project Title; Date, Budget Allocation, Completion Date, Date Started, Date of Completion, Minimum Required Manpower, Minimum Required Equipment); a table Item No. / Item Description / Unit / Quantity / Unit Cost / Total under "A. Materials:"; "TOTAL MATERIALS COST"; "B. Labor Cost:"; "C. Total Project Cost"; signatures: Prepared by (School Head), Recommending Approval (DepEd Project Engineer), APPROVED (Division Engineer), and in one POW a second APPROVED (Schools Division Superintendent). Total = Quantity x Unit Cost; Total Project Cost = Materials + Labor and equals the Budget Allocation. "Completion Date" and "Date of Completion" appear twice in the sheet; the form keeps one.
- Existing code to reuse: `SobPlan`, `SobItem` (`aip_activity_id`, `particulars`, `frequency`, `quantity`, `unit`, `unit_cost`, `amount`, `is_extra`, `note`), `SobService::grouped()`, the Planning SOB panel (`resources/views/planning.blade.php`), the shared print engine (`partials/print-clean`, `partials/official-toolbar`, `OfficialDocument::logos()`), School Settings (school address, School ID, DepEd School ID), `planning.manage` permission, organization scope (`BelongsToOrganization`).

## Decisions taken in chat

- Materials = **all items** of the chosen Program and Activity, each with a checkbox to leave one out.
- Every POW is **saved** (list, open, edit, print, delete).
- Paper: **Folio** (210 x 330 mm) by default; the shared Paper Size toolbar lets the user switch to A4 when the POW is short. The POW is not locked to A4.
- No logos (the workbook has none); not decided against, just not asked for.

## Data (additive migrations; nothing existing is changed)

- **`pow_plans`**: `id`, `organization_id`, `school_id`, `sob_plan_id` (foreign key, restrict), `aip_activity_id` (nullable: null means all activities of the program), `program` (string 255, a snapshot), `project_title` (string 255, default the program), `school_address` (string 255, nullable; default from the school profile), `pow_date`, `date_started`, `date_completed` (all nullable dates), `manpower` and `equipment` (text, nullable), `labor_cost` (decimal 15,2, default 0), `signatories` (json: `prepared_by`, `recommended_by`, `approved_by`, `division_superintendent`, each `{name, position}`; the last one optional), `created_by`, timestamps.
- **`pow_items`**: `id`, `organization_id`, `pow_plan_id` (cascade delete), `sob_item_id` (nullable, null on delete), `description`, `unit`, `quantity` (decimal 12,2), `unit_cost` (decimal 15,2), `amount` (decimal 15,2, stored as quantity x unit cost), `sort` (integer).
- Models `PowPlan` and `PowItem` use `BelongsToOrganization`. A POW keeps its own copy (snapshot) of the lines, so a later change to the SOB does not change a saved POW.
- **Total Materials** = sum of `pow_items.amount`; **Total Project Cost** = Total Materials + `labor_cost`; **Budget Allocation** = Total Project Cost (computed, not stored).

## Rules

- A school user sees and changes only their own school's POWs (organization and school scope as in the SOB). Permission `planning.manage` to create, edit and delete; anyone who can open Planning can open and print.
- A POW is made from an SOB of the school: the SOB may be a draft or approved. The Program list is the programs of the SOB's items; the Activity list is the activities of the chosen program that have items in that SOB, plus "All activities of the program".
- **Materials** come from the items of the chosen Program and Activity that are not "other lines": description = particulars (and the item note is not copied), unit = unit, quantity = frequency x quantity, unit cost = unit cost, amount computed on the server. Each line has a checkbox (checked by default); an unchecked line is not saved. The rows can also be edited on the POW page after saving (description, unit, quantity, unit cost; add a line; remove a line).
- **Labor** is the sum of the "other lines" (for example 35% Labor) of the same Program and Activity; it is only a starting value and can be changed.
- Quantity must be greater than 0, unit cost 0 or more; a POW needs at least one material line or a labor amount. Dates are optional; Date Started must not be after Date of Completion when both are given.
- **Signatures:** Prepared by (default: the school's School Head from the staff list), Recommending Approval, Approved; a fourth slot (Schools Division Superintendent) is optional and printed only when filled. Name and position are typed; for the three division slots the form is prefilled with the values of the school's latest POW. They are saved as a snapshot in the POW.
- Deleting a POW needs `planning.manage`. Writing an audit entry `pow_created`, `pow_updated`, `pow_deleted`.

## Screens

- **Form Generator** (`/planning/forms`, route `planning.forms`): opened from a **Form Generator** button in the SOB panel of Planning. Top: filters Fiscal Year, SOB (quarter), Program, Activity. Under them, the list of forms (a registry in code, `App\Support\GeneratedForms`, with one entry for now: Program of Works) and, for the chosen form, the form's fields. For the POW: the materials checklist with amounts and the live totals, Labor, Project Title, dates, manpower, equipment, signatures, and **Create POW**. Under it the list of saved POWs of the school (project title, quarter, Total Project Cost, date; Open, Print, Delete), newest first.
- **POW page** (`/planning/pow/{pow}`, `planning.pow.show`): the same fields for editing a saved POW, with Save, Print and Delete, and the docked preview of the official form like the other generating pages (29.2).
- Routes: `planning.forms` (GET), `planning.pow.store` (POST), `planning.pow.show` (GET), `planning.pow.update` (PUT), `planning.pow.destroy` (DELETE), `planning.pow.print` (GET `/planning/pow/{pow}/print`).

## Print (`resources/views/pow-print.blade.php`)

- Through the shared print engine (`data-official-page`, paper toolbar, no `@page` of its own); default paper `folio`, portrait, 10 mm margins; Arial.
- Layout as the workbook: centered letterhead lines (Republic of the Philippines; Department of Education; Region; Division; school name; school address); "PROGRAM OF WORKS"; "MOOE <QUARTER> <YEAR>" (the SOB's fund and quarter); the information block in two columns (School, School Address, School ID, Region, Division, Project Title | Date, Budget Allocation, Date Started, Date of Completion, Minimum Required Manpower, Minimum Required Equipment); the table (Item No., Item Description, Unit, Quantity, Unit Cost, Total) under "A. Materials:" with blank numbered rows left out; "TOTAL MATERIALS COST - - - -"; "B. Labor Cost:"; "C. Total Project Cost"; the signatures (Prepared by | Recommending Approval, then APPROVED, then the optional second APPROVED), names in bold and underlined.
- School ID: the DepEd School ID of the school profile, else the system School ID. Region and division from the school profile (as the SOB print). Amounts with two decimals. Long tables flow page by page with the shared pagination (`data-paginate`).

## Not in this change

- Logos on the POW; a POW without an SOB; numbering of POWs; approval workflow; attaching the POW to a Purchase Request; the other forms (they are added one by one when the owner supplies them).

## Tests (each fails first)

- Materials: the lines come from the chosen Program and Activity only; "All activities" takes every activity of the program; other lines are not materials; quantity = frequency x quantity; unchecked lines are not saved; labor starts from the other lines.
- Totals: Total Materials, Total Project Cost and Budget Allocation add up on the server and in the print.
- Validation: quantity, unit cost, dates, a POW with nothing in it.
- Permission: `planning.manage` to create, edit and delete; others can open and print; guest redirected.
- Isolation: another school's or organization's SOB and POW give 403 or 404 and never list.
- Snapshot: changing the SOB afterwards leaves a saved POW unchanged.
- Print: shared toolbar, default paper folio, letterhead, the information block, the totals, the signatures (the optional one only when filled), the DepEd School ID.
- Page: the Form Generator opens, lists the saved POWs, and the SOB panel of Planning links to it.
