# Form Generator and Program of Works (POW) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Form Generator page under the SOB that builds a saved, printable Program of Works from the SOB items of a chosen Program and Activity.

**Architecture:** Two additive tables (`pow_plans`, `pow_items`) hold a snapshot of the POW. `PowService` turns SOB items into POW lines and computes totals; `FormGeneratorController` serves the generator page, the POW page and the print; the print uses the shared official print engine with `folio` as the default paper. A code registry (`GeneratedForms`) lists the forms so later documents can be added.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests, the shared print engine (`partials/official-toolbar`, `partials/print-clean`).

**Spec:** `docs/superpowers/specs/2026-10-16-form-generator-pow-design.md`

## Global Constraints

- Additive migrations only; nothing existing is renamed or dropped (CLAUDE.md rule 3).
- New models use `App\Models\Concerns\BelongsToOrganization`; every read and write checks the school with the same rule as `SobController::scoped()` (`schoolIds($request)`).
- Permission `planning.manage` to create, edit, delete; `planning.view` or `planning.manage` to open and print.
- Official print: `data-official-page`, `data-paper="folio"`, `data-orientation="portrait"`, `data-margin="10"`, no `@page` of its own, include `partials.official-toolbar`; Arial.
- Amounts: two decimals; quantity = SOB frequency x quantity; amount = quantity x unit cost, computed on the server.
- Do not edit `docs/specs.md` outside Task 6 (the owner's standing permission applies to the commit step only).
- Run `vendor/bin/pint --dirty --format agent` after PHP changes; if it reorders imports or traits of a file you did not need to touch, undo that part.
- Test fixtures: `Tests\Support\SobFixture::make()` (returns `organization`, `school`, `user`, `aip`, `kra`, `activities`, `account`); the SOB comes from `app(SobService::class)->create($school, 2026, 1, 'MOOE', $user)` and items from `addItems($plan, $activityId, $accountId, $lines, $note, $extras)`.

## Review Focus

- A Program or Activity with no items in the SOB: the generator shows an empty checklist and Create POW refuses ("nothing to put in the POW") instead of saving an empty POW.
- All materials unchecked and no labor: refused with a validation error, nothing saved.
- Activity picked that belongs to a different SOB or school (tampered id): 403/404, nothing saved.
- Decimal quantities (frequency 3 x quantity 1.5): amount is exact to two decimals and totals match the print.
- A POW whose SOB was later edited or whose SOB items were deleted: opens, prints and totals unchanged (snapshot; `sob_item_id` nulls on delete).

---

### Task 1: Tables and models

**Files:**
- Create: `database/migrations/2026_10_16_090001_create_pow_plans_table.php`, `database/migrations/2026_10_16_090002_create_pow_items_table.php`, `app/Models/PowPlan.php`, `app/Models/PowItem.php`
- Test: `tests/Feature/PowModelTest.php`

**Interfaces:**
- Produces: `PowPlan` (fillable `organization_id, school_id, sob_plan_id, aip_activity_id, program, project_title, school_address, pow_date, date_started, date_completed, manpower, equipment, labor_cost, signatories, created_by`; casts: three dates as `date`, `labor_cost` `decimal:2`, `signatories` `array`; relations `school()`, `sobPlan()`, `items()` ordered by `sort`); `PowItem` (fillable `organization_id, pow_plan_id, sob_item_id, description, unit, quantity, unit_cost, amount, sort`; casts decimals; relation `plan()`).

- [ ] **Step 1: Write the failing test** `PowModelTest::test_a_pow_keeps_its_lines_in_order_and_is_scoped_to_its_organization`: create a POW with two items (`sort` 2 then 1) through the models and assert `items` returns sort 1 first; create a second organization's POW and assert `PowPlan::count()` as the first organization's user is 1 (global org scope).
- [ ] **Step 2: Run** `php artisan test --compact --filter=PowModelTest` — expect FAIL (class not found).
- [ ] **Step 3: Implement** the two migrations as in the spec's Data section (`sob_plan_id` `constrained('sob_plans')->restrictOnDelete()`, `aip_activity_id` nullable, `pow_plan_id` cascade, `sob_item_id` nullable `nullOnDelete`) and the two models (`php artisan make:model` then edit).
- [ ] **Step 4: Run** the test — expect PASS; run `php artisan migrate --no-interaction` locally.
- [ ] **Step 5: Commit** `feat: pow_plans and pow_items tables and models`.

### Task 2: PowService (materials, labor, totals, defaults)

**Files:**
- Create: `app/Services/PowService.php`
- Test: `tests/Feature/PowServiceTest.php`

**Interfaces:**
- Consumes: `SobPlan`, `SobItem` (`is_extra`, `aip_activity_id`, `activity.kra.program`), `SignatorySlots::defaults('notice_to_award', $activeStaff)['schoolHead']`, `PlanningSignatories`-style "latest POW" lookup (own query on `pow_plans`).
- Produces (exact signatures):
  - `programs(SobPlan $plan): Collection` of program names that have items; `activities(SobPlan $plan, string $program): Collection` of `AipActivity` that have items in the plan under that program.
  - `materials(SobPlan $plan, string $program, ?int $activityId): Collection` of arrays `{sob_item_id, description, unit, quantity, unit_cost, amount}` (non-extra items only; `$activityId` null = every activity of the program).
  - `labor(SobPlan $plan, string $program, ?int $activityId): float` (sum of the extras' `amount`).
  - `create(SobPlan $plan, array $data, User $user): PowPlan` — `$data` keys `program`, `activity_id`, `project_title`, `lines` (each `description, unit, quantity, unit_cost`, optional `sob_item_id`), `labor_cost`, dates, `manpower`, `equipment`, `school_address`, `signatories`; validates and saves in one transaction; throws `ValidationException` (field `lines`) when there is no line and `labor_cost` is 0.
  - `update(PowPlan $pow, array $data, User $user): PowPlan` (replaces lines and fields); `totals(PowPlan $pow): array{materials: float, labor: float, total: float}`.
  - `defaultSignatories(School $school): array{prepared_by: array{name,position}, recommended_by: ..., approved_by: ..., division_superintendent: ...}` — School Head from staff else `$school->school_head`; the other slots from the school's latest POW, else empty strings.

- [ ] **Step 1: Write the failing tests** (names and assertions):
  - `test_materials_come_from_the_chosen_program_and_activity_only`: two activities with items; `materials($plan, 'Program One', $activity1->id)` has only activity 1's lines; with `null` has both; an extra ("35% Labor") is not in it.
  - `test_quantity_is_frequency_times_quantity_and_amount_is_exact`: item frequency 3, quantity 1.5, unit_cost 100.10 gives quantity 4.50 and amount 450.45.
  - `test_labor_is_the_sum_of_the_other_lines`.
  - `test_create_saves_a_snapshot_that_does_not_follow_the_sob`: create, then change the SOB item's `unit_cost` and delete the SOB item; the POW's line keeps its values and `sob_item_id` is null after the delete.
  - `test_totals_add_materials_and_labor`: materials 55,000 + labor 19,250 gives total 74,250.
  - `test_a_pow_with_no_line_and_no_labor_is_refused` and `test_quantity_must_be_positive_unit_cost_not_negative_and_start_not_after_completion`.
  - `test_default_signatories_use_the_last_pow_of_the_school`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=PowServiceTest` — expect FAIL.
- [ ] **Step 3: Implement** `PowService` with the signatures above; use `Validator::make` like `SobService::cleanNote`; amounts `round(qty * cost, 2)`; programs/activities from `$plan->items()->with('activity.kra')`.
- [ ] **Step 4: Run** the tests — expect PASS.
- [ ] **Step 5: Commit** `feat: PowService builds a POW from the SOB items`.

### Task 3: Form Generator page, POW store and the SOB panel link

**Files:**
- Create: `app/Support/GeneratedForms.php`, `app/Http/Controllers/FormGeneratorController.php`, `resources/views/form-generator.blade.php`
- Modify: `routes/web.php` (near the `planning/sob` routes), `resources/views/planning.blade.php:372` (add the **Form Generator** link button beside Create SOB)
- Test: `tests/Feature/FormGeneratorTest.php`

**Interfaces:**
- Consumes: `PowService` (Task 2), `schoolIds` rule as in `SobController`.
- Produces: `GeneratedForms::all(): array<string, array{key, title, description}>` with one entry `pow`; routes `planning.forms` (GET `/planning/forms`, query `sob_plan_id`, `program`, `activity_id`), `planning.pow.store` (POST `/planning/pow`, middleware `permission:planning.manage`); page lists the school's saved POWs newest first and, when a Program (and Activity) is chosen, shows the materials checklist (checkbox `lines[i][include]`), labor, title, dates, manpower, equipment, signatures with live totals (small inline script).

- [ ] **Step 1: Write the failing tests** (`FormGeneratorTest`):
  - `test_the_generator_lists_programs_and_activities_of_the_chosen_sob_and_the_materials` (GET with `sob_plan_id`, `program`, `activity_id`; assert see the item particulars and the labor amount).
  - `test_the_sob_panel_links_to_the_form_generator` (GET `planning` with the school; assert the `planning.forms` url).
  - `test_create_pow_saves_checked_lines_only_and_redirects_to_the_pow_page` (POST with two lines, one without `include`; assert one `pow_items` row and redirect to `planning.pow.show`).
  - `test_create_pow_needs_planning_manage_and_the_users_own_school` (a user without the permission gets 403; another organization's `sob_plan_id` gets 403/404 and nothing is saved).
  - `test_nothing_to_put_in_the_pow_is_refused` (no lines, labor 0: session error on `lines`, no row).
- [ ] **Step 2: Run** `php artisan test --compact --filter=FormGeneratorTest` — expect FAIL.
- [ ] **Step 3: Implement** the controller (`index(Request): View`, `store(Request): RedirectResponse`), the registry, the routes, the view (follow the look of `sob-show.blade.php` boxes and `partials.sob-line-row` classes) and the link button. Audit entry `pow_created` through `AuditLog::create` as `FinanceController::pay` does.
- [ ] **Step 4: Run** the tests — expect PASS.
- [ ] **Step 5: Commit** `feat: Form Generator page and POW creation`.

### Task 4: POW page (edit, delete)

**Files:**
- Create: `resources/views/pow-show.blade.php`
- Modify: `app/Http/Controllers/FormGeneratorController.php` (`show`, `update`, `destroy`), `routes/web.php`
- Test: `tests/Feature/PowPageTest.php`

**Interfaces:**
- Consumes: `PowService::update`, `PowService::totals`.
- Produces: routes `planning.pow.show` (GET `/planning/pow/{pow}`), `planning.pow.update` (PUT), `planning.pow.destroy` (DELETE); show page has editable fields and lines (add/remove a line), totals, Save, Print (link to `planning.pow.print`), Delete; audit `pow_updated`, `pow_deleted`.

- [ ] **Step 1: Write the failing tests:** `test_a_pow_can_be_edited_and_the_totals_follow` (change a quantity and labor; assert saved and `totals`); `test_another_schools_pow_is_forbidden_for_show_update_and_delete`; `test_delete_removes_the_pow_and_its_lines_and_writes_an_audit_entry`; `test_view_only_users_can_open_but_not_change` (a user with `planning.view` only: show 200, update/destroy 403).
- [ ] **Step 2: Run** `php artisan test --compact --filter=PowPageTest` — expect FAIL.
- [ ] **Step 3: Implement** the three controller methods (scope check as `SobController::scoped`) and the view; validation lives in `PowService::update`.
- [ ] **Step 4: Run** — expect PASS.
- [ ] **Step 5: Commit** `feat: open, edit and delete a saved POW`.

### Task 5: Official POW print (folio)

**Files:**
- Create: `resources/views/pow-print.blade.php`
- Modify: `app/Http/Controllers/FormGeneratorController.php` (`print`), `routes/web.php`
- Test: `tests/Feature/PowPrintTest.php`

**Interfaces:**
- Consumes: `PowService::totals`, `School` (region, division, address, `deped_school_id`, `code`), `AgencySetting::first()` as in `SobController::print`.
- Produces: route `planning.pow.print` (GET `/planning/pow/{pow}/print`); the page follows the spec's Print section.

- [ ] **Step 1: Write the failing tests:** `test_the_print_uses_the_official_engine_with_folio_as_default_paper` (assert `data-official-page`, `data-paper="folio"`, no `@page`); `test_the_print_shows_letterhead_info_block_table_and_totals` (assert "PROGRAM OF WORKS", "MOOE 3RD QUARTER 2026", project title, "TOTAL MATERIALS COST", `55,000.00`, "B. Labor Cost:", `19,250.00`, "C. Total Project Cost", `74,250.00`, Budget Allocation `74,250.00`); `test_the_school_id_is_the_deped_school_id_else_the_system_code`; `test_the_optional_superintendent_signature_prints_only_when_filled`; `test_signature_names_are_underlined_and_blank_rows_are_left_out`; `test_the_print_is_forbidden_for_another_school`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=PowPrintTest` — expect FAIL.
- [ ] **Step 3: Implement** the view by copying the structure of `sob-print.blade.php` (masthead without logos, `data-paginate` table) and the controller `print` (permission as `SobController::print`).
- [ ] **Step 4: Run** — expect PASS; open the print in the browser pane once and check the Folio/A4 switch and the layout against the workbook.
- [ ] **Step 5: Commit** `feat: official Program of Works print on folio paper`.

### Task 6: Documents and full check

**Files:**
- Modify: `docs/specs.md` (new section 38, only the lines of this feature), `CLAUDE.md` (one status line)

- [ ] **Step 1:** Add section 38 "Form Generator and Program of Works" to `docs/specs.md` summarizing the spec's Rules, Screens and Print; add the status line to `CLAUDE.md`.
- [ ] **Step 2: Run** `vendor/bin/pint --dirty --format agent` then the whole suite `php artisan test --compact` — expect all pass.
- [ ] **Step 3: Commit** `docs: Form Generator and POW`, then push and deploy only when the owner says so (standing workflow in CLAUDE.md).
