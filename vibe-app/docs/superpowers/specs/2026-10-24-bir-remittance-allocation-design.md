# BIR remittance allocation in the Cash Disbursement Register (design)

Date: 2026-10-24. Owner's decision: the register allocates a payment to the BIR to the expense accounts of the withheld taxes by itself, oldest first.

## Purpose

The school's Cash Disbursement Register is a disbursement record only; the main office does the accounting. Withholding tax is a liability (Due to BIR) when it is withheld and becomes an expense in the register only when it is remitted, in the expense columns of the accounts it was withheld from (the owner's workbook; no Due to BIR column). Today the owner types that split by hand in the breakdown of the DV to the BIR. The owner wants no entry for it: make the DV to the BIR (Dr Due to BIR, Cr Cash), record the check, and the register splits it.

## Success criteria

- A DV to the BIR with no breakdown shows its check amount in the expense columns of the accounts whose withholding it remits, and its Particulars name those accounts.
- The split follows the unremitted withholding of earlier paid DVs of the school, oldest check date first.
- A DV to the BIR that has a breakdown keeps that breakdown (nothing already entered by hand changes).
- The account columns, the Recapitulation and the Payments column agree (they already follow the check amount).

## Rules

1. **Remittance:** a paid DV whose charged account is Due to BIR (`2020101000`) and that has no breakdown and no ORS lines. A DV with a breakdown is not touched.
2. **Withheld tax:** the "Less: withholding tax" (`dv_deductions['withholding_tax']`) of every paid DV of the school, in order of check date then check number. Each is split among the expense accounts that DV is charged to, in proportion to the amounts charged (the splits of its breakdown or ORS lines, else its one account). A code starting with 2 (a liability) never receives a share.
3. **Allocation:** remittances are taken in order of check date then check number; each consumes the queue of withheld taxes from the oldest, keeping the account split of the DV it takes from, until its check amount is used. Amounts are in cents; the last piece takes the rounding difference.
4. **Not enough withheld tax recorded:** the shares found are brought to the check amount in proportion (the register already does this). With no withheld tax recorded the check shows in Payments and Balance only.
5. **Scope:** the whole school, all funds, all dates (a remittance of this quarter can use a tax withheld in an earlier one); the register still shows only the rows of its period and typed checks. A register of one fund blanks the amounts of the checks of other funds as before.

## Where

- `CashReportService::build` computes the allocation after the paid lines are made and puts the result in the `splits` of each remittance line. Nothing is stored; editing or deleting a DV changes the register on its next build. A register saved to history keeps the page it was saved with.
- No change to the DV forms, the database, the Cash Disbursement Register view or the other cash reports.

## Tests (`tests/Feature/CashReportServiceTest.php`)

- Two DVs withholding tax from two accounts and one remittance that crosses both: the remittance splits oldest first and ends at the check amount.
- A DV to the BIR with a breakdown keeps the breakdown.
- A DV to the BIR with no withheld tax recorded gets no account split.
- A remittance in a later quarter uses a tax withheld earlier.

## Not in this change

Choosing the DVs by month or by hand, a per-form (1601-EQ, 1600-PT, 1600-VT) split, an unremitted-tax report, and per-fund matching are not built.
