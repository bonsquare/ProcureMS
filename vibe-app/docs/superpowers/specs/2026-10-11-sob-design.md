# School Operating Budget (SOB) replaces the PPMP: design

Date: 2026-10-11. Status: design approved in chat, written spec awaiting review.

## Goal

The PPMP is removed from the planning flow. A **School Operating Budget (SOB)**, one per quarter, takes its place: the school picks the AIP activities that have an actual budget in that quarter, adds their line items, and approves it. The approved SOB creates the quarter's Budget allotments and feeds the APP. The flow becomes **SIP -> AIP -> SOB -> APP -> Purchase Request**.

## Evidence

- Template reviewed read-only: `School Operating Budget.xlsx` (Lubas Elementary School). Title "SCHOOL OPERATING BUDGET", "FY 2026 SCHOOL MAINTENANCE AND OTHER OPERATING EXPENSES (MOOE)", one quarter section ("1ST QUARTER"). Columns: PPAs/Expenditures/Item, Particulars, Frequency, Quantity, Unit of Measure, Unit Cost, Amount (frequency x quantity x unit cost; the sample amounts, for example 3 x 1 x 3000 = 9000, agree). Line items are grouped under pillar headings (ACCESS, QUALITY, EQUITY, RESILIENCY & WELL-BEING, GOVERNANCE) with a Sub-Total each, then GRAND TOTAL (181,500 in the sample), then "Summary per object of expenditure" (Supplies and Materials, Repair and Maintenance, Electric Bills, Internet, and so on) with its own grand total, then three signatories: Prepared by (School Principal), Recommending Approval (Chief, SGOD), Approved (Schools Division Superintendent). Column B of a line names its object of expenditure ("School and Office Supplies").
- Today (code read): `PpmpPlan`/`PpmpItem` are created from an approved AIP (`PlanningController::storePpmp`, routes `planning.ppmp.*`); `generateApp` builds `AppItem` rows from approved PPMP items (`app_items.ppmp_item_id` is NOT NULL, unique with `app_plan_id`); `ProcurementRequestItem.app_item_id` links PR lines to APP items through `AppItemLinkService`, which checks remaining quantity and cost. `AipController::approve` no longer creates Budget allotments (decided 2026-10-11). `BudgetAllocation` has `aip_id`, `source_of_fund`, `chart_of_account_id`, `program`, `q1_amount` to `q4_amount`, `amount`, `uacs_code`, `particulars`, `budget_ref_no`.

## Decisions taken in chat

- Flow: SIP -> AIP -> SOB -> APP -> PR. The APP is kept and is built from approved SOB items; PR lines keep linking to APP items unchanged.
- One SOB per quarter (Q1 to Q4) of a fiscal year, with its own items, totals, summary, approval and print.
- Approving an SOB **creates or updates Budget allotments** for that quarter.
- The PPMP is removed from the screens and routes. Its tables and data stay untouched (additive migrations only).
- The AIP quarter amount is a **warning**, not a block, when an SOB spends more on an activity than the AIP gives that quarter.
- `docs/specs.md` and `CLAUDE.md` mention the PPMP in the flow. They are protected: the exact new text is shown to the owner at the end and added only on an explicit yes.

## Data

- **`sob_plans`**: `id`, `organization_id`, `school_id`, `aip_id`, `master_transaction_id` (nullable), `fiscal_year`, `quarter` (1 to 4), `fund_source` (string, default `MOOE`), `status` (`draft` or `approved`, default `draft`), `prepared_by_name`, `prepared_by_position`, `recommended_by_name`, `recommended_by_position`, `approved_by_name`, `approved_by_position` (all nullable), `approved_at` (nullable), `created_by`, timestamps. Unique on (`school_id`, `fiscal_year`, `quarter`, `fund_source`).
- **`sob_items`**: `id`, `organization_id`, `sob_plan_id` (cascade delete), `aip_activity_id` (the AIP activity chosen as the PPA), `chart_of_account_id` (the object of expenditure), `particulars` (string 255), `frequency` (decimal 12,2, default 1), `quantity` (decimal 12,2), `unit` (string 50), `unit_cost` (decimal 15,2), `amount` (decimal 15,2, stored as frequency x quantity x unit cost), timestamps.
- **`app_items`**: `ppmp_item_id` becomes nullable; add nullable `sob_item_id` (foreign key to `sob_items`, cascade delete) with a unique index on (`app_plan_id`, `sob_item_id`). Existing rows are untouched.
- Models `SobPlan` and `SobItem` use `BelongsToOrganization` like the other planning models.
- No existing column or table is dropped or renamed.

## Rules

- A school user sees and changes only their own school's SOBs (organization and school scope as elsewhere). Permission `planning.manage` to create, edit, approve, delete.
- An SOB can be created only for a school and fiscal year that has an **approved AIP** and whose fiscal year is open. One SOB per school, fiscal year, quarter and fund.
- **Choosing the PPAs:** the SOB page lists the AIP's activities (by pillar, program and activity) with each one's AIP amount for this quarter (`q{N}_amount`). The user picks which activities have an actual budget in the quarter and adds line items under them. An activity with an AIP amount of 0 for the quarter can still be chosen.
- **Line item:** account (a chart of accounts entry of the school's organization), particulars (required), frequency (number > 0, default 1), quantity (> 0), unit (required), unit cost (>= 0). The amount is computed on the server; the client shows it live.
- **Warning, not a block:** when the SOB lines under an activity total more than that activity's AIP amount for the quarter, the page shows the activity in amber with both amounts. Saving and approving are not blocked.
- **Edit and delete:** a draft SOB's items and header (fund, signatories) can be edited, and the draft SOB can be deleted. An approved SOB is locked. An SOB whose APP items are drawn on by a Purchase Request cannot be deleted. Approval needs at least one item and an open fiscal year.
- **Approve:** sets `approved`, `approved_at`; links the SOB to the AIP's master transaction (`MasterTransactionService::forAip($aip, $user)`, as the PPMP did when it was created) and records the event (`sob`, `approved`) on it; writes an audit entry `sob_approved`; then creates or updates Budget allotments for the quarter (below).
- **Allotments from an approved SOB:** group the SOB's items by (`fund_source` of the SOB, `chart_of_account_id`, program of the activity's AIP KRA). For each group, find the `BudgetAllocation` with the same `aip_id`, `source_of_fund`, `chart_of_account_id`, `program` and fiscal year; set its `q{N}_amount` to the group's total for this quarter and its `amount` to the sum of its four quarters; if none exists, create it with the other quarters 0, `uacs_code` and `particulars` from the account, `budget_ref_no` from `DocumentNumberService` (`budget_allocation`, `BA`), remarks "From SOB FY {year} Q{N}". A new line takes the other columns as the former AIP approval did (`office` = the school's name, `start_date` and `end_date` = the fiscal year, `created_by` = the approver). An existing line's new total `amount` may not be below its obligated amount (`BudgetService::matrix(...)` row `obligated`): refuse the approval with a message naming the program and fund, and save nothing. The whole approval is one database transaction.
- **Generate APP:** takes the items of the school's approved SOBs for the fiscal year (all quarters). Each SOB item that is not yet in the APP becomes an APP item: `procurement_item` = particulars, `specifications` = the PPA text, `quantity` = frequency x quantity, `unit`, `estimated_unit_cost` = unit cost, `estimated_total_cost` = amount, `fund_source` = the SOB's fund, `sob_item_id` set. Items already generated are not duplicated. Existing PPMP-based APP items keep working. The APP approval event is recorded on the SOB transactions.
- **Deleting an AIP** keeps its guard and also refuses when any SOB exists on it.

## Screens

- **Planning page:** the PPMP card, form and list are removed. In their place an **SOB** panel for the selected school and fiscal year with four quarter cards (Q1 to Q4): status (not started, draft, approved), total, number of lines, and a button (Create, Open). The guided flow and wording elsewhere read SIP -> AIP -> SOB -> APP.
- **SOB page** (`/planning/sob/{sob}`): header (school, FY, quarter, fund, signatories), the AIP activities picker with line items grouped by pillar and program, sub-totals per pillar, grand total, summary per object of expenditure, the over-AIP warnings, and Approve / Delete / Print.
- **Print** (`/planning/sob/{sob}/print`): the official-document print engine (`data-official-page`, paper toolbar, no own `@page`); default paper legal, portrait; layout as the template (header lines, table with the seven columns and pillar headings, sub-totals, grand total, summary per object of expenditure, three signatories). Header logos from `OfficialDocument::logos()`.
- Routes (all `planning.manage` except show and print): `planning.sob.store`, `planning.sob.show`, `planning.sob.update`, `planning.sob.destroy`, `planning.sob.items.store`, `planning.sob.items.update`, `planning.sob.items.destroy`, `planning.sob.approve`, `planning.sob.print`. The `planning.ppmp.*` routes are removed.

## Tests (each fails first)

- SOB: needs an approved AIP and an open fiscal year; one per school, year, quarter and fund; school isolation; permission.
- Items: amount = frequency x quantity x unit cost on the server; validation; the over-AIP amount warns and does not block; edit and delete only while draft; approved is locked.
- Approve: creates allotments per fund, account and program with the quarter amount; four quarters fill one line's Q1 to Q4; re-approval impossible; refused when it would cut an allotment below its obligated amount, saving nothing; one transaction; audit and transaction event.
- Generate APP from approved SOBs: items, quantities, no duplicates, PPMP-based items untouched; PR link tests pass unchanged against SOB-based APP items (quantity and cost checks).
- Delete guards: an SOB drawn on by a PR cannot be deleted; an AIP with an SOB cannot be deleted.
- Print: shared toolbar, three signatories, the summary per object of expenditure, totals.
- PPMP routes are gone (404) and the Planning page has no PPMP card; the PPMP-specific tests are replaced by the SOB tests; the existing legacy-data cases keep passing.

## Out of scope

Importing an SOB from Excel, changing an approved SOB, several funds in one SOB, and migrating old PPMP data into SOBs.

## Documentation

`docs/specs.md` (planning flow, the AIP edit and delete table, section 14) and the Phase 2 and 3 text in `CLAUDE.md` describe the PPMP. They are protected: the exact old and new wording is shown to the owner at the end and added only on an explicit yes.

## Risks

- The allotment grouping uses the AIP KRA's program; an activity whose KRA has no program groups under an empty program (as the AIP approval did before 2026-10-11).
- A school with old PPMP-based APP items and new SOB-based ones in the same APP year mixes both; both are handled by the same APP and PR checks.
- Removing the PPMP screens hides data the school may still want to read; it stays in the database and can be shown again.

## Open points

None blocking.
