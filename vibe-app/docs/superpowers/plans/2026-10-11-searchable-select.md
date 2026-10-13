# Searchable Dropdowns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every `<select>` in the system becomes a type-to-search combo, through one shared enhancer, with no page edited.

**Architecture:** One Blade partial holds the styles and a dependency-free script. It enhances each `<select>` by adding a visible combo input and a popover list, while the real select stays as the source of truth. A global observer, a wrapper on the select `value`/`selectedIndex` setters and a form-reset listener keep the combo in sync with page scripts.

**Tech Stack:** Blade partial, plain JavaScript (Popover API), PHPUnit 12 for markup checks, the browser pane for behavior checks, Pint.

**Spec:** `vibe-app/docs/superpowers/specs/2026-10-11-searchable-select-design.md`. Read it with this plan.

## Global Constraints

- No new Composer or npm package and no third-party script. No page, controller, route or database change; the only edit to an existing file is the one `@include` line in `resources/views/partials/input-fixes.blade.php`.
- Not enhanced: `select[multiple]`, `select[size]` greater than 1, `select[data-native]`, any select inside `.official-toolbar`, and any select already enhanced (`data-ss-enhanced`). If `HTMLElement.prototype` has no `popover` property the script does nothing.
- The real select stays in the document, hidden by CSS but not `display: none` (off-screen, zero size, `tabindex="-1"`, `aria-hidden="true"`) so validation and submission still work. Picking dispatches bubbling `input` and `change` events on the select.
- Search: case-insensitive, accent-insensitive; every typed word must appear in the option text. Render at most 500 options (hint "Type to narrow the list" beyond that).
- Markup and hooks (fixed names): wrapper `.ss-wrap`, input `.ss-input` (copies the select's class list), list `.ss-list` (`popover="manual"`), option `.ss-option`, group heading `.ss-group`; script tag attribute `data-searchable-select-script`; global `window.searchableSelect = { refresh(select), enhance(select), enhanceAll(root) }`.
- All option and group text is inserted with `textContent`, never `innerHTML`.
- The script runs once per page even if the partial is included twice (guard on `window.searchableSelect`).
- Tests: the JavaScript cannot be run by PHPUnit. Verification is `node --check` plus scripted runs in the browser pane against rendered pages (see Task 3). Long files are written with the Write tool; multi-line edits to existing files go through a Python script file. Run `vendor/bin/pint --dirty --format agent` after PHP changes. Commit steps run only when the user has asked for commits; push and deploy only when asked. `docs/specs.md` is protected and is not touched.

## Review Focus

- A page script sets `select.value` before the combo exists, after it exists, and to a value with no matching option; the visible text matches the select each time.
- A required select left empty blocks the form submit and puts focus on the visible box (the hidden select must not cause "not focusable" errors).
- A select inside a modal `<dialog>` that sits inside an `overflow-x-auto` table: the list opens above the dialog, is not clipped, and closes with the dialog.
- A dynamically added select (table row built with `innerHTML`) is enhanced, and removing its row leaves no orphan wrapper or popover list in the document.
- Option text with `<`, `&` or quotes is shown as text; 600 options still open instantly and show the 500-option hint.

---

### Task 1: The enhancer core

**Files:**
- Create: `resources/views/partials/searchable-select.blade.php`, `tests/Feature/SearchableSelectTest.php`
- Modify: `resources/views/partials/input-fixes.blade.php` (add `@include('partials.searchable-select')` after the existing `@include('partials.password-toggle')`)

**Interfaces:**
- Produces the partial (a `<style>` and one `<script data-searchable-select-script>`) and `window.searchableSelect` as in Global Constraints. The script, on `DOMContentLoaded`, calls `enhanceAll(document)`; `enhance(select)` builds the wrapper after the select and wires input, list and the select's own events; `refresh(select)` re-reads the select's options, selected option, disabled state and updates the input text and the list.
- Behavior of this task: open on click, focus-then-typing or ArrowDown; filter as typed; pick by click or Enter (value set, `input` and `change` dispatched, text updated, list closed); Escape closes and restores the text; ArrowUp/Down/Home/End move the highlight (with `aria-activedescendant`, scrolled into view); Tab accepts the highlighted option only when a filter was typed; empty-value option as placeholder and as a clearing choice; `optgroup` headings hidden when none of their options match; disabled select and disabled options; "No matches" row; 500-option cap with hint; the ARIA roles of the spec.

- [ ] **Step 1: Write failing feature tests** in `SearchableSelectTest`: `test_both_layouts_load_the_enhancer_once` (a page extending `layouts.budget` such as the Planning page and one extending `layouts.procurement` such as the Suppliers page: `assertSee('data-searchable-select-script', false)` and `substr_count` equals 1); `test_pages_that_include_input_fixes_directly_get_it_once` (the registration page and the SOB page); `test_the_script_excludes_the_toolbar_and_data_native_selects` (the page text contains the exclusion selector `select[multiple], select[data-native], .official-toolbar select` once); `test_the_print_preview_toolbar_dropdowns_stay_plain_selects` (a print page renders `<select` inside `official-toolbar`, which the exclusion covers).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SearchableSelectTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the partial (styles: `.ss-wrap { position: relative }`, `.ss-input` with a chevron background, `.ss-list` popover with `max-height: 16rem`, overflow auto, border, shadow, `.ss-option[aria-selected=true]`, `.ss-option.is-active`, `.ss-option[aria-disabled=true]`, `.ss-group`, hidden-select CSS) and the script as specified, and add the include line in `input-fixes`.
- [ ] **Step 4: Run** the filter, `node --check` on the script extracted from a rendered page, and `php artisan test --compact`. Expected: PASS (suite unchanged).
- [ ] **Step 5: Commit** `feat: type-to-search enhancer for every dropdown`.

### Task 2: Staying in sync with page scripts

**Files:**
- Modify: `resources/views/partials/searchable-select.blade.php`
- Test: `tests/Feature/SearchableSelectTest.php` (a markup test per hook)

**Interfaces:**
- Adds to the script: a wrapper around the `value` and `selectedIndex` accessors of `HTMLSelectElement.prototype` (installed once; calls the original, then `refresh` for enhanced selects); a `MutationObserver` per enhanced select for `childList`, `subtree`, `characterData`, and the `disabled` attribute; a `reset` listener on forms that refreshes their enhanced selects on the next tick; a document-level `MutationObserver` that enhances newly added selects and removes the wrapper and list of a select that left the document; an `invalid` listener on the select that focuses the visible input and adds `aria-invalid="true"` (removed on the next pick).

- [ ] **Step 1: Write failing tests**: `test_the_script_contains_the_sync_hooks` (the page text contains `HTMLSelectElement.prototype`, `MutationObserver`, `'reset'`, `'invalid'` once each in the enhancer script; a markup-level guard so a refactor cannot silently drop a hook). The behavior itself is proved in Task 3.
- [ ] **Step 2: Run** the filter. Expected: FAIL.
- [ ] **Step 3: Implement** the hooks as specified.
- [ ] **Step 4: Run** the filter, `node --check`, and the whole suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: dropdown combos follow code that changes the select`.

### Task 3: Behavior checks on real pages, then documentation

**Files:**
- Modify: `CLAUDE.md` (one status line)
- Test: scripted browser-pane checks on rendered copies; the whole suite

**Interfaces:**
- Consumes the partial of Tasks 1 and 2. A throwaway test (not committed) renders the pages below with real data into `public/_combo_*.html` (delete afterwards): the Supplier form, the SOB page, the Planning page with its dialogs, the Procurement create page (runtime item rows) and the Documents page (Abstract of Bids company pickers), plus a harness page built with `Blade::render` that includes the partial around synthetic markup: optgroups, an empty-value placeholder option, disabled option and disabled select, a required select in a form, a select inside a `<dialog>` inside an `overflow-x-auto` table, 600 options, option text with `<`, `&` and quotes, and a select added by `innerHTML` and removed again.

- [ ] **Step 1: Run the scripted checks** against the harness and the real pages in the browser pane and read every result: typing filters and a click picks (value and `change` fire); Enter, Escape, ArrowUp/Down, Home/End, Tab; optgroups, placeholder, disabled; `select.value = x`, replaced options, `form.reset()`; the dynamic select is enhanced and its removal leaves no `.ss-wrap` or `.ss-list`; the required empty select blocks submit and focuses the visible box; the select in the dialog opens above it and is not clipped; the 600-option list opens instantly and shows the hint; special characters render as text; zero console errors.
- [ ] **Step 2: Fix** anything the checks show, one change at a time, with a markup test where a test can pin it, and re-run the checks.
- [ ] **Step 3: Run** `vendor/bin/pint --dirty --format agent` and `php artisan test --compact`. Expected: no style changes left; all tests pass (517 existing plus the new ones).
- [ ] **Step 4: Add the `CLAUDE.md` status line** for the searchable dropdowns.
- [ ] **Step 5: Final whole-branch review** by a fresh reviewer with the Review Focus list verbatim (the owner allowed an independent reviewer earlier in this session for the SOB; ask again if this one is wanted), fix Critical and Important findings each with a check that failed first.
- [ ] **Step 6: Ask the owner** before committing, pushing and deploying (`railway up --detach --service procurems` from `vibe-app`, `MSYS_NO_PATHCONV=1` in Git Bash). No migration is needed.
