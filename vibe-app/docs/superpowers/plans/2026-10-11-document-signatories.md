# Document Signatories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every generating form (10 procurement documents, PR, ORS, DV) shows who will sign each signature slot and lets the user pick someone else for that one document.

**Architecture:** One catalog class (`App\Support\SignatorySlots`) lists the slots per document and resolves, validates and snapshots signers. Choices are saved as snapshots (staff id, name, position) with the document. Print methods compute today's defaults, then overlay the saved snapshots onto the same template variables, so the official templates do not change.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests (`RefreshDatabase`), Pint.

**Spec:** `docs/superpowers/specs/2026-10-11-document-signatories-design.md`

## Global Constraints

- Official templates keep their layout, captions and one signer per slot. Only the person printed in a slot changes. Do not edit `docs/specs.md` (protected).
- A choice applies to that one document only. It never changes School Settings roles.
- Only active staff of the document's school may be chosen (`SchoolStaff::active()`, school-scoped; ended staff are already hidden by the model's global scope).
- Saved value per slot: `{staff_id:int, name:string, position:string|null}`. Old documents never change when staff change.
- Blank choice (select value `""`, shown as "Default (Name)") saves nothing for that slot; the document keeps following School Settings.
- Re-saving with no `signatories` field present keeps earlier choices. Sending the field replaces the whole map.
- Permissions unchanged for each form. No new permission.
- Migrations are additive and nullable; nothing renamed or dropped.
- Follow the project rules: use `php artisan make:*`, run `vendor/bin/pint --dirty --format agent` before each commit, run the narrowest tests per task and the full suite before the final commit.
- Commit message ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

- Another school's staff id, an inactive staff id or an unknown id posted in `signatories` is refused with a validation error, never saved (Task 2).
- `signatories` posted as a string, a nested array or with unknown slot keys never causes a 500; unknown keys are ignored (Task 2).
- A person who is renamed or deactivated after the document was saved still prints with the saved name and position (Task 3).
- The preview endpoint (`previewProcurementDocument` saves inside a rolled-back transaction) shows the chosen signer and persists nothing (Task 3).
- On the Abstract, choosing only BAC Member 2 leaves rows 1 and 3 on their defaults and does not shift the others (Task 3).

## File Structure

- Create `app/Support/SignatorySlots.php`: the catalog, candidate ordering, validation/snapshot, overlay. One responsibility: who may sign which slot.
- Create `resources/views/partials/signatory-picker.blade.php`: renders the selects for one document; reused by all forms.
- Modify `app/Http/Controllers/HomeController.php` (procurement document store/print/forms, PR store/update/print, ORS store/print), `app/Http/Controllers/FinanceController.php` (DV).
- Modify views: `procurement-documents.blade.php`, `procurement-create.blade.php`, `budget.blade.php` (ORS form), `finance.blade.php` (DV form and options row).
- Create migrations (2) and modify `ProcurementRequest`, `LiquidationReport` models.
- Create `tests/Feature/DocumentSignatoriesTest.php` (catalog, procurement documents) and add PR/ORS/DV tests to it or to a sibling `DocumentSignatoriesFormsTest.php` if it grows past about 400 lines.

---

## Stage 1: Procurement documents

### Task 1: Slot catalog

**Files:**
- Create: `app/Support/SignatorySlots.php`
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Produces: `final class SignatorySlots` with
  - `public const DOCUMENTS` keyed by document key; values are lists of slots `['key' => string, 'label' => string, 'var' => string, 'role' => ?array{0:string,1:string}]` (`role` is `[group, roleName]` for `SchoolStaff::hasRole`, or `null` when the slot falls back to a position or `document_role` text match).
  - `public static function slots(string $document): array` returns the slots for a document key, or `[]`.
  - `public static function candidates(Collection $staff, array $slot): Collection` returns active staff, role holders of the slot first (stable by name), then the rest by name.
- Document keys: the 10 existing procurement `document_type` keys, plus `purchase_request`, `obligation_request`, `disbursement_voucher`.

Slots to define (`key => var`; labels are the form-side names):
- `request_for_quotation`: `bac_chair => bacChair`, `bac_secretariat => bacSecretariat`
- `abstract_of_bids_quotation`: `bac_chair => bacChair`, `bac_vice_chair => bacViceChair`, `bac_member_1`, `bac_member_2`, `bac_member_3` (all `var` = `bacMembers`; the overlay builds the collection by index)
- `notice_to_award`, `notice_to_proceed`: `school_head => schoolHead`
- `purchase_order`: `school_head => schoolHead`, `disbursing_officer => disbursingOfficer`
- `inspection_acceptance_report`: `inspection_officer => inspectionOfficer`, `property_officer => propertyOfficer`
- `inventory_acknowledgement_receipt_supplies`: `school_head => schoolHead`
- `requisition_issuance_slip`: `requesting_officer => requestingOfficer`, `school_head => schoolHead`, `property_officer => propertyOfficer`
- `inventory_custodian_slip`: `property_officer => propertyOfficer`, `procurement_officer => procurementOfficer`, `requesting_officer => requestingOfficer`
- `property_acknowledgement_receipt`: `prepared_by => requestingOfficer`, `approved_by => approver`
- `purchase_request`: `requested_by => requestingOfficer`, `approved_by => approver`
- `obligation_request`: `requester => requester`, `budget_officer => budgetOfficer`
- `disbursement_voucher`: `accountant => accountant`, `head => head`

Role for each slot is the lookup the controller already uses (e.g. `['procurement_role','Approver']`, `['bac_role','BAC Chairperson']`, `['bac_role','BAC Member']`); where the controller matches a `document_role` or position text (Disbursing Officer, Inspection Officer, Property Custodian, Accountant, School Head), use `null` and match by the same lowercase substring in `candidates()` (a `needle` key on the slot).

- [ ] **Step 1: Write the failing tests** in `DocumentSignatoriesTest`: `test_every_document_has_the_slots_the_spec_lists` (assert slot keys per document against the lists above, including 5 slots for the Abstract), `test_unknown_document_has_no_slots`, `test_candidates_list_role_holders_first_then_other_active_staff` (three staff: one holds `BAC Chairperson`, one is a plain employee, one inactive; assert order `[holder, other]` and that the inactive one is absent).
- [ ] **Step 2: Run** `php artisan test --filter=DocumentSignatoriesTest` — expect FAIL (class not found).
- [ ] **Step 3: Implement** `SignatorySlots::DOCUMENTS`, `slots()` and `candidates()` as specified.
- [ ] **Step 4: Run** the same filter — expect PASS.
- [ ] **Step 5: Commit** `feat: catalog of signature slots per official document`.

### Task 2: Validate and snapshot choices

**Files:**
- Modify: `app/Support/SignatorySlots.php`
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: Task 1 `slots()`.
- Produces:
  - `public static function validated(string $document, mixed $input, int $schoolId): array` returns `array<string, array{staff_id:int,name:string,position:?string}>` for the non-blank known slots. Throws `Illuminate\Validation\ValidationException` with error key `signatories.<slot key>` and message `That person cannot sign for this school.` when the id is not an active staff member of `$schoolId`. A non-array `$input`, unknown slot keys and blank values are ignored.
  - `public static function toStaff(array $snapshot): SchoolStaff` returns an unsaved `SchoolStaff` with `name` and `position` set (templates only read those).

- [ ] **Step 1: Write failing tests:** `test_validated_returns_snapshots_for_chosen_slots`; `test_blank_and_unknown_slots_are_ignored`; `test_a_string_or_nested_array_input_is_ignored_without_error` (inputs `'x'`, `['school_head' => ['a']]`); `test_another_schools_staff_is_refused` (assert `signatories.school_head` error); `test_an_inactive_or_unknown_staff_id_is_refused`.
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** `validated()` with one query: `SchoolStaff::active()->where('school_id', $schoolId)->whereIn('id', $ids)->get()->keyBy('id')`; `toStaff()` builds `new SchoolStaff(['name' => ..., 'position' => ...])`.
- [ ] **Step 4: Run** the filter — expect PASS.
- [ ] **Step 5: Commit** `feat: validate and snapshot chosen signatories`.

### Task 3: Save and print for the 10 procurement documents

**Files:**
- Modify: `app/Support/SignatorySlots.php` (add overlay), `app/Http/Controllers/HomeController.php` (`storeProcurementDocument`, `printProcurementDocument`)
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: Tasks 1-2.
- Produces: `public static function overlay(string $document, array $variables, ?array $saved): array` where `$variables` is the template variable array the print method already builds (`bacChair`, `schoolHead`, ...) and the return replaces any variable that has a saved slot with `toStaff($snapshot)`; for `bacMembers` it rebuilds the collection by index so only the chosen rows change (rows without a choice keep their default at the same index).

- [ ] **Step 1: Write failing tests:**
  - `test_a_saved_choice_prints_in_the_slot_for_each_document_type` (data provider over the 10 document types; create a `ProcurementDocument` with `metadata['signatories'][<first slot>]` for a staff member named "Pat Chosen" and a different default holder; GET `procurement.documents.print`; assert the response contains `PAT CHOSEN` or `Pat Chosen` and not the default's name in that slot — assert the default's name is absent when it is the only place it would print).
  - `test_without_a_choice_the_default_still_prints` (existing behaviour unchanged for one document).
  - `test_a_renamed_or_deactivated_signer_still_prints_from_the_snapshot`.
  - `test_saving_a_document_stores_the_chosen_signatories_in_metadata` (POST `procurement.documents.store` for an RFQ with `signatories[bac_chair]=<id>`; reuse the request setup from `ProcurementDocumentCenterTest`).
  - `test_resaving_without_the_signatories_field_keeps_earlier_choices` and `test_posting_blank_values_clears_choices`.
  - `test_the_preview_shows_the_chosen_signer_and_saves_nothing` (POST `procurement.documents.preview`; afterwards `ProcurementDocument` count unchanged).
  - `test_only_the_chosen_bac_member_row_changes_on_the_abstract` (choose `bac_member_2`; rows 1 and 3 keep their defaults).
  - `test_a_signer_from_another_school_is_refused_on_save` (422/redirect with errors, nothing saved).
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** `overlay()`; in `storeProcurementDocument` call `SignatorySlots::validated($data['document_type'], $request->input('signatories'), $procurementRequest->school_id)` only when `$request->has('signatories')`, and put the result in `metadata['signatories']` (else carry over `$existingDocument->metadata['signatories'] ?? null`); in `printProcurementDocument` wrap the variable array in `overlay($procurementDocument->document_type, [...], $procurementDocument->metadata['signatories'] ?? null)` before `view(...)`.
- [ ] **Step 4: Run** the filter and `--filter="OfficialPrintTest|ProcurementDocumentCenterTest|ProcurementOfficialToolbarTest"` — expect PASS.
- [ ] **Step 5: Commit** `feat: procurement documents save and print chosen signatories`.

### Task 4: Signatories picker in the document modal

**Files:**
- Create: `resources/views/partials/signatory-picker.blade.php`
- Modify: `app/Http/Controllers/HomeController.php` (`procurementDocuments`), `resources/views/procurement-documents.blade.php`
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: `slots()`, `candidates()`.
- Produces: the partial takes `$document` (key), `$slots` (from `slots()`), `$options` (`slot key => ['default' => ?SchoolStaff, 'candidates' => Collection]`) and `$saved` (`slot key => snapshot`). It renders, per slot, a labelled `<select name="signatories[<slot key>]">` whose first option has value `""` and text `Default (<name or 'none set'>)`, then the candidates; the saved person is selected. `procurementDocuments` passes `signatoryOptions` and saved choices per document type; the modal shows only the section of the document being made, using the existing `data-*-only` show/hide pattern, and reopening a saved document selects its saved people.

- [ ] **Step 1: Write failing tests:** `test_the_document_page_shows_a_signatories_select_per_slot` (GET `procurement.documents`; assert `name="signatories[bac_chair]"` and `name="signatories[disbursing_officer]"` present); `test_role_holders_are_listed_before_other_staff_in_the_select` (`assertSeeInOrder` of the holder then the other employee inside one select); `test_the_default_option_names_the_current_default_person`.
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** the partial and wire it into the modal form (`#document-form`), with each document's section wrapped so only the active type's selects are enabled (disable the others so they are not posted); saved choices come from the `savedMetadata` data the page already embeds.
- [ ] **Step 4: Run** the filter and the procurement document tests — expect PASS. Check the modal in the browser preview for RFQ, PO and IAR: the section appears, a choice persists after saving and reopening, and the printed document shows it.
- [ ] **Step 5: Commit** `feat: pick signatories in the procurement document form`.

---

## Stage 2: Purchase Request

### Task 5: PR signatories

**Files:**
- Create: migration `add_signatories_to_procurement_requests_table` (`php artisan make:migration`, nullable `json signatories`)
- Modify: `app/Models/ProcurementRequest.php` (fillable + `'signatories' => 'array'` cast), `app/Http/Controllers/HomeController.php` (`validateProcurement` rule, `storeProcurement`, `updateProcurement`, `procurementPrintView`), `resources/views/procurement-create.blade.php`
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: Tasks 1-4. Document key `purchase_request`; slots `requested_by`, `approved_by`.
- Produces: `procurement_requests.signatories` (array|null). `procurementPrintView` applies `overlay('purchase_request', ['requestingOfficer' => ..., 'approver' => ...], $procurementRequest->signatories)` before computing `prepared_by`, `prepared_by_role`, `approved_by`, `approved_by_role`.

- [ ] **Step 1: Write failing tests:** `test_the_pr_prints_the_chosen_requested_by_and_approved_by`; `test_a_new_pr_saves_chosen_signatories`; `test_editing_a_pr_without_the_field_keeps_them`; `test_another_schools_staff_is_refused_on_the_pr`; `test_the_pr_form_shows_the_signatories_selects`.
- [ ] **Step 2: Run** `--filter=DocumentSignatoriesTest` — expect FAIL.
- [ ] **Step 3: Implement** the migration, model change, validation through `SignatorySlots::validated('purchase_request', ...)` (only when `$request->has('signatories')`), saving in create and update, the print overlay and the picker section in the PR form.
- [ ] **Step 4: Run** the filter, `ProcurementRequestFormTest` and `OfficialPrintTest` — expect PASS; run `php artisan migrate` check via the test database.
- [ ] **Step 5: Commit** `feat: choose the PR signatories`.

---

## Stage 3: ORS and DV

### Task 6: ORS signatories

**Files:**
- Create: migration `add_signatories_to_liquidation_reports_table` (nullable `json signatories`)
- Modify: `app/Models/LiquidationReport.php` (fillable + array cast), `app/Http/Controllers/HomeController.php` (`storeLiquidation`, `printOrs`), `resources/views/budget.blade.php` (the ORS form at the `liquidation.store` action)
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: Tasks 1-4. Document key `obligation_request`; slots `requester`, `budget_officer`.
- Produces: `liquidation_reports.signatories` shaped `['ors' => [slot => snapshot], 'dv' => [slot => snapshot]]`. `printOrs` applies `overlay('obligation_request', ['requester' => ..., 'budgetOfficer' => ...], $report->signatories['ors'] ?? null)`.

- [ ] **Step 1: Write failing tests:** `test_the_ors_prints_the_chosen_requester_and_certifier`; `test_a_new_ors_saves_the_chosen_signatories_under_the_ors_key`; `test_another_schools_staff_is_refused_on_the_ors`; `test_the_ors_form_shows_the_signatories_selects` (GET `budget`).
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** the migration, cast, `storeLiquidation` validation and save (when `signatories` present), `printOrs` overlay and the picker in the Budget page ORS form.
- [ ] **Step 4: Run** the filter, `OfficialPrintTest` and `--filter=Liquidation` — expect PASS.
- [ ] **Step 5: Commit** `feat: choose the ORS signatories`.

### Task 7: DV signatories

**Files:**
- Modify: `app/Http/Controllers/FinanceController.php` (`createDv`, `updateDvOptions`, `printDv`), `resources/views/finance.blade.php` (DV dialog `#dv-form` and the options row form `accounting.dv.options`)
- Test: `tests/Feature/DocumentSignatoriesTest.php`

**Interfaces:**
- Consumes: Task 6's column and `signatories['dv']`; document key `disbursement_voucher`; slots `accountant`, `head`.
- Produces: `createDv` and `updateDvOptions` save `signatories['dv']` (preserving `signatories['ors']`) when the field is present; `printDv` overlays `['accountant' => ..., 'head' => ...]`.

- [ ] **Step 1: Write failing tests:** `test_the_dv_prints_the_chosen_accountant_and_head`; `test_creating_a_dv_saves_the_chosen_signatories_and_keeps_the_ors_ones`; `test_the_dv_options_request_can_change_the_signatories`; `test_another_schools_staff_is_refused_on_the_dv`; `test_the_accounting_page_shows_the_dv_signatories_selects`.
- [ ] **Step 2: Run** the filter — expect FAIL.
- [ ] **Step 3: Implement** save, update, print overlay and the pickers in the DV dialog and in the options row form.
- [ ] **Step 4: Run** the filter, `DvJournalEntryTest` and `OfficialPrintTest` — expect PASS.
- [ ] **Step 5: Commit** `feat: choose the DV signatories`.

### Task 8: Final verification

- [ ] **Step 1:** Run `vendor/bin/pint --dirty --format agent`, then the full suite `php artisan test` — expect all passing.
- [ ] **Step 2:** In the browser preview, generate one document of each kind with a changed signer and confirm the printed form shows it with the official layout unchanged.
- [ ] **Step 3:** Do not edit `docs/specs.md`. If a standing rule there is now out of date, report it to the owner instead.
- [ ] **Step 4:** Update the status list in `CLAUDE.md` with one line for this feature (match the style of the existing 2026-10-11 lines). Commit `docs: note the document signatories in CLAUDE.md status`.
