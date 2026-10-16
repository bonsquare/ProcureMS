# Cancelled checks: design

Date: 2026-10-16. Status: approved by the owner in chat; waiting for review of this file.

## Goal

After an ORS is paid, Cash can cancel the check. The cancelled check is kept in a register. The ORS, DV and journal entry are then opened for editing (for example to change the amount), approved again, and paid with a new check. Cash, Accounting and Budget stay consistent.

## Decisions (owner)

- Cancelling returns the ORS to review; it must be approved again before the new check.
- Editing the ORS, DV and journal entry is allowed only after a cancel (inside the cancel flow). It is not allowed on any other paid or unpaid ORS.
- Every cancel and every edit save needs a **reason** and the **signed-in user's own password**.
- No new dependency. Additive migration only. `docs/specs.md` is not touched by this work unless the owner says commit/push.

## Data

- New table `cancelled_checks`: `organization_id`, `school_id`, `liquidation_report_id`, `ors_number`, `dv_number`, `check_number` (the old `payment_reference`), `payment_mode`, `paid_at`, `amount`, `reason`, `cancelled_by`, `cancelled_at`.
- New column `liquidation_reports.reissue_pending` (boolean, default false).
- The budget needs no change: the Allotment Registry reads disbursement from `paid_at` and `amount` (`AllotmentRegistryService`), so clearing `paid_at` removes the disbursement and a new `amount` flows into the obligation.

## Cancel (Cash, Paid tab)

- Permission `cash.pay`; the ORS must belong to one of the user's schools; the ORS must be paid.
- Input: reason (required) and password (checked against the signed-in user).
- One DB transaction: write the `cancelled_checks` row; clear `paid_at`, `payment_reference`, `payment_mode`, `paid_by`; set `status` back to `for_review`, `approved_at` null, `reissue_pending` true; move the master transaction out of `completed`.
- `recordEvent('cash', 'check_cancelled', ...)` on the transaction spine and an `AuditLog` row.

## Edit (Accounting), only while `reissue_pending`

- Permission `accounting.approve`. Editable: ORS amount and details, DV fields, deductions and journal lines. Journal lines are replaced.
- Same validation as `createDv` (every account in the chart, balanced, deductions below the amount) and a reason plus password on every save.
- Audit log keeps old and new values; a transaction event is recorded.
- Refused with 422 when `reissue_pending` is false.

## Pay again

- The ORS is approved again in Accounting, then appears in Cash > Awaiting payment. Recording the new payment clears `reissue_pending`, and the records are locked as before.

## Register

- New "Cancelled Checks" tab in Cash: read-only list with search (check no., DV, ORS, payee) showing date, amount, reason and who cancelled.

## Tests

- Cancel records and unlocks; missing reason or wrong password is refused; no `cash.pay` gives 403; another organization cannot see or touch it.
- Edit refused when `reissue_pending` is false; allowed when true; unbalanced journal refused; wrong password refused.
- Budget follows the new amount and the cleared payment.
