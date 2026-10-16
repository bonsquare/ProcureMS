# Cash Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Print the Report of Checks Issued, the Checks and Advices to Debit Account Disbursement Record and the Cash Disbursement Register for a school and period from the Cash transactions already in the system.

**Architecture:** A live `CashReportService` reads paid `liquidation_reports` and `cancelled_checks` for one school and period and adds typed `cash_fund_receipts` (opening balance, NCA, cash advance) for the balance. A `CashReportController` serves a Reports tab on the Cash page, the Fund received entries and three print pages on the shared print engine. Nothing is stored per report.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit, SQLite. Print pages follow `resources/views/aip-print.blade.php` (shared engine, `data-official-page`).

**Spec:** `docs/superpowers/specs/2026-10-16-cash-reports-design.md`. Source workbooks: `11REPORT OF CHECKS ISSUED.xlsx`, `CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD.xlsx`, `CASH DISBURSEMENT REGISTER.xlsx` (in Downloads).

## Global Constraints

- Strict minimal change: additive migration only; no existing table, route, view behavior or test changes except the Cash page gaining one tab.
- Tenant isolation: every query is scoped to the user's schools exactly as `FinanceController::schoolIds()`; a school's report never shows another school's rows; Fund received rows carry `organization_id` and `school_id`.
- Permission: Reports tab and prints need `cash.pay`; Fund received create, update and delete need `cash.pay`; every change writes an `AuditLog` row.
- Official documents (docs/specs.md section 1): shared print engine, no own `@page`, no own toolbar, `data-official-page` with `data-doc`, `data-paper="longbond"`, `data-orientation="landscape"`, `data-margin="10"`; Arial; black and white; the template columns and captions are fixed; no `undefined`/`null`/`NaN`; a report with no payments still prints.
- Money: peso amounts with thousands separators and two decimals, right aligned; a cancelled check line has no amount (blank, not 0.00).
- Project rules: Pint (`vendor/bin/pint --dirty --format agent`) after PHP changes; narrowest tests per task; the form design standard of docs/specs.md section 7 for the new Reports tab forms; spec edits to `docs/specs.md` only in the final task (standing permission 2026-10-16).
- Cancelled check numbers come from `cancelled_checks.check_number`; a paid line's check number is `liquidation_reports.payment_reference`.

## Review Focus

- A school with no payment in the period: the three reports print with the header and the opening balance, no error. (Task 2 and Task 4 tests.)
- A check cancelled and paid again in the same period: both the cancelled line and the new paid line appear, in date then check-number order. (Task 2.)
- An ORS paid by ADA: it must not appear as a check; it goes in the ADA Issued column only. (Task 2 and Task 5.)
- A payee or particulars that is very long, and more than 10 expense accounts in the period: text wraps, the register continues in a second column group, nothing overflows. (Task 6.)
- A user of school A opening school B's report URL or fund entry: 403/404. (Task 3.)

## File Structure

- `database/migrations/2026_10_16_230000_create_cash_fund_receipts_table.php` (new): the table.
- `app/Models/CashFundReceipt.php`, `database/factories/CashFundReceiptFactory.php` (new): model and factory.
- `app/Services/CashReportService.php` (new): all report figures; one responsibility, no HTTP.
- `app/Http/Controllers/CashReportController.php` (new): Reports tab data, Fund received CRUD, three print actions.
- `routes/web.php` (modify): the routes below, inside the existing authenticated group next to the `cash.*` routes.
- `resources/views/finance.blade.php` (modify): add the `reports` tab to the Cash tab list and include `partials/cash-reports-tab`.
- `resources/views/partials/cash-reports-tab.blade.php` (new): period picker, Fund received panel, three report buttons.
- `resources/views/cash-report-roci.blade.php`, `cash-report-checks-advices.blade.php`, `cash-report-register.blade.php`, `partials/cash-report-header.blade.php` (new): the print pages and the shared header block.
- `tests/Feature/CashReportServiceTest.php`, `CashFundReceiptTest.php`, `CashReportPrintTest.php` (new).

## Routes (all under `middleware('permission:cash.pay')`)

| Name | Method and path | Action |
|---|---|---|
| `cash.reports` | GET `/cash?tab=reports&school_id=&from=&to=` | existing `FinanceController::cash`, tab `reports` |
| `cash.fund.store` | POST `/cash/fund-receipts` | `CashReportController::storeReceipt` |
| `cash.fund.update` | PUT `/cash/fund-receipts/{cashFundReceipt}` | `updateReceipt` |
| `cash.fund.destroy` | DELETE `/cash/fund-receipts/{cashFundReceipt}` | `destroyReceipt` |
| `cash.report.roci` | GET `/cash/reports/roci` | `roci` |
| `cash.report.checks` | GET `/cash/reports/checks-advices` | `checksAdvices` |
| `cash.report.register` | GET `/cash/reports/register` | `register` |

The three print routes take query `school_id`, `from`, `to` (dates), `report_no`, `sheet_no`.

---

### Task 1: Fund received table, model and entries

**Files:**
- Create: `database/migrations/2026_10_16_230000_create_cash_fund_receipts_table.php`, `app/Models/CashFundReceipt.php`, `database/factories/CashFundReceiptFactory.php`, `app/Http/Controllers/CashReportController.php` (receipt actions only)
- Modify: `routes/web.php` (the three `cash.fund.*` routes)
- Test: `tests/Feature/CashFundReceiptTest.php`

**Interfaces:**
- Produces: model `App\Models\CashFundReceipt` with fillable `organization_id, school_id, received_at, kind, fund_cluster, bank_account_number, amount, reference, note, created_by`; casts `received_at` date, `amount` decimal:2; constants `KINDS = ['opening_balance' => 'Opening balance', 'nca' => 'NCA received', 'cash_advance' => 'Cash advance']`; `school()` relation; scope `forSchools($ids)`.
- Produces: `CashReportController::storeReceipt/updateReceipt/destroyReceipt(Request, ...)`: redirect to `route('cash', ['tab' => 'reports', 'school_id' => ...])` with `success`; each writes an `AuditLog` (`cash_fund_received`, `cash_fund_updated`, `cash_fund_deleted`).

- [ ] **Step 1: Write the failing tests** in `CashFundReceiptTest`: `test_a_cash_user_adds_a_fund_receipt_and_it_is_audited` (POST with kind `opening_balance`, `amount` 181500, `received_at` 2026-07-01 → row exists with the user's `organization_id` and `school_id`, audit action `cash_fund_received`); `test_a_user_without_cash_permission_is_refused` (403); `test_another_schools_receipt_cannot_be_changed_or_deleted` (PUT and DELETE on school B's row as school A user → 403 or 404, row unchanged); `test_invalid_amount_or_kind_is_refused` (amount `-5`, kind `loan` → session errors, no row).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/CashFundReceiptTest.php` → FAIL (route/table missing).
- [ ] **Step 3: Implement** the migration (`foreignId` organization and school constrained, `date received_at`, `string kind`, `string fund_cluster` default `General Fund`, nullable `bank_account_number`, `decimal amount 14,2`, nullable `reference`, nullable `note`, `foreignId created_by` constrained users, timestamps, index on `school_id, received_at`), the model and factory, the three controller actions (validate: `school_id` required exists among the user's schools, `received_at` required date, `kind` in `CashFundReceipt::KINDS`, `amount` numeric min 0.01, other fields nullable strings) and the routes. School scope uses the same rule as `FinanceController::schoolIds()`.
- [ ] **Step 4: Run** the test file → PASS; run `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** `git add` the files above; `git commit -m "feat: cash fund received entries"`.

### Task 2: CashReportService

**Files:**
- Create: `app/Services/CashReportService.php`
- Test: `tests/Feature/CashReportServiceTest.php`

**Interfaces:**
- Consumes: `CashFundReceipt` (Task 1), `LiquidationReport`, `CancelledCheck`, `DvJournalLine`, `BudgetAllocation::account`.
- Produces: `CashReportService::build(School $school, CarbonInterface $from, CarbonInterface $to): array` with keys: `opening` (float: opening balance and receipts dated before `from`, minus payments before `from`), `receipts` (Collection of `CashFundReceipt` inside the period), `lines` (Collection, ordered by `date` then `check_no`, each `['date' => Carbon, 'released' => Carbon|null, 'check_no' => string, 'dv_no' => string, 'ors_no' => string, 'responsibility_center' => string, 'payee' => string, 'uacs_code' => string, 'nature' => string, 'amount' => float|null, 'mode' => 'check'|'ada', 'cancelled' => bool, 'balance' => float]`), `total_paid` (float), `total_received` (float), `ending` (float), `accounts` (array code => title, only accounts paid in the period, ordered by code).
- The line date is `check_date ?? paid_at`; `released` is `paid_at`. `mode` is `ada` when `payment_mode === 'ADA'`, else `check`. UACS code and nature come from `chargedLine()?->account` (`code`, `title`); when missing, from the DV journal line with a debit. `cancelled` lines come from `cancelled_checks` with `paid_at` in the period: `check_no` = `check_number`, `payee` = `'Cancelled Check'`, `amount` null, no balance change.

- [ ] **Step 1: Write the failing tests** in `CashReportServiceTest` (reuse the fixture style of `CancelledCheckTest::paidOrs`): `test_paid_dvs_of_the_period_become_check_lines_with_uacs_and_nature` (assert fields and `total_paid`); `test_the_running_balance_is_opening_plus_receipts_minus_payments` (receipt 181500 opening; two payments 1000 and 500 → balances 180500 and 180000, `ending` 180000); `test_a_cancelled_check_is_a_zero_line_and_the_new_payment_is_a_second_line` (cancel then repay: both appear in order, cancelled `amount` null, balance unchanged by it); `test_ada_payments_are_marked_ada`; `test_unpaid_and_other_periods_and_other_schools_are_left_out`; `test_the_uacs_falls_back_to_the_dv_debit_line_when_the_budget_line_has_no_account`; `test_an_empty_period_returns_the_opening_balance_and_no_lines`; `test_accounts_lists_only_accounts_paid_in_the_period`.
- [ ] **Step 2: Run** the file → FAIL.
- [ ] **Step 3: Implement** `build()` as described (eager load `budgetAllocation.account`, `procurementRequest.budgetAllocation.account`, `journalLines`); amounts as floats rounded to 2.
- [ ] **Step 4: Run** the file → PASS; Pint.
- [ ] **Step 5: Commit** `feat: cash report service`.

### Task 3: Reports tab and controller data

**Files:**
- Create: `partials/cash-reports-tab.blade.php`
- Modify: `resources/views/finance.blade.php` (add `'reports' => 'Reports'` to the Cash tabs list at line ~23 and its icon entry at ~46, include the partial when `$isCash && $tab === 'reports'`), `app/Http/Controllers/FinanceController.php::cash` (for tab `reports` pass `reportSchools`, the chosen `reportSchool`, `from`, `to`, `receipts` of the period; for other tabs behave exactly as now), `routes/web.php` (the three print routes; actions may return 501 until Tasks 4 to 6)
- Test: `tests/Feature/CashReportPrintTest.php` (access tests only in this task)

**Interfaces:**
- Consumes: `CashFundReceipt` (Task 1), `CashReportService::build` (Task 2).
- Produces: the Reports tab with: school select (only for users who see several schools), `from` and `to` date inputs defaulting to the current quarter, a Fund received list with add (modal or inline form posting to `cash.fund.store`), edit and delete icon buttons (form design standard of section 7), and three buttons linking to the print routes with the chosen school and dates. Counts and the other tabs are unchanged.

- [ ] **Step 1: Write the failing tests** in `CashReportPrintTest`: `test_the_reports_tab_shows_the_period_the_fund_entries_and_three_report_buttons` (GET `route('cash', ['tab' => 'reports'])` as a cash user → see "Fund received", the three route URLs); `test_the_reports_tab_needs_cash_permission` (user without `cash.pay` → 403); `test_a_school_user_cannot_open_another_schools_report` (print routes with the other `school_id` → 403/404); `test_the_existing_cash_tabs_still_work` (unpaid, paid, cancelled return 200).
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the tab, the controller additions and the routes. The tab is only listed for users with `cash.pay`.
- [ ] **Step 4: Run** the file and `tests/Feature/CancelledCheckTest.php`, `FinanceRowBadgesTest.php` → PASS; Pint.
- [ ] **Step 5: Commit** `feat: cash reports tab and fund received panel`.

### Task 4: Report of Checks Issued (Appendix 35) print

**Files:**
- Create: `resources/views/cash-report-roci.blade.php`, `resources/views/partials/cash-report-header.blade.php`
- Modify: `CashReportController::roci`
- Test: `tests/Feature/CashReportPrintTest.php`

**Interfaces:**
- Consumes: `CashReportService::build` (Task 2).
- Produces: `partials/cash-report-header.blade.php` taking `$school`, `$officer` (name, position), `$title`, `$period`, `$reportNo`, `$sheetNo`, `$bank` (bank name and account no. from the school, else from the latest payment), `$fundCluster` (latest payment's `payment_fund_cluster`, else "General Fund"), used by the three prints; the Accountable Officer is the active employee of the school holding `document_role` "Disbursing Officer" (`SchoolStaff::hasRole`), else blank.
- The ROCI page: title REPORT OF CHECKS ISSUED, "Appendix 35" top right, "Period Covered: <from> to <to>", columns **Check Date, Check Serial No., DV/Payroll No., ORS/BURS No., Responsibility Center Code, Payee, UACS Object Code, Nature of Payment, Amount** (two heading rows as in the workbook: Check / Date + Serial No.), a Total row, the Certified-correct and signatures block of the workbook; engine attributes per Global Constraints; uses the shared official toolbar with `context` "Report of Checks Issued".

- [ ] **Step 1: Write the failing tests**: `test_the_roci_prints_header_lines_and_total` (see the school name, "REPORT OF CHECKS ISSUED", "Appendix 35", the check number, DV, ORS, payee, UACS code, formatted amount and the total); `test_a_cancelled_check_prints_as_cancelled_check_with_no_amount`; `test_the_roci_prints_with_no_payments_in_the_period` (200, header present, no "NaN"/"undefined"); `test_ada_lines_are_not_in_the_roci` is NOT required (ROCI lists checks only: assert an ADA payment is absent).
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the view, header partial and controller action (validate `school_id`, `from`, `to`; same school guard as Task 3).
- [ ] **Step 4: Run** the file → PASS; also `OfficialPrintTest` → PASS; Pint.
- [ ] **Step 5: Commit** `feat: report of checks issued print`.

### Task 5: Checks and Advices to Debit Account Disbursement Record print

**Files:**
- Create: `resources/views/cash-report-checks-advices.blade.php`
- Modify: `CashReportController::checksAdvices`
- Test: `tests/Feature/CashReportPrintTest.php`

**Interfaces:**
- Consumes: Task 2 service, Task 4 header partial.
- Produces: the page with columns of the workbook sheet `chck_advice`: **NCA/DS/DV/Payroll No.** (DV no.) and **Date** (DV date), **Check/ADA Serial No.** split into Check and ADA, **Date**, **Date Released**, **Payee**, **UACS Object Code**, **Nature of Payment**, **Amount** with sub-columns **NCA Received Deposit Mode**, **Check Issued**, **ADA Issued**, **NCA / Bank Balance**. A first row shows the opening balance; a receipt in the period appears as a row with its amount under NCA Received and the balance; a check line puts its amount under Check Issued, an ADA line under ADA Issued; the balance column is the line's `balance`; the last row is the totals.

- [ ] **Step 1: Write the failing tests**: `test_the_record_shows_opening_balance_receipts_checks_ada_and_running_balance` (opening 181,500; one check 1,000; one ADA 500; assert Check Issued and ADA Issued cells and balances 180,500 and 180,000); `test_the_record_prints_with_no_payments`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the view and action.
- [ ] **Step 4: Run** the file → PASS; Pint.
- [ ] **Step 5: Commit** `feat: checks and advices disbursement record print`.

### Task 6: Cash Disbursement Register print

**Files:**
- Create: `resources/views/cash-report-register.blade.php`
- Modify: `CashReportController::register`
- Test: `tests/Feature/CashReportPrintTest.php`

**Interfaces:**
- Consumes: Task 2 service (`lines`, `accounts`, `opening`), Task 4 header partial.
- Produces: the page of sheet `CASH_DISBRSMNT`: columns **Date, Reference** (check serial no.), **Particulars** (payee), **Working Fund** (**Cash Advance**, **Payments**, **Balance**), then **Breakdown of Payments**: one column per entry of `accounts`, headed by the account title with its UACS code under it. Row one is "Beginning Balance" with the opening balance as Cash Advance and Balance; each payment row puts its amount in Payments and in its account's column; cancelled lines show the reference and "Cancelled Check" with no amounts; a Total row sums Payments and each account column. When `accounts` has more than 8 entries, the account columns continue in a second table below (same rows: Date, Reference, then the remaining accounts) so nothing overflows.

- [ ] **Step 1: Write the failing tests**: `test_the_register_has_a_column_per_paid_account_with_its_uacs_code` (two payments on two accounts → two headed columns, amounts in the right ones, totals); `test_the_register_balance_follows_the_payments`; `test_more_than_eight_accounts_continue_in_a_second_table`; `test_a_very_long_payee_wraps` (assert the payee is in the HTML in a cell without `white-space:nowrap`); `test_the_register_prints_with_no_payments`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the view and action.
- [ ] **Step 4: Run** the file → PASS; run the whole `tests/Feature/CashReportPrintTest.php` plus `OfficialPrintTest`; Pint.
- [ ] **Step 5: Commit** `feat: cash disbursement register print`.

### Task 7: Specs, status and full run

**Files:**
- Modify: `docs/specs.md` (new section 39, Cash reports, summarising the design: three reports, Fund received, the rules, tests), `vibe-app/CLAUDE.md` (status line)
- Test: whole suite

- [ ] **Step 1: Run** `php artisan test --compact` → all pass.
- [ ] **Step 2: Edit** specs.md section 39 and the CLAUDE.md status list (standing permission of 2026-10-16 covers specs.md when committing).
- [ ] **Step 3: Commit** `docs: cash reports in specs and CLAUDE.md`. Push and deploy only when the owner says so.

## Self-review notes

- Spec coverage: Fund received (Task 1), service with checks, ADA, cancelled, balance, accounts and UACS fallback (Task 2), Reports tab (Task 3), three prints with header, Disbursing Officer and Report/Sheet No. (Tasks 4 to 6), permission and isolation (Tasks 1 and 3), no Excel, specs (Task 7). Open spec questions are decided here: UACS fallback is the DV debit line; the register uses a second table beyond 8 account columns.
- Names used across tasks: `CashFundReceipt`, `CashReportService::build`, route names `cash.fund.*`, `cash.report.roci|checks|register`, `partials/cash-report-header`.
