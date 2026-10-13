# SIP Import from Excel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A user uploads a school's own `.xlsx` SIP, edits what was read in an on-screen preview, and confirms; the programs, activities and signatories are saved in one step.

**Architecture:** `SipWorkbookReader` turns the workbook into a normalized plan array plus a list of issues (no database). `SipImportService` validates a plan and saves it; the web import and `php artisan sip:import` both use it. `SipImportController` holds the parsed plan in the cache under a token, shows an editable preview, and on Confirm validates the edited plan (sent as one JSON field) before saving.

**Tech Stack:** Laravel 13, PHP 8.4, SQLite, PHPUnit 12 (`php artisan test --compact`), Blade with plain JavaScript, built-in `ZipArchive` and `SimpleXML`/`XMLReader` (no new Composer package), Pint (`vendor/bin/pint --dirty --format agent`).

**Spec:** `vibe-app/docs/superpowers/specs/2026-10-11-sip-excel-import-design.md`. Read it with this plan.

## Global Constraints

- No new Composer or npm package. The workbook is read with `ZipArchive` and `SimpleXML`/`XMLReader` only.
- Only `.xlsx`; upload limit 5 MB; at most 12 MB of uncompressed sheet XML; at most 5,000 data rows; edited JSON payload at most 2,000,000 characters.
- Plan array shape (used everywhere): `['plan' => ['start_year' => int, 'planning_period' => string, 'school_name' => ?string], 'signatories' => ['prepared_by_name', 'prepared_by_position', 'recommended_by_name', 'recommended_by_position', 'approved_by_name', 'approved_by_position' (all ?string)], 'projects' => [['row' => ?int, 'pillar', 'kra', 'organizational_outcome', 'strategy', 'five_point_agenda', 'project', 'source_of_fund', 'activities' => [['row' => ?int, 'activity', 'physical' => [?float, ?float, ?float], 'financial' => [float, float, float], 'responsible_person', 'remarks']]]]]`. This is the JSON shape of `database/seed-data/sip-lubas-2026-2028.json` plus the optional `row` keys.
- Issue shape: `['level' => 'error'|'warning', 'row' => ?int, 'message' => string]`.
- Pillars are `App\Models\Aip::PILLARS` (`Access`, `Equity`, `Quality`, `Resiliency`, `Well-Being`, `Enabling Mechanism`). A blank physical target is stored as `null`; a blank financial amount as `0`.
- Duplicate rule and message are those of `sip:import` today: a school with programs for the same `school_year` and `planning_period` is refused and nothing is saved.
- The school, start year and period of a confirm come from the cached token, never from posted data. Token lifetime 30 minutes; bound to user id and school id.
- Routes are inside the existing authenticated group with `permission:planning.manage`. Unreadable files give an error issue, never a 500.
- Tests use `RefreshDatabase`. Multi-line edits to existing files go through a Python script file (heredocs with apostrophes break in this shell). Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Commit steps run only when the user has asked for commits; otherwise skip them. Push and deploy only when asked. `docs/specs.md` is protected: do not edit it; the owner decides in Task 5.

## Review Focus

- A workbook whose cells hold text numbers (`"1,500"`, `"₱1,500.00"`) imports; a cell with `abc` is an error naming the Excel row and column.
- HTML or script text in any cell (`<script>alert(1)</script>`) is escaped in the preview and never rendered as markup.
- Two Confirm requests for the same token (double click): one saves, the other is refused by the duplicate rule; nothing is doubled.
- A token used by another user, for another school, expired, or already used shows a clear message and saves nothing.
- A program with no activities, a plan whose title has no "FY ####-####", or a file that is not a real `.xlsx` (renamed text file, zip bomb, huge row count) gives a clear error and no partial save.

---

### Task 1: SipImportService and the refactor of `sip:import`

**Files:**
- Create: `app/Services/SipImportService.php`
- Modify: `app/Console/Commands/ImportSip.php`
- Test: `tests/Feature/SipImportServiceTest.php` (existing `tests/Feature/SipImportTest.php` must keep passing)

**Interfaces:**
- Produces `App\Services\SipImportService` with: `validate(array $plan): array` (list of issues, shape above); `alreadyImported(School $school, int $startYear, string $period): bool`; `save(School $school, array $plan, ?User $user): array{programs: int, activities: int}` (runs in one `DB::transaction`, creates `SipProject` + master transaction via `MasterTransactionService::create(..., 'sip', 'created')`, `SipActivity` rows and `SipPlan` signatories exactly as `ImportSip` does today; throws `ValidationException` with key `plan` and the existing duplicate message if `alreadyImported`).
- `validate` rules: `plan.start_year` int 2000-2100 and `planning_period` non-empty (error, row null); at least one project (error); per project: pillar in `Aip::PILLARS`, non-empty `kra` (max 255), `project` (max 255), optional strings within the lengths of the `sip_projects` columns, a project with no activities is a warning; per activity: non-empty `activity` text, each physical value null or a number >= 0, each financial a number >= 0, at most 5,000 activities in total. Every issue carries the `row` of the project or activity when present.

- [ ] **Step 1: Write failing tests** in `SipImportServiceTest`: `test_validate_accepts_the_lubas_json` (decode `database/seed-data/sip-lubas-2026-2028.json`, no error issues); `test_validate_reports_each_problem_with_its_row` (unknown pillar, empty kra, negative financial, empty activity text, a project with no activities as warning, 5,001 activities as error); `test_save_writes_programs_activities_signatories_and_a_master_transaction` (a small plan; assert `sip_projects`, `sip_activities` counts, `estimated_budget` = sum of financial, `SipPlan` names, `master_transaction_id` set, return value counts); `test_save_refuses_the_same_period_twice_and_changes_nothing` (second `save` throws `ValidationException`, counts unchanged); `test_save_is_all_or_nothing` (second activity with a too-long field makes the DB transaction roll back, zero rows saved).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SipImportServiceTest`. Expected: FAIL (class missing).
- [ ] **Step 3: Implement** `SipImportService` as specified. Move the saving code out of `ImportSip` (same columns: `fund_source` and each activity's `source_of_fund` from the program's `source_of_fund`; `created_by` = the given user's id).
- [ ] **Step 4: Refactor `ImportSip::handle`** to read the JSON, find the school, use `alreadyImported` for its existing error message and exit code, then `save` (user = the first `master_user`, as now). Output text unchanged.
- [ ] **Step 5: Run** `php artisan test --compact --filter="SipImportServiceTest|SipImportTest"`. Expected: PASS.
- [ ] **Step 6: Commit** `refactor: shared SipImportService for sip:import`.

### Task 2: SipWorkbookReader

**Files:**
- Create: `app/Services/SipWorkbookReader.php`, `tests/Support/XlsxBuilder.php`
- Test: `tests/Feature/SipWorkbookReaderTest.php`

**Interfaces:**
- Produces `App\Services\SipWorkbookReader::read(string $path): array{plan: array, issues: array}`. `plan` has the shape in Global Constraints (`projects` empty when nothing could be read). It never throws for a bad file: it returns an error issue.
- Produces test helper `Tests\Support\XlsxBuilder::make(array $sheets): string` returning the path of a minimal valid `.xlsx` in `sys_get_temp_dir()`. Each sheet: `['name' => string, 'hidden' => bool, 'cells' => array<string, string|int|float|array{f: string, v: float}>, 'merges' => string[]]`; strings are written as inline strings, an array cell as a formula with cached value `v`.
- Consumes: `Aip::PILLARS`.

- [ ] **Step 1: Write `XlsxBuilder`** (zip with `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, one `xl/worksheets/sheetN.xml` per sheet with `<mergeCells>` and `state="hidden"` support). Add a helper in the reader test, `sipSheet(array $overrides = [])`, that lays out a sample like the real file: title cells B8 "SCHOOL IMPROVEMENT PLAN", B9 "FY 2026-2028", B10 school name; headers in row 12 (B..Q) and Year sub-headers in row 13; data from row 14; a merged pillar cell `B14:B18`; program-level cells only on each program's first row; signature labels in row 143 and names/titles in rows 145 and 146 at different columns than the labels.
- [ ] **Step 2: Write failing tests** in `SipWorkbookReaderTest` (each asserts on `plan` and `issues`): `test_it_reads_the_sip_sheet_and_ignores_other_sheets` (a second visible sheet and a hidden sheet with SIP-like text are ignored); `test_it_finds_columns_by_header_text_not_by_letter` (shift all columns one to the right); `test_it_fills_down_merged_and_program_level_cells` (activity rows 2+ of a program get its pillar, kra, outcome, strategy, agenda, project, source of fund; a new project starts when the Program cell has text); `test_it_reads_formula_cells_by_their_stored_value` (`['f' => '=16000', 'v' => 16000]` gives 16000.0); `test_numbers_blank_text_and_bad_values` (blank financial = 0, blank physical = null, `"1,500"` and `"₱1,500.00"` accepted, `abc` is an error with the Excel row, a negative number is an error); `test_pillar_text_is_mapped_to_the_apps_pillars` (`ACCESS`, `Well-Being and Resilience` becomes `Well-Being`, `Resiliency`, `Enabling Mechanisms Governance` becomes `Enabling Mechanism`, `Banana` is an error with its row); `test_plan_period_comes_from_fy_text` (`FY 2026-2028` gives start_year 2026 and period `2026-2028`; missing gives an error); `test_signatories_are_matched_left_to_right` (names and titles for the three labels; only two names gives a warning and a blank third); `test_total_rows_and_empty_rows_are_skipped`; `test_a_row_with_numbers_but_no_activity_text_is_an_error`; `test_not_a_real_xlsx_gives_an_error_issue` (a renamed text file; a zip without `xl/workbook.xml`); `test_no_sip_table_gives_an_error_issue`; `test_limits_refuse_more_than_5000_rows` (builder with 5,001 data rows) .
- [ ] **Step 3: Run** `php artisan test --compact --filter=SipWorkbookReaderTest`. Expected: FAIL.
- [ ] **Step 4: Implement `SipWorkbookReader::read`.** Algorithm (decided here because the tests do not fix it): open with `ZipArchive`; refuse if `xl/workbook.xml` is missing or the total uncompressed size of `xl/worksheets/*.xml` exceeds 12 MB; map sheet names to files through `xl/_rels/workbook.xml.rels`; skip sheets with `state="hidden"`; load shared strings and inline strings; parse each candidate sheet into `[row][column letter] => value` plus the list of merged ranges (a merged range gives its top-left value to every cell inside it). Choose the sheet containing header cells matching "specific program", "activity" and "physical" (most data rows wins). The header row is the row with "activity"; the Year sub-header row is the next one; map columns by lowercase header text containing: pillar, kra, organizational outcome, strategy, 5-point, specific program, activity, physical, financial, source of fund, responsible, remarks (physical and financial give the first of three columns). Parse every XML with `LIBXML_NONET` and no DTD/entity loading. Walk data rows below the header until the signature label row; implement the fill-down, number, pillar and signatory rules of the spec. Use `row` = the Excel row number.
- [ ] **Step 5: Run** the filter. Expected: PASS.
- [ ] **Step 6: Commit** `feat: SipWorkbookReader reads the SIP sheet of an xlsx`.

### Task 3: Preview, token and confirm (controller and routes)

**Files:**
- Create: `app/Http/Controllers/SipImportController.php`
- Modify: `routes/web.php` (inside the group of the other `planning.sip.*` routes)
- Test: `tests/Feature/SipImportFlowTest.php`

**Interfaces:**
- Consumes: `SipWorkbookReader::read`, `SipImportService::{validate, alreadyImported, save}`, `FiscalYearService::assertOpen(int $organizationId, int $year)`, `AuditLog`.
- Produces routes (all `permission:planning.manage`): `planning.sip.import.preview` POST `/planning/sip/import` (fields `school_id`, `file`); `planning.sip.import.show` GET `/planning/sip/import/{token}`; `planning.sip.import.store` POST `/planning/sip/import/{token}` (field `payload` = JSON string of `{signatories, projects}`).
- Cache entry `sip-import:{token}` (30 minutes): `['user_id', 'school_id', 'plan' => [...], 'issues' => [...], 'original_hash' => string]`. The `show` view receives `plan`, `issues`, `school`, `token`, `schoolNameWarning` (file school name differs from the selected school).

- [ ] **Step 1: Write failing tests** in `SipImportFlowTest` (user = `school_admin` of the school; xlsx made with `XlsxBuilder`, uploaded with `UploadedFile`): `test_upload_shows_the_preview_and_writes_nothing` (redirect to `show`, page lists the program names and totals, `sip_projects` count 0); `test_confirm_saves_the_edited_plan` (post `payload` with a changed activity text, a changed financial amount, one program removed; assert saved values are the edited ones and the removed program is absent, `SipPlan` names from the payload, audit `sip_imported` with `edited` true and counts); `test_an_unedited_confirm_is_audited_as_not_edited`; `test_invalid_edits_are_refused_and_keep_the_edits` (empty program name, a negative number, an unknown pillar, malformed JSON, a payload over 2,000,000 characters: redirect back to `show`, session errors, nothing saved, the edited values still on the page); `test_the_payload_cannot_change_school_year_or_period` (extra `school_id`, `plan.start_year` keys are ignored; saved under the token's school and year); `test_errors_in_the_file_block_confirm_until_fixed` (file with an unknown pillar: confirm without fixing is refused; with the pillar fixed in the payload it saves); `test_a_second_import_of_the_same_period_is_refused` (and a double confirm of one token saves once); `test_the_token_is_bound_to_user_and_school_and_expires` (another user gets 404, `Carbon::setTestNow` +31 minutes gives the expired message, a used token gives the expired message); `test_wrong_file_type_and_oversize_are_refused` (`.pdf`, a 6 MB file); `test_only_planning_managers_may_import_and_only_for_their_school` (403 for a role without `planning.manage`; another organization's `school_id` is refused); `test_closed_fiscal_year_refuses_the_import` (existing `FiscalYearService` rule, same behavior as `storeSip`); `test_html_in_cells_is_escaped_in_the_preview` (`<script>alert(1)</script>` in an activity shows as `&lt;script&gt;`). `test_an_imported_program_and_activity_can_be_edited_and_deleted_afterwards` (after a confirm, `planning.sip.update`, `planning.sip.activities.update`, `planning.sip.activities.destroy` and `planning.sip.destroy` work on the imported rows exactly as on typed ones).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SipImportFlowTest`. Expected: FAIL.
- [ ] **Step 3: Implement the controller.** `preview`: authorize `planning.manage`; validate `school_id` is in the user's schools (same rule as `PlanningController::schoolIds`) and `file` is `required|file|mimes:xlsx|max:5120`; `read()` the temp file, delete the upload, store the cache entry, redirect to `show`. `show`: 404 unless the entry exists and matches user and school; render the preview view. `store`: same token checks; `payload` required string max 2,000,000; decode JSON (error back on failure); build the plan from the token's `plan.start_year`/`planning_period` plus the posted `signatories` and `projects` (ignore everything else); run `validate`; on error issues write the edited plan and issues back to the cache and redirect to `show` with the messages; on success `assertOpen`, `alreadyImported` check, `save`, forget the token, write the audit entry, redirect to `route('planning', ['school_id' => ..., 'year' => ...])#sip` with a success message. Escape nothing in the controller; Blade escapes.
- [ ] **Step 4: Add the three routes** with names above. The preview view is created in Task 4; for this task render a minimal `sip-import-preview` view that lists programs and totals.
- [ ] **Step 5: Run** the filter. Expected: PASS.
- [ ] **Step 6: Commit** `feat: SIP import preview and confirm endpoints`.

### Task 4: Editable preview screen and the Import button

**Files:**
- Create: `resources/views/sip-import-preview.blade.php` (replaces the minimal view of Task 3)
- Modify: `resources/views/planning.blade.php` (SIP panel header, next to "+ Add SIP Program", around line 163)
- Test: `tests/Feature/SipImportFlowTest.php` (page assertions) and a one-off browser check

**Interfaces:**
- Consumes: the view variables of Task 3 and the routes `planning.sip.import.preview/store`.
- Produces: page state in a JSON script element `#sip-plan` (`{plan, signatories, projects}`) and a Confirm form with hidden `payload`; element ids `sip-import-form`, `sip-import-confirm`, `sip-import-totals`.

- [ ] **Step 1: Write failing tests** (feature, markup only): `test_the_sip_panel_offers_import_to_planning_managers_only` (button text "Import from Excel" and the upload form action `planning.sip.import.preview` for a manager; absent for a viewer); `test_the_preview_page_carries_the_plan_the_issues_and_the_confirm_form` (JSON in `#sip-plan`, the issues list with Excel row numbers, `name="payload"`, a CSRF field, the school-name warning when the file's school differs, the Confirm button present).
- [ ] **Step 2: Run** the new tests. Expected: FAIL.
- [ ] **Step 3: Add the Import button** to the SIP panel: a small form with a file input (`accept=".xlsx"`) and hidden `school_id` = `$selectedSchool->id`, same visual style as the neighbouring buttons.
- [ ] **Step 4: Build the preview page** with plain JavaScript state rendered into an editable table: per program the pillar `<select>` (the six pillars) and text fields for KRA, outcome, strategy, 5-point agenda, program, source of fund; per activity text, three physical and three financial number inputs, responsible person, remarks; the six signatory fields; a Remove button on each program and each activity (confirm dialog; removing a program removes its activities); live counts and Year 1/2/3 totals; rows with an issue are highlighted with its message and the flag clears when the field becomes valid (client rules: required text, numbers >= 0, pillar in the list). Confirm is disabled while any error flag remains; on submit it writes `JSON.stringify({signatories, projects})` into `payload`. Escape all text through Blade/`textContent`, never `innerHTML` with data.
- [ ] **Step 5: Run** the feature tests. Expected: PASS. Then run `node --check` on the extracted script (render the page with a test that writes it to a temp file, as done for the other pages in this project) and verify in the browser pane with a stubbed page: edit a field, remove a program, see the totals change and the flag clear, confirm the posted `payload` contains the edits.
- [ ] **Step 6: Commit** `feat: editable SIP import preview`.

### Task 5: Final check and documentation

**Files:**
- Modify: none by default. `CLAUDE.md` gets one status line. `docs/specs.md` section 14 only if the owner approves exact text.

- [ ] **Step 1: Run** `vendor/bin/pint --dirty --format agent` and `php artisan test --compact`. Expected: no style changes left; all tests pass (424 existing plus the new ones).
- [ ] **Step 2: Real-file check.** Run the reader on the owner's sample (`C:\Users\Bonifacio Orong\Downloads\SIP 2026-2028.xlsx`) in a throwaway script outside the repository. Expected: 33 programs, 116 activities, financial totals 766,000 / 802,000 / 693,000, the three signatories, no error issues. Do not add the file to the repository.
- [ ] **Step 3: Add the `CLAUDE.md` status line** for the SIP Excel import.
- [ ] **Step 4: Final whole-branch review** by a fresh reviewer on the most capable model, using the Review Focus list verbatim; fix Critical and Important findings, each with a test that failed first.
- [ ] **Step 5: Ask the owner** for the exact text to add to `docs/specs.md` section 14 (show old and new text, wait for an explicit yes), then ask before committing, pushing and deploying (`railway up --detach --service procurems` from `vibe-app`, `MSYS_NO_PATHCONV=1` in Git Bash). No migration is needed.
