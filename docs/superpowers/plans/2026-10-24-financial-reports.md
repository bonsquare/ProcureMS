# Financial Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Trial Balance, General Ledger, Subsidiary Ledger, Income Statement and Balance Sheet under Accounting, computed from DV journals, cash fund receipts and an opening-balance table.

**Architecture:** `App\Services\GeneralLedgerService` builds one list of postings on the fly from three sources; each report is a method over that list. One controller and one Blade page (tabs via `?report=`) render them. No second ledger is stored.

**Tech Stack:** Laravel (all code under `vibe-app/`), Blade, PHPUnit feature tests with `RefreshDatabase`.

**Spec:** `docs/superpowers/specs/2026-10-24-financial-reports-design.md`

## Global Constraints
- A DV counts in the books when `status = 'approved'` and `dv_number` is not null, dated `dv_date`. Payment is not required.
- Receipts of kind `opening_balance` and `balance_forwarded` are NOT posted (cash opening comes from the opening-balance table).
- Account category comes from `ChartOfAccount.category`: `Assets`, `Liabilities`, `Equity`, `Revenue`; every other category is an expense.
- Fund cluster default when blank: `General Fund`.
- Permission: viewing needs `accounting.view` or `accounting.manage`; entering opening balances needs `accounting.manage`. Use the `permission:` middleware as in `routes/web.php`.
- Match existing code style: one-line `/** */` docblocks, `BelongsToOrganization` trait on new models, school scoping like `FinanceController::schoolIds()`.
- Owner's rules: commit and push after each task; do not touch documents; do not deploy without asking.

## Review Focus
- Empty books (no postings): every report renders zeros, no error.
- `from` after `to`: validation error, not an empty page.
- Account with no postings picked in GL/SL: empty ledger with zero balance.
- DV whose check was cancelled (journal deleted, status reset): absent from every report.
- Unbalanced DV journal (debits != credits): Trial Balance shows an "out of balance" notice instead of hiding it.

---

### Task 1: Data foundation (config, opening balances, postings)

**Files:**
- Create: `vibe-app/config/financial_reports.php`, `vibe-app/app/Models/OpeningBalance.php`, `vibe-app/database/migrations/2026_10_24_000001_create_opening_balances_table.php`, `vibe-app/app/Services/GeneralLedgerService.php`
- Test: `vibe-app/tests/Feature/FinancialReportsTest.php`

**Interfaces:**
- Produces: `GeneralLedgerService::postings(array $schoolIds, ?string $fundCluster, ?Carbon $to = null): Collection` of arrays with keys `date` (Carbon), `account_code`, `account_title`, `debit` (string, 2dp), `credit`, `school_id`, `fund_cluster`, `ref` (DV number, receipt reference or `Opening balance`), `particulars`, `source` (`opening|dv|receipt`), `sub` (payee, source of fund or `Opening balance`), `breakdown` (array of `{account_code, account_title, debit, credit}`, empty if none), sorted by date. Also `accountCategory(string $code): string` returning `Assets|Liabilities|Equity|Revenue|Expense`.
- Produces: `OpeningBalance` model (fillable `organization_id, school_id, fund_cluster, as_of, account_code, account_title, debit, credit`; `as_of` date cast; unique on `school_id, fund_cluster, account_code`).
- Config `financial_reports`: `cash_account` = `['code' => '1010202024', 'title' => 'Cash in Bank-Local Currency, Current Account-Land Bank of the Philippines ( LBP)']`; `receipt_credit` keyed by receipt `kind`: `nca` => `4030101000` (Subsidy from National Government), `cash_advance` => `1990101000` (Advances for Operating Expenses).

- [ ] **Step 1: Write failing tests** in `FinancialReportsTest` (reuse the `tenant()` helper and the way `tests/Feature/CashReportPrintTest.php` builds an approved `LiquidationReport`). Shared fixture: opening balance Dr cash 10,000 / Cr `3010101000` 10,000 (as_of `2026-01-01`); NCA receipt 50,000 on `2026-02-01`; approved DV dated `2026-03-01` with journal Dr an MOOE account from `ChartOfAccount` 8,000 / Cr cash 8,000. Tests:
  - `test_postings_include_opening_receipt_and_approved_dv`: 2 postings per source (6 total); total debits equal total credits, both `68000.00`.
  - `test_unapproved_dvs_are_not_posted`: a DV with `status = 'for_review'`, or with no `dv_number`, adds no postings.
  - `test_receipts_of_kind_opening_balance_and_balance_forwarded_are_not_posted`.
  - `test_postings_respect_the_to_date_and_fund_cluster_filters`.
- [ ] **Step 2: Run** `php artisan test --filter=FinancialReportsTest` from `vibe-app/`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement** migration, model, config and `GeneralLedgerService` per the Interfaces block. Receipt posting: Dr `cash_account`, Cr `receipt_credit[kind]`, fund cluster from the receipt. DV posting: one posting per `DvJournalLine`, `sub` = report `payee`, `breakdown` from `breakdownLines` where `journal_line_no == line_no`, fund cluster `dv_fund_cluster` or the default. Skip anything whose school is not in `$schoolIds`.
- [ ] **Step 4: Run** the filter again. Expected: PASS.
- [ ] **Step 5: Commit and push** `feat: general ledger postings from DV journals, receipts and opening balances`.

### Task 2: Trial Balance, General Ledger, Subsidiary Ledger

**Files:**
- Modify: `vibe-app/app/Services/GeneralLedgerService.php`
- Test: `vibe-app/tests/Feature/FinancialReportsTest.php`

**Interfaces:**
- Consumes: `postings()`, `accountCategory()` from Task 1.
- Produces (all take `array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to`; the ledger methods take `string $accountCode` first):
  - `trialBalance(...): array{rows: Collection, totals: array{debit: string, credit: string}, balanced: bool}`; row = `{account_code, account_title, opening_debit, opening_credit, period_debit, period_credit, ending_debit, ending_credit}`. Opening = postings before `from`, net shown on one side; ending = opening + period, net on one side; all-zero accounts omitted. `balanced` compares total ending debit and credit.
  - `ledger(string $accountCode, ...): array{opening: string, rows: Collection, closing: string}`; row = `{date, ref, particulars, debit, credit, balance}` with running balance (debit-positive). Unknown or empty account returns zeros and no rows.
  - `subsidiary(string $accountCode, ...): Collection` of `{sub, debit, credit, balance}`; a DV posting that has `breakdown` is split into one entry per breakdown line, using the breakdown account title as `sub`.

- [ ] **Step 1: Write failing tests** with the Task 1 fixture, `from 2026-02-01`, `to 2026-12-31`:
  - `test_trial_balance_has_equal_totals_and_correct_cash_row`: cash opening Dr 10,000, period Dr 50,000 / Cr 8,000, ending Dr 52,000; `balanced` true; totals debit and credit both `60000.00`.
  - `test_trial_balance_flags_an_unbalanced_dv_journal`: a DV journal Dr 8,000 / Cr 7,000 gives `balanced === false`.
  - `test_general_ledger_running_balance`: cash ledger closing `52000.00`, rows in date order.
  - `test_ledger_of_account_without_postings_is_zero`.
  - `test_subsidiary_ledger_splits_by_breakdown_and_falls_back_to_payee`: a DV line with a breakdown yields one entry per breakdown account title; one without yields a single entry named after the payee.
  - `test_reports_are_empty_but_valid_with_no_postings`.
- [ ] **Step 2: Run** the filter. Expected: FAIL.
- [ ] **Step 3: Implement** the three methods; keep decimal handling consistent with `CashReportService`.
- [ ] **Step 4: Run** the filter. Expected: PASS.
- [ ] **Step 5: Commit and push** `feat: trial balance, general ledger and subsidiary ledger`.

### Task 3: Income Statement and Balance Sheet

**Files:**
- Modify: `vibe-app/app/Services/GeneralLedgerService.php`
- Test: `vibe-app/tests/Feature/FinancialReportsTest.php`

**Interfaces:**
- Consumes: Task 1 and 2 methods.
- Produces:
  - `incomeStatement(array $schoolIds, ?string $fundCluster, Carbon $from, Carbon $to): array{revenue: Collection, expenses: Collection, totalRevenue: string, totalExpenses: string, surplus: string}`; `revenue` and `expenses` are grouped by `ChartOfAccount.category`, rows `{account_code, account_title, amount}`; period postings only.
  - `balanceSheet(array $schoolIds, ?string $fundCluster, Carbon $asOf): array{assets: Collection, liabilities: Collection, equity: Collection, totalAssets: string, totalLiabilities: string, totalEquity: string, balanced: bool}`; all postings up to `asOf`; cumulative surplus (revenue minus expenses, every posting up to `asOf`) is added to `equity` as a row titled `Current surplus/(deficit)`.

- [ ] **Step 1: Write failing tests** with the Task 1 fixture:
  - `test_income_statement_shows_revenue_expenses_and_surplus`: revenue `50000.00`, expenses `8000.00`, surplus `42000.00`.
  - `test_balance_sheet_balances`: assets `52000.00`, liabilities `0.00`, equity `52000.00` (10,000 accumulated surplus plus 42,000 current surplus), `balanced` true.
  - `test_balance_sheet_as_of_before_the_dv_excludes_it`: `asOf 2026-02-15` gives assets `60000.00`.
  - `test_statements_with_no_postings_are_zero`.
- [ ] **Step 2: Run** the filter. Expected: FAIL.
- [ ] **Step 3: Implement** both methods. Asset and expense natural balance is debit minus credit; liability, equity and revenue is credit minus debit.
- [ ] **Step 4: Run** the filter. Expected: PASS.
- [ ] **Step 5: Commit and push** `feat: income statement and balance sheet`.

### Task 4: Page, routes, navigation and opening-balance entry

**Files:**
- Create: `vibe-app/app/Http/Controllers/FinancialReportController.php`, `vibe-app/resources/views/financial-reports.blade.php`
- Modify: `vibe-app/routes/web.php` (after the chart-of-accounts routes, around line 189); `vibe-app/resources/views/layouts/budget.blade.php:25-28` and `vibe-app/resources/views/finance.blade.php:18` (add a `Financial Reports` link, icon `assessment`, route `financial-reports`, and handle the active route the way `chart-of-accounts` is handled)
- Test: `vibe-app/tests/Feature/FinancialReportsTest.php`

**Interfaces:**
- Consumes: all `GeneralLedgerService` methods.
- Produces routes: `GET /accounting/financial-reports` named `financial-reports` (query: `report` in `tb|gl|sl|is|bs`, default `tb`; `school_id`, `fund_cluster`, `from`, `to`, `account`); `POST /accounting/financial-reports/opening-balances` named `financial-reports.opening.store` and `DELETE /accounting/financial-reports/opening-balances/{openingBalance}` named `financial-reports.opening.destroy`, both with `permission:accounting.manage`.
- Validation: `from` and `to` are dates with `from <= to` (else redirect back with the error); `school_id` must be one of the user's schools (403 otherwise, same rule as `CashReportController::schoolIds`); defaults: first school, `from` Jan 1 of the current year, `to` today. Opening-balance store validates `school_id`, `fund_cluster` (default `General Fund`), `as_of`, `account_code` (exists in the organization's chart), `debit`/`credit` (>= 0, one of them > 0), and rejects a duplicate school + fund + account.

- [ ] **Step 1: Write failing tests**:
  - `test_each_report_tab_renders_with_the_fixture_numbers`: `52,000.00` on `tb`, on `gl` with `account=1010202024`, and on `bs`; `42,000.00` on `is`; the `sl` tab lists the payee.
  - `test_trial_balance_page_warns_when_out_of_balance`.
  - `test_from_after_to_is_rejected`.
  - `test_another_schools_data_is_forbidden`.
  - `test_opening_balance_can_be_added_and_deleted_only_with_accounting_manage`: a user without it gets 403.
  - `test_page_renders_with_no_data`.
- [ ] **Step 2: Run** the filter. Expected: FAIL (route missing).
- [ ] **Step 3: Implement** the controller (thin: validate, call the service, pass to the view), routes and nav links. The view: filter bar (school, fund cluster, from, to), five tabs, one table per report, an "Out of balance" notice on TB and BS, an opening-balance form and list under the TB tab, and a Print button using `window.print()` with `@media print` hiding filters and nav (reuse `partials.print-clean` as the other report views do).
- [ ] **Step 4: Run** `php artisan test` (whole suite). Expected: all PASS, no regressions.
- [ ] **Step 5: Manual check** with `preview_start`: open `/accounting/financial-reports`, click each tab, check print preview. Report what was and was not exercised.
- [ ] **Step 6: Commit and push** `feat: financial reports page with opening balances`; in the same commit update `specs.md` and the `vibe-app/CLAUDE.md` status list (standing permission on commit/push). Do not deploy; ask first.
