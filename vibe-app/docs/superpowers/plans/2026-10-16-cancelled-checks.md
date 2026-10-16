# Cancelled Checks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cash can cancel a paid check into a register; the ORS, DV and journal entry then open for editing (reason + own password), are approved again, and are paid with a new check.

**Architecture:** One new table (`cancelled_checks`) and one new ORS flag (`reissue_pending`). Cancel and edit are added to `FinanceController` (cancel is new; edit reuses the `createDv` validation so the rules stay in one place). Budget needs no change: `AllotmentRegistryService` reads `paid_at` and `amount` from the ORS.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit, Blade, the existing Cash/Accounting page `resources/views/finance.blade.php`.

**Spec:** `docs/superpowers/specs/2026-10-16-cancelled-checks-design.md`

## Global Constraints

- Additive migration only; no existing column is renamed or dropped.
- No new dependency. Strict minimal change: touch only the files listed below.
- Cancel needs permission `cash.pay`; edit needs `accounting.approve`; the ORS school must be in `$this->schoolIds()`.
- Cancel and every edit save need `reason` (required, max 255) and `password` (rule `current_password`).
- Edit is refused (422) unless `reissue_pending` is true.
- Do not edit `docs/specs.md` (protected). Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Use `route()` names; follow the one-line `recordEvent` + `AuditLog::create` style already in `FinanceController`.

## Review Focus

- Wrong or empty password on cancel or edit: refused, nothing changes.
- Cancelling an unpaid ORS (double click, stale page): 422, no second register row.
- User of another organization cancels or edits: 403 or not found, nothing changes.
- Edit with a journal that does not equal the new amount: refused, old lines kept.
- Paying again after edit clears `reissue_pending` and locks the DV again.

---

### Task 1: Table, model and the cancel action

**Files:**
- Create: `database/migrations/2026_10_16_100000_create_cancelled_checks_table.php`
- Create: `app/Models/CancelledCheck.php`
- Modify: `app/Models/LiquidationReport.php` (fillable/casts for `reissue_pending`, relation `cancelledChecks()`)
- Modify: `app/Http/Controllers/FinanceController.php` (new `cancelCheck`)
- Modify: `routes/web.php` (after line 177)
- Test: `tests/Feature/CancelledCheckTest.php`

**Interfaces:**
- Produces: route `cash.cancel` = `POST /cash/{liquidationReport}/cancel` (middleware `permission:cash.pay`); `FinanceController::cancelCheck(Request, LiquidationReport): RedirectResponse`; model `App\Models\CancelledCheck`; `LiquidationReport::cancelledChecks(): HasMany`; boolean column `liquidation_reports.reissue_pending` default false.

- [ ] **Step 1: Write failing tests** in `CancelledCheckTest` (helper builds a paid ORS like `DvJournalEntryTest::ors()`, with `dv_number`, `paid_at`, `payment_reference` 'CHK-1', `payment_mode` 'MDS Check', plus a master user and a second organization's user):
  - `test_cancelling_records_the_check_and_reopens_the_ors`: POST `cash.cancel` with `reason` and the user's password; assert one `cancelled_checks` row with check_number `CHK-1`, the amount, DV no. and reason; the report has `paid_at`, `payment_reference`, `payment_mode`, `paid_by` null, `status` `for_review`, `approved_at` null, `reissue_pending` true.
  - `test_cancel_needs_a_reason_and_the_users_own_password`: missing reason and wrong password each give session errors and leave the report paid with zero register rows.
  - `test_an_unpaid_ors_cannot_be_cancelled`: 422.
  - `test_another_organization_cannot_cancel`: 403.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/CancelledCheckTest.php` — expect FAIL (route missing).
- [ ] **Step 3: Migration** creating `cancelled_checks` (columns exactly as in the spec: organization_id, school_id, liquidation_report_id as foreign keys; ors_number, dv_number, check_number nullable; payment_mode; paid_at date; amount decimal(14,2); reason; cancelled_by foreign key to users; cancelled_at timestamp; timestamps) and adding `reissue_pending` boolean default false to `liquidation_reports`. Create the model/migration with `php artisan make:model CancelledCheck -m --no-interaction`, then fill them in.
- [ ] **Step 4: Implement `cancelCheck`**: authorize like `pay()`, `abort_unless($report->paid_at, 422, ...)`, validate `reason` and `password` (`current_password`), then in `DB::transaction` create the register row and update the report (fields per the spec). Set the master transaction back from `completed` to the status it had before payment (find the pre-payment value used elsewhere in this controller with Grep on `transaction` and `status`). Call `recordEvent('cash', 'check_cancelled', 'paid', 'unpaid', $checkNumber, ['reason' => ...])` and an `AuditLog` with action `check_cancelled`. Redirect to `route('cash', ['tab' => 'cancelled'])` with a success message.
- [ ] **Step 5: Add the route** `cash.cancel` under the `pay` route.
- [ ] **Step 6: Run** the test file — expect PASS. Then `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 7: Commit** `feat: cancel a paid check into a cancelled-checks register`.

---

### Task 2: Edit the ORS, DV and journal while `reissue_pending`

**Files:**
- Modify: `app/Http/Controllers/FinanceController.php` (`createDv` at line 154)
- Modify: `resources/views/finance.blade.php` (Approve button condition at line 105)
- Test: `tests/Feature/CancelledCheckTest.php`

**Interfaces:**
- Consumes: `reissue_pending` and the cancel action from Task 1.
- Produces: `accounting.dv.store` accepts a reopened ORS; extra input `amount`, `purpose`, `reason`, `password` when `reissue_pending`.

- [ ] **Step 1: Write failing tests:**
  - `test_a_reopened_ors_can_be_edited_with_a_new_amount_and_journal`: after cancel, POST `accounting.dv.store` with `amount` 9000, balanced journal totalling 9000, reason, password; assert amount is 9000, old journal lines replaced, `dv_number` unchanged, status still `for_review`.
  - `test_the_edit_is_refused_when_the_ors_was_not_cancelled`: a normal paid ORS with a DV gets 422.
  - `test_the_edit_needs_reason_and_password`: both missing/wrong cases leave the data unchanged.
  - `test_an_edit_whose_journal_does_not_match_the_new_amount_keeps_the_old_lines`.
  - `test_approving_then_paying_again_locks_the_ors`: approve via `accounting.review`, pay via `cash.pay`; assert `reissue_pending` false and a second edit is refused.
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: In `createDv`:** allow an existing `dv_number` only when `reissue_pending` (replace the abort at line 158 and skip the "approved" check at line 157 in that case). When `reissue_pending`: require `reason` and `password` (`current_password`), accept `amount` (numeric, min 0.01) and `purpose`, set the amount on the model before `journalLines` and the deductions check run, keep the existing `dv_number`, and inside the transaction delete the report's `DvJournalLine` rows before creating the new ones. Record `dv_edited` (event and `AuditLog`) with old and new amount and the reason. Do not run these rules for the preview path beyond what already works.
- [ ] **Step 4: Approve button:** in `finance.blade.php:105` show Approve when `! $r->dv_number || $r->reissue_pending`. Confirm `review()` needs no change (a cleared `paid_at` already passes line 92).
- [ ] **Step 5: In `pay()`** set `reissue_pending` to false when recording the new payment.
- [ ] **Step 6: Run** the test file — expect PASS; run `php artisan test --compact tests/Feature/DvJournalEntryTest.php` to confirm normal DV creation is unchanged.
- [ ] **Step 7: Commit** `feat: edit the ORS, DV and journal entry of a cancelled check`.

---

### Task 3: Screens: cancel button, edit fields and the register tab

**Files:**
- Modify: `app/Http/Controllers/FinanceController.php` (`cash()` at line 106)
- Modify: `resources/views/finance.blade.php`
- Test: `tests/Feature/CancelledCheckTest.php`

**Interfaces:**
- Consumes: routes `cash.cancel` and `accounting.dv.store`.

- [ ] **Step 1: Write failing tests:** `test_the_cancelled_checks_tab_lists_the_register` (GET `cash` with `tab=cancelled` shows the check number, DV, amount, reason and who cancelled); `test_another_organization_does_not_see_the_register` (their rows are absent); `test_the_paid_tab_offers_cancel_only_to_cash_pay_users` (assertSee `route('cash.cancel', $report)` for a permitted user, not for one without `cash.pay`).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: `cash()`**: add a `cancelled` tab with its count badge and the register rows (scoped to the user's schools through `schoolIds()`), newest `cancelled_at` first.
- [ ] **Step 4: Blade:** (a) Cancel button on paid rows for `cash.pay` users, opening a small modal in the same style as the Record Payment modal (line ~154) with Reason and Password fields posting to `cash.cancel`. (b) For `reissue_pending` rows in Accounting, an "Edit DV" button that reuses the Create DV modal (the `data-dv` attributes at line 104) prefilled from the row, with extra Amount, Reason and Password fields. (c) A read-only table for the `cancelled` tab with search like the other tabs. Reuse existing classes; no new styles.
- [ ] **Step 5: Run** the test file — expect PASS. Check the three screens in the browser preview (cancel modal, edit modal, register tab) and report what you saw.
- [ ] **Step 6: Commit** `feat: cancelled checks screens`.

---

### Task 4: Budget follow-through and full check

**Files:**
- Test: `tests/Feature/CancelledCheckTest.php`

- [ ] **Step 1: Write the test** `test_the_allotment_registry_follows_the_cancel_and_the_new_amount`: build the ORS with an allotment as the existing registry tests do (see `app/Services/AllotmentRegistryService.php` and its feature test), assert the disbursement row exists while paid, disappears after cancel, and returns with the new amount after edit, approve and pay.
- [ ] **Step 2: Run** it. If it fails because the registry caches or reads something other than `paid_at`/`amount`, stop and report to the owner instead of changing the registry.
- [ ] **Step 3: Run** `php artisan test --compact` for the whole suite and `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 4: Report** per the owner's format: Problem, Fix Applied, Files Modified, Verification, Unchanged. Do not commit/push/deploy or edit `specs.md` unless the owner says so.
