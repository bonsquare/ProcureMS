# SIP import from Excel: design

Date: 2026-10-11. Status: design approved in chat, written spec awaiting review.

## Goal

A school can enter its School Improvement Plan (SIP) by uploading the Excel file it already has, instead of typing hundreds of programs and activities. The user sees a preview of what was understood, then confirms, and the plan is saved in one step.

## Problem and evidence

- The SIP is a three-year plan with many long text cells; typing it takes a very long time.
- The sample `SIP 2026-2028.xlsx` (Lubas Elementary School) was reviewed read-only. Its sheet **"school Improvement Plan"** is the source of the printed PDF and has the official columns in B to Q: Pillar, KRA, DepEd Organizational Outcomes, Strategy, 5-Point Agenda, Specific Program / Project, Activity, Physical Targets Year 1-3 (I-K), Financial Target Year 1-3 (L-N), Source of Fund, Responsible Person, Remarks. Header rows are 12 and 13; data starts at row 14 and ends at row 141 in the sample.
- The plan title ("FY 2026-2028") and the school name are at the top (B9, B10). The three signatories are at the bottom: labels in row 143 ("Prepared by", "Recommending Approval", "Approved by"), names in row 145, titles in row 146.
- The pillar cell is merged down many rows. Program-level cells (KRA, outcome, strategy, agenda, program, source of fund) appear only on the first activity row of each program. About 24 financial cells are formulas (for example `=16000`) whose computed value is stored in the file.
- The workbook has ten sheets. Only the SIP sheet is imported; the pillar sheets, "Annual Implementation Plan" and the rest are ignored.
- Existing: `php artisan sip:import <file.json> <school>` enters a plan from JSON (`plan`, `signatories`, `projects` with `activities`). The Lubas plan (33 programs, 116 activities) was entered this way. The SIP tables and print template already match the official columns.
- The `zip`, `SimpleXML` and `xml` PHP extensions are present locally and in the Railway image (`install-php-extensions ... zip`). No Composer package is needed.

## Decisions taken in chat

- File type: **Excel `.xlsx`**, the original source of the PDF. PDF is not supported (merged cells make rows ambiguous; scans would not work). CSV, `.xls` and Word are out of scope.
- Approach A: read the school's own Excel file as it is, finding the sheet and columns by their header text. No template is required. No new Composer package.
- A downloadable blank template is not part of this work.
- A plan that already exists for the school, year and period is refused (as `sip:import` does). No merge or replace.

## User flow

1. Planning, SIP panel: an **Import from Excel** button for the selected school. Visible to users with `planning.manage`.
2. Upload `.xlsx` (maximum 5 MB). The server parses it and writes nothing to the SIP tables.
3. **Preview page (editable):** plan period (read only), school name found in the file next to the selected school, running counts of programs and activities, Year 1/2/3 financial totals that update as numbers change, the three signatories, and the programs with their activities. **Every field of every program and activity can be edited** in place (pillar from a list, KRA, outcome, strategy, 5-point agenda, program, source of fund; activity text, the three physical and three financial targets, responsible person, remarks), and the three signatory names and titles. A program or an activity can be **removed** (with a confirmation; removing a program removes its activities). Adding a new program or activity is not part of the preview (do it in the SIP lists after importing). Problems found in the file are listed per row with the Excel row number and marked on the row; fixing a field clears its flag as soon as it is valid. **Errors** block Confirm until they are fixed or the row is removed; **warnings** do not.
4. **Confirm import** sends the edited plan to the server, which validates it again from scratch with the same rules as manual entry (the browser is never trusted), then saves everything in one database transaction (all or nothing). If something is invalid the preview opens again with the user's edits kept and the messages shown, and nothing is saved. The preview stays available server-side for 30 minutes under a random token tied to the user, the school and the plan year and period, then expires.
5. After saving: back to the SIP panel with a success message, and a `sip_imported` audit entry with the counts.

## Components

- `App\Services\SipWorkbookReader`: file path in, normalized array out (same shape as the JSON of `sip:import`: `plan`, `signatories`, `projects[].activities[]`) plus a list of `issues` (`level` error or warning, `row`, `message`). No database access.
- `App\Services\SipImportService`: `validate(array $plan): array` returns the issues for a normalized plan (used on the parsed file and again on the edited plan at Confirm, so there is one set of rules), and `save(School $school, array $plan, ?User $user)` writes it (programs, master transactions, activities, signatories, audit). Used by both the web import and `sip:import`. `ImportSip` is changed to call it; its existing tests (`SipImportTest`) must keep passing.
- `App\Http\Controllers\SipImportController`: `preview` (upload) and `store` (confirm). Routes under Planning, `permission:planning.manage`.
- A Blade partial for the editable preview and the button on the SIP panel. The preview holds the plan as JavaScript state, renders the editable table, and on Confirm sends the whole plan as **one JSON field** (not hundreds of form fields: PHP's `max_input_vars` of 1,000 would silently cut a plan of 116 activities with 12 fields each). The JSON is limited to 2 MB.

## Reading the workbook

- Open with `ZipArchive`; read `xl/workbook.xml`, `xl/sharedStrings.xml`, sheet XML and merged-cell ranges with `SimpleXML`/`XMLReader`. Formula cells use the stored value.
- Sheet choice: the sheet whose cells contain the headers "Specific Program" and "Activity" and "Physical". If none, an error ("No SIP table found"). If several, the one with the most data rows.
- Columns are located from the header text (case-insensitive, trimmed, line breaks removed): pillar, KRA, organizational outcome, strategy, 5-point, specific program, activity, physical, financial, source of fund, responsible, remarks. The Year 1 to 3 sub-headers sit one row below the Physical and Financial headers.
- Fill-down: pillar, KRA, outcome, strategy, agenda, program and source of fund carry down until the next non-empty cell. A merged range gives its top-left value to every row in the range. A new program starts when the Program cell has text.
- Numbers: a blank financial amount is 0 and a blank physical target stays empty (as `sip:import` stores it). Text such as "1,500" or "₱1,500.00" (thousands separators, peso sign, spaces) is accepted as a number. Anything else that is not a number, or a negative number, is an error on that row. A row with numbers but no activity text is an error. Rows whose first cell starts with "TOTAL" are skipped.
- Pillar text is mapped to the application's names by keyword, case-insensitive: contains "access" gives Access, "equity" gives Equity, "quality" gives Quality, "well" gives Well-Being (so "Well-Being and Resilience" is Well-Being), "resilien" without "well" gives Resiliency, "enabling" gives Enabling Mechanism. These are the application's own pillars (`Aip::PILLARS`). Anything else is an error on that row.
- Plan year and period come from "FY 2026-2028" (start year 2026, period `2026-2028`); if absent the user cannot confirm (error).
- Signatories: find the row holding the labels "Prepared by", "Recommending Approval" and "Approved by" (in the sample row 143, columns B, E and J). The names are the non-empty cells on the row two below it and the titles the non-empty cells on the row three below it, taken left to right and matched to the three labels in order (in the sample C, E, M of rows 145 and 146; the columns differ from the labels because of merged cells). If fewer than three names are found: a warning, the missing ones left blank.

## Saving rules

- Same fields and master-transaction creation as `ImportSip` today (`sip_projects`, `sip_activities`, `sip_plans`); the same duplicate check: the school already has programs for that start year and planning period means refuse with the existing message and nothing is saved.
- The user must be allowed to manage planning for the selected school (organization and school scope as elsewhere). The master user may import for any school. School comes from the selected school, not from the file; a different school name in the file is a warning.
- Audit log action `sip_imported` with school, programs and activities counts, and whether the user edited or removed anything in the preview.
- **The edited plan is untrusted input.** At Confirm the server decodes the JSON and validates it completely: structure and types, a pillar from the allowed list, non-empty KRA, program and activity text, lengths as in the SIP tables, every target a number of 0 or more, at most 5,000 activities, and the three signatories as text. The school, year and period are taken from the token, never from the posted data. Nothing is saved unless all of it is valid.
- **Editing after the import:** imported programs and activities are ordinary SIP records (same tables, nothing marks them as imported or read-only). They are edited and deleted afterward exactly like typed ones, through the existing Planning SIP lists and their rules (a program is locked while an AIP is linked to it; an activity is locked when the fiscal year is closed). Import changes none of these rules, and a test confirms an imported program and activity can be edited and deleted.

## Safety and limits

- Only `.xlsx` (checked by extension, MIME and by opening as a zip with `[Content_Types].xml`). Maximum upload 5 MB; maximum uncompressed size of the sheets 12 MB (the server's PHP memory limit is 256 MB); maximum 5,000 data rows; refuse otherwise.
- XML parsed with network access off and no external entities or DTD loading.
- The uploaded file is deleted as soon as it is parsed; only the normalized result is held for 30 minutes (cache, keyed by token, user id and school id).
- All text is escaped on output. Confirm re-checks permission, school scope and the duplicate rule at save time.

## Tests (each fails first)

- Reader, with a small `.xlsx` built in the test (merged pillar, program-level cells only on the first row, a formula cell, a hidden extra sheet, a Total row, bad numbers, a missing signatory): fill-down, formulas, pillar mapping, errors and warnings with row numbers.
- Feature: upload shows the preview and writes nothing; confirm saves programs, activities, signatories, master transactions and the audit entry; **edits made in the preview are what gets saved; a removed program or activity is not saved; an invalid edit (a bad number, an empty program name, an unknown pillar, an oversized or malformed JSON) is refused with the user's edits kept and nothing saved; a payload that tries to change the school, year or period is ignored in favour of the token's;** errors in the file block confirm until fixed; a second import of the same period is refused; token cannot be used by another user or school; expired token; wrong file type; school isolation; user without planning permission gets 403.
- After an import, a program and an activity can be edited and deleted through the existing Planning routes (`planning.sip.update`, `planning.sip.activities.update`, `planning.sip.destroy`).
- `SipImportTest` for `sip:import` keeps passing after the refactor to the shared service.
- The user's real file is not added to the repository.

## Out of scope

Importing the Annual Implementation Plan sheet, PDF/CSV/`.xls`/Word, adding new programs or activities inside the preview (add them in the SIP lists after importing), replacing or merging into an existing plan, a downloadable template, and background processing of very large files.

## Documentation

`docs/specs.md` section 14 (SIP entry) needs an addition. That file is protected: the exact text is shown to the owner at the end and added only on an explicit yes.

## Risks

- Other schools' Excel files may differ from this sample (renamed sheets, moved columns, extra header rows). Header-based detection and per-row messages reduce this; a layout that cannot be recognized produces a clear error instead of a wrong import.
- Fill-down can attach an activity to the wrong program if the source file is inconsistent. The preview shows the programs with their activities so the user can check before confirming.

## Open points

None blocking.
