# Financial Reports: Trial Balance, General Ledger, Subsidiary Ledger, Balance Sheet, Income Statement

## Goal
Add the five financial reports to Accounting, computed from data already in the system. Per school, filterable by fund cluster and date range, with a print view.

## Decisions (approved 2026-10-24)
- **Derive, don't store.** `GeneralLedgerService` builds a list of postings (date, account code/title, debit, credit, school, fund cluster, reference) on the fly. All reports read that list. No second ledger table.
- **Posting sources**
  1. `DvJournalLine` of DVs recognized once approved (`status = approved`, has `dv_number`), dated `dv_date`. Payment is not required. A cancelled check already deletes the journal and reopens the ORS, so those entries drop out by themselves.
  2. `CashFundReceipt`: one auto entry each (Dr cash in bank, Cr fund/revenue account) using a config mapping by `kind` and `source_of_fund`. Receipts of kind `opening_balance` are not double-counted with the opening balance table.
  3. New table `opening_balances` (organization_id, school_id, fiscal_year, account_code, debit, credit) with a simple entry screen.
- **Account mapping** lives in one config array with defaults. No UI to edit it yet.

## Reports (Accounting > Financial Reports, one page, tabs)
- **Trial Balance:** per account for the range: opening, period debit, period credit, ending; shows a balanced check (total debits = total credits).
- **General Ledger:** pick an account; postings with running balance.
- **Subsidiary Ledger:** pick a control account; grouped by sub-account/payee using `DvBreakdownLine` and the DV payee.
- **Income Statement:** Revenue minus Expenses, grouped by chart category.
- **Balance Sheet:** Assets = Liabilities + Equity as of a date; period surplus/deficit rolls into equity.
- Filters: school, fund cluster, date range. Print view for each. Access: `accounting.manage`.

## Testing
Feature tests with a fixture of paid DVs, receipts and an opening balance. Assert TB debits equal credits and Assets equal Liabilities plus Equity.

## Out of scope
Manual journal voucher screen, period-closing entries, PDF/Excel export beyond print, UI to edit the account mapping.

## Risks
- Books are only as complete as DV journals plus receipts; anything outside them (adjustments, other income) will not appear until a manual journal screen exists.
- Chart categories (Assets/Liabilities/Equity/Revenue/expenses) drive statement grouping; mis-categorised accounts will land in the wrong section.
