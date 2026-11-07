# Print Settings Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Printing tab in School Settings where a school sets one default margin set and optional margins per document, applied by every printable page.

**Architecture:** A `print_settings` table (one default row per school, optional rows per document key). A small service resolves them; the shared print engine (`partials/print-clean`) reads them as JSON and now supports four independent margins; the five report pages that print with their own CSS read the default through a tiny partial. The tab saves through one PUT guarded by the Reason + PIN window.

**Tech Stack:** Laravel 13, Blade, vanilla JS, Pest/PHPUnit feature tests with `RefreshDatabase`.

**Spec:** `vibe-app/docs/superpowers/specs/2026-11-07-print-settings-manager-design.md`

## Global Constraints

- Margins in millimetres, 0 to 40, one decimal (`decimal(5,1)`); order everywhere: top, right, bottom, left.
- A school with no rows prints exactly as today (existing print tests pass unchanged).
- Documents laid out page by page (`data-sheets`: SOB, APP, AIP, SIP, POW, Alphalist, procurement documents) never print with top or bottom below 13 mm.
- ORS and DV (no `data-margin` in their file) do not take the school default; only their own row applies.
- Choice order: the document's own row, then the school default (only for documents that have `data-margin`), then the file's `data-margin`.
- Permission `organization.settings`; saving needs the Reason + PIN (`RequireActionPin::ROUTES`); the change goes to the audit log with old and new values.
- UI text wraps inside its box (CLAUDE.md "No overlapping, clipped or overflowing text"); check at 320, 768 and 1280 px; boxes in a row stay aligned.
- Minimal change: do not reformat untouched files; run Pint only on files created or edited. Official documents' content is not changed.
- Commit and push after each task; do not deploy without the owner's word.

## Review Focus

- A blank margin box means "use the default", never 0 (test in Task 2).
- A value above 40, below 0 or not a number is refused and nothing is saved (Task 2).
- A school never sees or gets another school's settings (Task 2).
- A school default set to 25 mm does not move the ORS or DV; a row for `ors` does (Task 3).
- A page-by-page document with margin 0 entered still prints with at least 13 mm top and bottom (Task 3).
- A master user (no school) opens a print page and the engine still works without settings (Task 3).

## File Structure

- Create `database/migrations/2026_11_08_000001_create_print_settings_table.php` — the table.
- Create `app/Models/PrintSetting.php` — row model (tenancy trait).
- Create `app/Services/PrintSettings.php` — document catalogue, reading, saving.
- Create `app/Http/Controllers/PrintSettingsController.php` — the PUT.
- Modify `routes/web.php`, `app/Http/Middleware/RequireActionPin.php` — route and guard.
- Modify `resources/views/partials/print-clean.blade.php`, `partials/print-pages.blade.php` — four margins and the settings JSON.
- Create `resources/views/partials/print-margin.blade.php`; modify the five report views to include it.
- Modify `resources/views/alphalist-print.blade.php` — add `data-doc="alphalist"`.
- Modify `app/Http/Controllers/HomeController.php` (`schoolSettings`), `resources/views/school-settings.blade.php`; create `resources/views/partials/settings/printing.blade.php`.
- Tests: `tests/Feature/PrintSettingsTest.php`, `tests/Feature/PrintSettingsEngineTest.php`, `tests/Feature/PrintSettingsTabTest.php`.
- Docs: `docs/specs.md` (section 119 bullet), `docs/STATUS_HISTORY.md`.

---

### Task 1: Table, model and service

**Files:**
- Create: `database/migrations/2026_11_08_000001_create_print_settings_table.php`, `app/Models/PrintSetting.php`, `app/Services/PrintSettings.php`
- Test: `tests/Feature/PrintSettingsTest.php`

**Interfaces:**
- Produces:
  - `App\Models\PrintSetting` (fillable `organization_id, school_id, doc_key, margin_top, margin_right, margin_bottom, margin_left`; `BelongsToOrganization`).
  - `App\Services\PrintSettings::DOCUMENTS` — `array<string,string>` key to label for: `aip, alphalist, app, app-cse, cash-bank-recon, cash-checks-advices, cash-register, cash-roci, dv, ors, pow, pr, reconciliation, sip, sob, rfq, po, ntp, noa, abstract, iar, ris, iars, ics, generic`.
  - `PrintSettings::forSchool(?int $schoolId): array` returning `['default' => ?array{top:float,right:float,bottom:float,left:float}, 'documents' => array<string, array{top,right,bottom,left}>]`; `['default' => null, 'documents' => []]` for a null school or no rows.
  - `PrintSettings::pageMargin(?int $schoolId): ?array` — the default or null.
  - `PrintSettings::save(School $school, ?array $default, array $documents): array` — replaces the school's rows (a null/absent set deletes its row); returns `['before' => forSchool-shape, 'after' => forSchool-shape]` for the audit log.

- [ ] **Step 1: Write the failing tests** in `PrintSettingsTest`: `test_a_school_without_rows_has_no_settings` (`forSchool($school->id)` equals `['default'=>null,'documents'=>[]]`, and `forSchool(null)` too); `test_save_stores_a_default_and_a_document_row` (save default 15,10,15,10 and `sob` 12,8,14,8; `forSchool` returns both with floats); `test_save_replaces_and_removes_rows` (a second save with `documents=[]` removes the `sob` row; a null default removes the default row); `test_settings_are_kept_per_school` (school B sees none of school A's).
- [ ] **Step 2: Run** `php artisan test --compact --filter=PrintSettingsTest` — expect FAIL (class missing).
- [ ] **Step 3: Create the migration** (columns per spec, `unique(['school_id','doc_key'])`, foreign keys with cascade on delete; note a unique index treats NULL `doc_key` as distinct, so the service must update the default row rather than insert twice), the model, and the service. `save` runs in one transaction and uses `updateOrCreate` for the default row (`doc_key` null).
- [ ] **Step 4: Run** the filter again — expect PASS.
- [ ] **Step 5: Pint** the three new PHP files; **commit** `feat: print_settings table, model and service`.

### Task 2: Saving endpoint, permission, guard and audit

**Files:**
- Create: `app/Http/Controllers/PrintSettingsController.php`
- Modify: `routes/web.php` (after the `school-settings.school` routes), `app/Http/Middleware/RequireActionPin.php` (`ROUTES`)
- Test: `tests/Feature/PrintSettingsTest.php`

**Interfaces:**
- Consumes: `PrintSettings::save`, `PrintSettings::DOCUMENTS`.
- Produces: route `PUT /school-settings/printing` named `school-settings.printing.update` with `permission:organization.settings`; request fields `school_id`, `default[top|right|bottom|left]`, `documents[<key>][top|right|bottom|left]`; redirects to `route('school-settings', ['ui'=>'staff-save-v7','school_id'=>$id,'tab'=>'printing'])` with a success flash.

- [ ] **Step 1: Write the failing tests** (a school_admin user with a paid subscription as in `SchoolSettingsTest`): `test_a_default_and_a_document_row_are_saved` (PUT with `default` 15,10,15,10 and `documents.sob` 12,8,14,8; rows exist; an audit log row `school_settings_printing_update` has `metadata.before/after`... use the key the controller writes: `print_settings_changed`); `test_blank_boxes_mean_use_the_default` (`documents.sob` all blank → no `sob` row; a default with all four blank → no default row); `test_a_value_out_of_range_or_not_a_number_is_refused` (41, -1, `abc` each give `assertSessionHasErrors` and no rows); `test_an_unknown_document_key_is_refused`; `test_a_viewer_without_the_permission_is_forbidden` (role `auditor`); `test_another_schools_id_is_refused` (user of school A sends school B's `school_id` → 403 or 404 and no rows for B); `test_it_asks_for_the_reason_and_pin` (set `$withoutActionPin = true` in a separate test class or the same class property only for this test: PUT without `pin` gets `assertSessionHasErrors(['pin','reason'])`).
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** `PrintSettingsController::update(Request $request): RedirectResponse`. Validation: `school_id` required and in the user's school ids (same scoping `HomeController::updateSchoolDetails` uses); each margin `nullable|numeric|between:0,40`; `documents` keys must be in `PrintSettings::DOCUMENTS`. A set whose four boxes are all blank is treated as absent; a set with some boxes filled and some blank fails validation ("fill all four margins or none"). Write `AuditLog` with action `print_settings_changed` and the before/after from `save`. Add the route name to `RequireActionPin::ROUTES`.
- [ ] **Step 4: Run** the filter — expect PASS; also `php artisan test --compact --filter=ActionPin` stays green.
- [ ] **Step 5: Pint** changed PHP files; **commit** `feat: save the print settings of a school (guarded, audited)`.

### Task 3: Print engine uses the settings (four margins) and the report pages

**Files:**
- Modify: `resources/views/partials/print-clean.blade.php`, `resources/views/partials/print-pages.blade.php`, `resources/views/alphalist-print.blade.php`, `resources/views/{allotment-registry,budget-item-show,budget-report,financial-reports,generated-report}.blade.php`
- Create: `resources/views/partials/print-margin.blade.php`
- Test: `tests/Feature/PrintSettingsEngineTest.php`

**Interfaces:**
- Consumes: `PrintSettings::forSchool`, `PrintSettings::pageMargin`.
- Produces:
  - In `print-clean`: `<script type="application/json" id="print-settings">{"default":{...}|null,"documents":{...}}</script>` rendered from `PrintSettings::forSchool($printSchoolId ?? auth()->user()?->school_id)`; `@page` margin written as four values `T R B L`; the sheet's `--sheet-px`/`--sheet-py` replaced by `--sheet-pl`, `--sheet-pr`, `--sheet-pt`, `--sheet-pb`.
  - In `print-pages`: the `spec` regex parses four margins (`spec[3]`=top, `spec[4]`=right, `spec[5]`=bottom, `spec[6]`=left); every `ph - 2*spec[3]` becomes `ph - spec[3] - spec[5]` and `pw - 2*spec[4]` becomes `pw - spec[4] - spec[6]`.
  - `partials/print-margin.blade.php` outputs `<style>@page { margin: T R B L }</style>` from `PrintSettings::pageMargin(auth()->user()?->school_id)` and nothing when there is no default.

- [ ] **Step 1: Write the failing tests** in `PrintSettingsEngineTest`: `test_a_print_page_carries_the_school_settings_json` (save default 15,10,15,10 for the user's school; GET `route('planning.sob.print', $sob)` or the simplest existing print route used by `SobPrintDesignTest`; `assertSee('id="print-settings"', false)` and the JSON contains `"top":15`); `test_a_school_without_settings_still_renders_the_engine` (JSON has `"default":null`); `test_a_master_user_without_a_school_still_prints` (page returns 200); `test_the_report_pages_take_the_default_margin` (for `route('budget.allocation.report', ...)` or the Allotment Registry route used in its existing test: `assertSee('@page', false)` with the margin, and not when no default); `test_the_alphalist_page_has_its_document_key` (`data-doc="alphalist"`).
- [ ] **Step 2: Run** `php artisan test --compact --filter=PrintSettingsEngine` — expect FAIL.
- [ ] **Step 3: Engine change.** In `print-clean` JS replace `baseMargin`/`marginX`/`marginY` with `mTop, mRight, mBottom, mLeft` chosen by `marginsFor(doc, fileMargin, paged)`: the document's row, else the default when `data-margin` is present on the page, else the file value for all four; then, when any page has `data-sheets`, `mTop = max(mTop, 13)` and `mBottom = max(mBottom, 13)` (this replaces the current single `marginY` floor), and the existing `flows` minimum of 8 mm applies to top and bottom. Keep all existing zoom and fill calculations working with `mLeft + mRight` and `mTop + mBottom`. Update `print-pages` as specified. Add `data-doc="alphalist"` to the alphalist page element. Include `print-margin` in the five report views next to their existing `<style>` block.
- [ ] **Step 4: Run** the filter, then the print-related suites: `php artisan test --compact --filter="Print|Sob|Aip|Official|Sip|Pow|App|Alphalist|Cash"` — expect PASS.
- [ ] **Step 5: Manual verification with Chrome.** Render the SOB, AIP, DV and APP print pages to HTML and print each with `chrome --headless=new --print-to-pdf`; expect the same page counts as before the change with no settings (SOB 1, AIP 11, DV 1, APP 1, no page without text), and with a default of 15,10,15,10 saved for the school the AIP prints without a footer-only last page. If either fails, fix before continuing.
- [ ] **Step 6: Commit** `feat: print engine and report pages read the school's print settings`.

### Task 4: The Printing tab

**Files:**
- Modify: `app/Http/Controllers/HomeController.php` (`schoolSettings`: pass `$printSettings = PrintSettings::forSchool($selectedSchool?->id)` and `$printDocuments = PrintSettings::DOCUMENTS`), `resources/views/school-settings.blade.php` (tab `'printing' => ['Printing','print']` and the `@elseif($tab === 'printing')` include)
- Create: `resources/views/partials/settings/printing.blade.php`
- Test: `tests/Feature/PrintSettingsTabTest.php`

**Interfaces:**
- Consumes: route `school-settings.printing.update`, the field names from Task 2, `partials.action-guard` (form carries `data-guard="Save the print settings"`).

- [ ] **Step 1: Write the failing tests**: `test_the_printing_tab_shows_the_default_and_every_document` (GET `route('school-settings', ['ui'=>'staff-save-v7','school_id'=>$id,'tab'=>'printing'])` → `assertOk()`, sees `Printing`, `name="default[top]"`, `name="documents[sob][top]"`, `data-guard="Save the print settings"`, and the 13 mm note text); `test_saved_values_are_shown_in_the_boxes` (a saved default 15 appears as `value="15"` or `15.0` — assert the exact format the view prints); `test_the_tab_is_not_offered_to_a_user_without_the_permission` (auditor: the tab link is absent or the form is read-only — follow how the other tabs treat `organization.settings`).
- [ ] **Step 2: Run** — expect FAIL.
- [ ] **Step 3: Build the tab.** Four labelled number boxes for the default (step 0.5, min 0, max 40, mm), aligned in one row; a note under the boxes (the note must not move the boxes); a table of the documents with four small boxes each (placeholder = the current default or the file value, label "Uses the default" when blank) and a "Use default" button that clears that row's boxes; a sheet drawing (CSS box) that follows the default boxes as they are typed (small inline script; no build step). Labels tied to inputs, buttons 44 px high, text wraps (`min-w-0 break-words`), table scrolls inside its own container, `prefers-reduced-motion` respected. The page uses the existing `$input` class and hidden `school_id`, `_method=PUT`.
- [ ] **Step 4: Run** the filter, then `php artisan test --compact --filter=SchoolSettings` — expect PASS.
- [ ] **Step 5: Check the layout** at 320, 768 and 1280 px (headless Chrome screenshots of the tab with a long document name and values like 40.0); fix any overlap; say so if a browser check was not possible.
- [ ] **Step 6: Pint** changed PHP files; **commit** `feat: Printing tab in School Settings`.

### Task 5: Docs, full run and handoff

**Files:**
- Modify: `docs/specs.md` (append a bullet to section 119 as the previous ones), `docs/STATUS_HISTORY.md` (one dated bullet, "Committed, not yet deployed"), `CLAUDE.md` only if the "Live:" line changes (it does not until deploy).

- [ ] **Step 1: Append the spec bullet and the status bullet** describing the table, the Printing tab, the choice order, the 13 mm floor, the five report pages and the tests.
- [ ] **Step 2: Run the full suite** `php artisan test --compact` — expect all tests pass (previously 1,267 plus the new ones).
- [ ] **Step 3: Commit** the code in its own commit and the docs as `specs:`; push; report the per-step checklist to the owner (commit, specs, push, deploy not done) and ask whether to deploy. The live database needs the new migration, which `railway up` applies on start.
