# Type-to-search dropdowns across the system: design

Date: 2026-10-11. Status: design approved in chat, written spec awaiting review.

## Goal

Every dropdown (`<select>`) in the system becomes a type-to-search combo: the user types to filter the options and picks one. It works on every page without editing each form, and existing page scripts keep working.

## Evidence

- 86 `<select>` elements in 36 Blade files; none is `multiple`. 12 of those files are partials included by pages.
- One shared partial, `resources/views/partials/input-fixes.blade.php`, is included by both layouts (`layouts/budget.blade.php`, `layouts/procurement.blade.php`) and directly by 8 pages. Every page that has a dropdown either extends a layout or includes that partial, so one enhancer placed there reaches all of them. `input-fixes` already includes `partials/password-toggle` (the same pattern for a global behavior).
- Some dropdowns are created by JavaScript at runtime: the Purchase Request item rows (`procurement-create.blade.php`, `innerHTML`) and the Abstract of Bids company pickers (`procurement-documents.blade.php`, `renderBidderComparison`). Pages also set dropdown values in code and listen for `change` (filters that submit on change).
- The print-preview toolbar (`partials/official-toolbar.blade.php`) has three dropdowns (paper size, orientation, zoom).

## Decisions taken in chat

- A custom, dependency-free enhancer (no Tom Select or other library; adding one would need the owner's approval).
- The real `<select>` stays in the DOM as the source of truth; a visible search box and list are added.
- Opt-out with `data-native`; the print-preview toolbar's dropdowns are opted out (kept native).
- Applies to all other dropdowns, including short lists.

## Behavior

- **Markup produced:** a wrapper after the select containing a text input (the combo) and a list. The select is kept in the document but visually hidden and not tabbable (`tabindex="-1"`, `aria-hidden="true"`, positioned off-screen with zero size, not `display: none`) so form submission and validation still work.
- **Visible box:** shows the selected option's text. The class list of the select is copied to the input so it looks like the page's own fields; a small chevron is drawn at the right. The placeholder is the text of the first option whose value is empty ("Select ..."); with no such option, the box is empty until a choice is made.
- **Opening:** click, focus plus typing, or Arrow Down opens the list. Typing filters; matching is case-insensitive and accent-insensitive, and every word typed must appear somewhere in the option text. Group headings (`optgroup`) show above their options and hide when none of their options match. No match shows "No matches".
- **Picking:** click, or Enter on the highlighted option. The select's value is set and a bubbling `change` event (and `input`) is dispatched, so inline handlers (for example `onchange="this.form.submit()"`) and page scripts run as before. Escape closes and restores the shown text; Tab accepts the highlighted option when the user typed a filter, otherwise just moves on. Arrow Up/Down move the highlight and scroll it into view; Home/End jump.
- **Empty value:** an option with an empty value is offered in the list as a choice (to clear the field) except that its text is shown in the placeholder style.
- **Disabled:** a disabled select gives a disabled combo; disabled options are shown dimmed and cannot be picked.
- **Required:** on form validation failure (`invalid` event on the select) the visible input is focused and styled as invalid.
- **Large lists:** all options are rendered up to 500; beyond that the list shows the first 500 matches and a "Type to narrow the list" hint.
- **Stay in sync (the select changes by code):** a global wrapper around the `value` and `selectedIndex` setters of `HTMLSelectElement.prototype` refreshes the combo when a script sets them; a `MutationObserver` on each select refreshes when options are added, removed or changed, or when `disabled` changes; a `reset` listener on forms refreshes after `form.reset()`. A `MutationObserver` on the document enhances selects added later (runtime rows, dialogs) and removes the combo when its select is removed.
- **Layering:** the list is a `popover="manual"` element (top layer), positioned with `getBoundingClientRect` and repositioned on scroll and resize, so it shows above modal `<dialog>` elements and is not clipped by scrolling table containers. It opens upward when there is no room below. It closes on outside click, on focus loss, and on scroll of the page when the box leaves the view.
- **Not enhanced:** `multiple` or `size > 1` selects, selects inside `.official-toolbar`, any select with `data-native`, and selects already enhanced.
- **Accessibility:** input has `role="combobox"`, `aria-autocomplete="list"`, `aria-expanded`, `aria-controls` and `aria-activedescendant`; the list has `role="listbox"`; options `role="option"` with `aria-selected`; the label that points at the select (`for`, or wrapping `<label>`) also labels the input.
- **Touch:** tapping opens the list; the list scrolls; tapping an option picks it.

## Components

- `resources/views/partials/searchable-select.blade.php`: the styles and the script, included once from `resources/views/partials/input-fixes.blade.php` (`@include('partials.searchable-select')`), exactly as `password-toggle` is. Guarded so the script runs once per page even if the partial is included twice. Exposes `window.searchableSelect.refresh(select)` for page scripts that need it.
- No page needs changes. No database, route or controller change.

## Tests (each fails first)

- Feature: pages that extend either layout, and the pages that include `input-fixes`, contain the enhancer once (`data-searchable-select-script`); the print-preview page still renders its toolbar dropdowns as native selects (the toolbar markup is excluded by the rule, asserted through the script's exclusion selector being present).
- Browser (browser pane, rendered pages with real forms, scripted):
  - typing filters and a click picks; the select's value and `change` fire; Enter, Escape, arrows, Tab, Home/End;
  - optgroups, the empty option as a placeholder, disabled options and a disabled select;
  - `select.value = x` in code updates the shown text; replacing the options updates the list; `form.reset()` restores the text;
  - a select added after load (as the runtime Purchase Request rows) is enhanced; one removed leaves no leftovers;
  - a required empty select blocks submit and focuses the visible box; a filled form submits the right value;
  - a dropdown inside a modal `<dialog>` opens above it and is not clipped inside an `overflow-x-auto` table;
  - the page's own existing scripts work: the Abstract of Bids company pickers (value read on change), a filter that submits on change.
- `node --check` on the extracted script; zero console errors in the pages exercised.

## Out of scope

Multi-select, remote (AJAX) search, creating new options by typing, a library, and restyling the page's fields.

## Risks

- Page scripts that read the visible text of a select by DOM position or that replace a select's parent HTML would drop the combo; the document observer re-enhances any select that appears, so these recover on their own.
- The `HTMLSelectElement.prototype` setter wrapper must call the original setter and be installed once; a failure there would break every dropdown, which is why the browser tests cover programmatic changes first.
- Very old browsers without the Popover API fall back to the select staying native (the enhancer checks `HTMLElement.prototype.hasOwnProperty('popover')` and does nothing otherwise).

## Open points

None blocking.
