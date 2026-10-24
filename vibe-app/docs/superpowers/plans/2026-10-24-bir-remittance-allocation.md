# BIR remittance allocation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A paid DV to the BIR with no breakdown is split by itself, in the Cash Disbursement Register, among the expense accounts of the withheld taxes it remits, oldest first.

**Architecture:** `CashReportService::build` makes the paid lines, then a new private method fills the `splits` of every remittance line from a queue of the withheld taxes of the earlier paid DVs. Nothing is stored and no view changes (the register already brings splits to the check amount and writes the expense titles in Particulars).

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit (`tests/Feature/CashReportServiceTest.php`).

**Spec:** `docs/superpowers/specs/2026-10-24-bir-remittance-allocation-design.md`

## Global Constraints

- Due to BIR is the account code `2020101000`; a code starting with `2` never receives a share.
- Amounts are in cents; the last piece of a split takes the rounding difference.
- Order is check date, then check number; the scope is the whole school, all funds and dates.
- A DV to the BIR with a breakdown (non-empty `splits`) is not touched.
- No new table, no change to the DV forms, the register view or the other cash reports.

## Review Focus

- A remittance larger than all the tax recorded: the shares found are brought to the check amount (the register does this); pinned by Task 1 test 3.
- No withheld tax recorded: the line keeps empty `splits`; pinned by Task 1 test 3.
- A remittance in a later quarter than the withholding; pinned by Task 1 test 4.
- A withheld DV charged to several accounts splits its tax in proportion; pinned by Task 1 test 5.
- Two remittances in a row do not take the same tax twice; pinned by Task 1 test 1.

---

### Task 1: Allocate remittances in `CashReportService`

**Files:**
- Modify: `app/Services/CashReportService.php` (call in `build`, new private method)
- Test: `tests/Feature/CashReportServiceTest.php`

**Interfaces:**
- Consumes: the paid line arrays of `paidLine()` (keys `date`, `check_no`, `amount`, `gross`, `tax`, `uacs_code`, `splits`, `cancelled`).
- Produces: `private function allocateRemittances(Collection $lines): Collection` returning the same lines with `splits` (`code => amount`, 2 decimals) filled on each remittance line.

- [ ] **Step 1: Write the failing tests** in `CashReportServiceTest.php`, using its `paid()` helper (the charged account is the one given; give a DV its withholding with `['dv_deductions' => ['withholding_tax' => N]]` in the overrides; a DV to the BIR is `paid($check, $date, $amount, $account('2020101000', 'Due to BIR'))`). Each test builds the report with `build($school, from, to)` and reads `$data['lines']` by `check_no`:
  1. `test_a_remittance_takes_the_withheld_taxes_oldest_first`: DV `0001` (2026-07-05, 1000, account A `5020399000`, withholding 100), DV `0002` (2026-07-10, 2000, account B `5020301000`, withholding 200), remittance `0003` (2026-07-20, 250) and remittance `0004` (2026-07-25, 50). Expect `0003` splits `['5020399000' => 100.0, '5020301000' => 150.0]` and `0004` splits `['5020301000' => 50.0]`.
  2. `test_a_remittance_with_a_breakdown_keeps_it`: DV `0001` as above plus remittance `0002` with a `DvBreakdownLine` (and its `DvJournalLine`, as in the existing breakdown tests) naming account B for the whole amount; expect its splits to stay `['5020301000' => amount]`.
  3. `test_a_remittance_with_no_withheld_tax_recorded_has_no_split_and_one_larger_than_the_tax_gets_the_tax_accounts`: remittance alone has `splits === []`; with DV `0001` (withholding 100) and a remittance of 300, splits are `['5020399000' => 100.0]` (the register scales it to 300).
  4. `test_a_remittance_of_a_later_quarter_uses_a_tax_withheld_earlier`: DV `0001` on 2026-06-10 with withholding 100, remittance `0002` on 2026-08-05 for 100, report for 2026-07-01 to 2026-09-30; expect `0002` splits `['5020399000' => 100.0]` and no line for `0001`.
  5. `test_a_tax_of_a_dv_charged_to_several_accounts_is_split_in_proportion`: DV `0001` with a breakdown charging A 600 and B 400 and withholding 100, remittance `0002` for 100; expect `['5020399000' => 60.0, '5020301000' => 40.0]`.

- [ ] **Step 2: Run** `php artisan test --compact --filter=CashReportServiceTest` — Expected: the five new tests FAIL (`splits` empty).

- [ ] **Step 3: Implement `private function allocateRemittances(Collection $lines): Collection`** and call it in `build()` on `$paidLines` before they are concatenated with the cancelled lines. Order the lines by `date` then `check_no`. Withheld queue: each line with `tax > 0` pushes `['left' => tax in cents, 'shares' => account code => weight]`, where weights are its `splits` if not empty, else `[uacs_code => 1]`, dropping codes that start with `2` or are empty. Remittance: a line with `uacs_code === '2020101000'` and empty `splits`. For each remittance, take cents from the queue front until the check `amount` is used (when a withheld DV gives part of its tax, its remaining `left` goes down and the part is split by its shares), add the pieces per code, and set `splits` to the code => amount (2 decimals); the last piece of each split takes the rounding difference. Leave `splits` as they are if the queue is empty.

- [ ] **Step 4: Run** `php artisan test --compact` — Expected: all tests PASS (the register test for a DV to the BIR with a breakdown still passes).

- [ ] **Step 5: Commit** (after the owner says so, with `docs/specs.md` section 89 and the `CLAUDE.md` status line updated in the same commit):

```bash
git add vibe-app/app/Services/CashReportService.php vibe-app/tests/Feature/CashReportServiceTest.php vibe-app/docs
git commit -m "feat: register splits a payment to the BIR among the expense accounts of the withheld taxes"
```
