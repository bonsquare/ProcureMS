# Cash reports: Report of Checks Issued, Checks and Advices to Debit Account Disbursement Record, Cash Disbursement Register

Date: 2026-10-16. Status: design, waiting for the owner's review. Sources of truth: the owner's three workbooks `11REPORT OF CHECKS ISSUED.xlsx` (Appendix 35), `CHECKS AND ADVICES TO DEBIT ACCOUNT DISBURSEMENT RECORD.xlsx` (sheet `chck_advice`) and `CASH DISBURSEMENT REGISTER.xlsx` (sheet `CASH_DISBRSMNT`).

## Goal

A school's cash user prints the three official cash reports for a quarter (or a date range) from the Cash transactions already in the system, so the reports always agree with Cash, the DV and the Cancelled Checks register. Print only (no Excel in this version).

## What the forms need

| Form | Lines | Columns |
|---|---|---|
| Report of Checks Issued | one per check of the period, cancelled checks included | Check Date, Check Serial No., DV/Payroll No., ORS/BURS No., Responsibility Center Code, Payee, UACS Object Code, Nature of Payment, Amount |
| Checks and Advices to Debit Account Disbursement Record | same lines | NCA/DS/DV/Payroll No. and Date, Check/ADA Serial No., Date, Date Released, Payee, UACS Object Code, Nature of Payment, Amount: NCA Received Deposit Mode, Check Issued, ADA Issued, NCA / Bank Balance |
| Cash Disbursement Register | one per payment | Date, Reference, Particulars, Working Fund (Cash Advance, Payments, Balance), Breakdown of Payments (one column per expense account, headed by title and UACS code) |

All three share a header: Entity Name, Fund Cluster, Bank Name / Account No., Accountable Officer, Official Designation, Station, Report No., Sheet No., Period Covered.

## Data

- **Existing, used as is:** `liquidation_reports` (paid ORS/DV: `payment_reference` = check no., `payment_mode`, `paid_at`, `check_date`, `bank_name`, `bank_account_number`, `payment_fund_cluster`, `dv_number`, `ors_number`, `responsibility_center_code`, `payee`, `amount`, budget line for the account and UACS code), `cancelled_checks`, the school profile (bank details), employees with the Disbursing Officer role.
- **New (additive migration):** `cash_fund_receipts`: `organization_id`, `school_id`, `received_at` (date), `kind` (`opening_balance`, `nca`, `cash_advance`), `fund_cluster`, `bank_account_number`, `amount`, `reference`, `note`, `created_by`, timestamps. Tenant-scoped like every school record; each create, change and delete writes an audit row.
- The opening balance and any NCA or cash advance are typed per quarter (owner's decision); nothing is carried forward automatically.

## Report service

`App\Services\CashReportService`, one method per report, all taking the school and a period:

1. **Check lines:** paid DVs with `paid_at` in the period (mode check) plus the Cancelled Checks of the period as "Cancelled Check" lines with no amount, ordered by date then check number. An unpaid ORS never appears. A payment by ADA appears in the ADA Issued column.
2. **Responsibility center, UACS code, nature of payment:** from the ORS and its budget line (account code and title).
3. **Balance:** the fund received typed for the period up to that line minus payments up to that line (nothing is carried forward from an earlier period).
4. **Account columns of the register:** only the accounts actually paid in the period, each headed by title and UACS code. When there are more columns than the paper takes, the register continues in a second column group.

## Screen and print

- **Cash > Reports tab:** period (quarter and fiscal year, or from and to dates), the **Fund received** entries (add, edit, delete) and a button for each of the three reports.
- **Print pages:** the shared print engine with the paper toolbar (docs/specs.md 1.5, 1.6, 1.8), landscape, the layout of the owner's workbook for each form, header from School Settings, Accountable Officer = the employee holding the Disbursing Officer role with their position, **Report No.** and **Sheet No.** typed on the report page before printing. A document with no payments in the period still prints (empty lines, balance only).
- Official-document rules of docs/specs.md section 1 apply (template fixed; only the content mapping is ours).

## Rules and tests

- Permission: users who can use Cash; a school sees only its own data; organization isolation covered by tests.
- Tests: check lines and totals of a period, cancelled checks as zero lines, ADA versus check, running balance with fund received, account columns from the payments, header values, empty period, permission and isolation.

## Not included now

Excel export, bank reconciliation, automatic carry-forward of the balance, payroll lines.

## Open questions for the plan

- Which account the UACS code and nature of payment come from when an ORS is charged to a budget line without an account (fall back to the DV journal debit line).
- Column grouping of the register on long bond landscape (decided when the print is built against the workbook).
