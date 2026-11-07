# Print Settings Manager (margins) — design

Date: 2026-11-07. Owner's request: one place with the printing settings, so every document in the system prints the same way.

## Understanding (confirmed by the owner)

- **Scope:** settings are per school, saved on the server. Every user and computer of that school prints the same way.
- **What it controls:** page margins (top, right, bottom, left, in mm). Paper size, orientation and zoom stay in the print toolbar, as now.
- **How:** one school-wide default, plus an optional margin set for a single document.
- **Where:** a new **Printing** tab in School Settings.
- **Approach A:** a `print_settings` table.

## Why

Today each print page hard-codes its own margin (8 or 10 mm for table documents, none for ORS/DV) and the print dialog of a printer can change it again. Printers also refuse to print closer than their own limit (an Epson L3210 asks 0.49" at the bottom), which made the SOB print on four sheets instead of two (fixed 2026-11-07 with a 13 mm minimum).

## Data

Table `print_settings`:

| column | type | note |
|---|---|---|
| id | bigint | |
| organization_id, school_id | foreign keys | same tenancy as the other school settings |
| doc_key | string(40), nullable | null = the school default; otherwise the document's `data-doc` key (sob, aip, ors, dv, pr, cash-roci...) |
| margin_top, margin_right, margin_bottom, margin_left | decimal(5,1) | mm, 0 to 40 |
| timestamps | | |

Unique on (school_id, doc_key). A school without rows prints exactly as today.

## Which documents

- **Through the shared print engine (`partials/print-clean`), 24 pages:** the procurement documents (PR, PO, RFQ, IAR, RIS, ICS, NOA, NTP, Abstract, Delivery Reconciliation), ORS, DV, SOB, APP, APP-CSE, AIP, SIP, POW, Alphalist and the four cash reports. They get the settings with no change to the documents.
- **Own print CSS, 5 pages:** Allotment Registry, Budget Item, Budget Report, Financial Reports, Generated Reports. Their `@page` margin reads the same school default; no layout change (to be confirmed in the plan after reading their CSS).
- ORS and DV are drawn to fit the sheet and keep margin 0 unless the school gives them their own row. The school default does not apply to them.

## Order of choice

1. The document's own row (`doc_key` equal to the page's `data-doc`).
2. The school default (`doc_key` null), only for documents that have a margin today.
3. The margin written in the document's file (`data-margin`).

## Safe limits

- Each margin 0 to 40 mm (validated).
- For documents laid out page by page (SOB, APP, AIP, SIP, POW) the top and bottom never go below 13 mm. The tab shows this beside those fields.

## Delivery to the page

The server gives the print pages a small JSON of the school's settings (`default` and `documents`) through the existing `partials/print-clean` include; its script reads it where it now reads `data-margin`. No new request.

## Screen (School Settings, Printing tab)

- Four margin boxes for the default, aligned in a row, with notes under them that do not move the boxes.
- A table of documents (name, own margins or "uses the default", Edit / Use default).
- A small sheet drawing that shows the margins as they are typed.
- Text wraps inside its box; checked at 320, 768 and 1280 px.

## Who and how

- Permission `organization.settings` (same as the other School Settings tabs).
- Saving asks the Reason + PIN window (route added to `RequireActionPin::ROUTES`); the change goes to the audit log with the old and new values.

## Not included (YAGNI)

Paper size, orientation, zoom and footer settings; per-user settings; separate settings for each printer.

## Tests

- Saving and validation (range, one row per school and document).
- Permission and school isolation.
- Order of choice (document row, school default, file value).
- The 13 mm floor for page-by-page documents.
- A school with no rows prints as before (existing print tests pass unchanged).
- The Printing tab renders and shows the defaults.

## Open for the plan

- Read the print CSS of the five report pages and confirm how each takes the default.
- Exact list and names of the `doc_key`s from the `data-doc` of each page.
