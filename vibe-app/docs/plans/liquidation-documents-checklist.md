# Liquidation documents checklist: plan

Status: built on 2026-10-18 (see docs/specs.md section 46), including the school's own papers and the read-only school view. Decisions of the owner on 2026-10-18 are listed first; the open items at the end still need an answer.

## Owner decisions (2026-10-18)

1. **How the list is made:** auto + manual. Papers the system makes itself (PR, RFQ, Abstract, NOA, NTP, PO, IAR, DV) are checked by themselves from the real documents; papers from outside (invoice, delivery receipt, certificates) are ticked by hand.
2. **Files:** tick only for now. Each paper is listed and ticked "received", with an optional note. No upload yet (it can be added later without changing this design).
3. **Effect on approval:** an ORS **cannot be approved** while a required paper is missing. A missing paper puts the ORS in **Pending Documents** and the school is shown exactly what is missing.
4. **ORS kinds that get their own list:** goods from a PR/PO, direct ORS (no PR), travel, and a school's own extra list.

## What exists today (from the code, 2026-10-18)

- The Liquidation module is the ORS record plus review. There is **no** supporting-document checklist (Phase 8 of CLAUDE.md asks for one).
- Status `pending_documents` exists with a free-text remark (`accounting_remarks`); nothing says which papers are missing.
- ORS has no "kind" field. A goods ORS has `procurement_request_id`; a direct ORS has none.
- A procurement request already knows which of its documents exist, and which are marked "not required for this transaction" (`ProcurementRequest::not_required_documents`). The checklist must respect that mark.
- The Accounting approval is `FinanceController::review`; the Liquidation page has its own status route (now guarded for paid / DV ORS).

## Design

### Data
- `liquidation_reports.ors_kind` (nullable string): `goods`, `direct`, `travel`, `other`. Goods is set by itself when the ORS is made from a PR; otherwise the ORS form asks "Type of expense" (direct, travel, other). Old rows without a value: goods if they have a PR, else direct.
- `liquidation_document_checks` (new): `organization_id`, `liquidation_report_id`, `item_key`, `received` (bool), `note`, `checked_by`, `checked_at`; unique on report + item key. Only manual items are stored; auto items are read live from the real documents.
- `school_checklist_items` (new): `organization_id`, `school_id`, `ors_kind`, `label`, `is_required`, `sort_order`, `is_active`. A school's own extra papers per kind.
- The standard lists are a code class (`App\Support\LiquidationChecklist`), not seeded data. First draft:
  - **goods:** auto PR, RFQ, Abstract, NOA, NTP, PO, IAR, DV (from the system; skipped when the request marks it not required); manual Sales invoice / official receipt, Delivery receipt, Certificate of acceptance if the school requires it.
  - **direct:** manual Billing statement or official receipt, Proof of service rendered; auto DV.
  - **travel:** manual Travel order, Itinerary of travel, Certificate of appearance, Approved trip ticket or receipts (the owner to confirm); auto DV.
  - **other:** manual Supporting paper (one line the accountant names); auto DV.

### Rules (service `LiquidationChecklistService`)
- `items(report)`: the list for its kind, standard + the school's active extras, each with `required`, `source` (system or manual), `done`, `note`.
- `missing(report)`: the required items not done. A system item is done when its document exists or the PR marks it not required; a manual item is done when ticked.
- **Approve gate:** `FinanceController::review` and the Liquidation status route refuse `approved` (422) while `missing()` is not empty, and say which papers.
- **Pending Documents:** choosing Pending Documents without a typed remark fills the remark with the missing papers; the school sees it in the Returned/Pending view.
- Ticking and unticking are audited (`AuditLog`) and recorded as a transaction event; only `accounting.approve` can tick. The school can only read.
- Anything already approved or paid is left as it is (no retroactive block).

### Screens
- **Accounting:** each ORS row gets a "Papers" button that opens a window listing the checklist: system papers with a green check and a link to open them, manual papers with a tick box and a note, a counter "5 of 8 received", then Save. The Approve button is disabled with the reason while a required paper is missing.
- **Liquidation (school view):** a read-only list of the same papers on the ORS, missing ones highlighted.
- **School Settings:** a small card "Extra papers for liquidation" to add, hide and order the school's own items per kind.
- **ORS form:** "Type of expense" select for an ORS without a PR (aligned with the other boxes, per the alignment rule).

### Out of scope now
- File upload or Drive link of the papers (owner chose tick only).
- Changing the approval of ORS that are already approved.

## Steps (each small, tested, committed and pushed; deploy only on the owner's word)

1. Migration + `ors_kind` + `LiquidationChecklist` standard lists + `LiquidationChecklistService` with tests (kinds, auto items, not-required marks, missing).
2. Tick/untick endpoint, audit and event, permission and isolation tests.
3. Approve gate in both status routes + Pending Documents auto remark + tests.
4. Accounting "Papers" window and the disabled Approve; read-only list on the Liquidation page; checked on the local server.
5. ORS form "Type of expense" for an ORS without a PR.
6. School extra papers (table, School Settings card, tests).
7. specs.md section, CLAUDE.md status list, full test suite, commit, push, ask to deploy.

## Open questions for the owner
1. **Travel list:** is the list above right (travel order, itinerary, certificate of appearance, receipts)? Please give the exact papers your office requires.
2. **Goods manual papers:** which are always required: sales invoice / official receipt, delivery receipt, anything else?
3. **Existing ORS:** after this, should an ORS that is already **For Review** with missing papers be blocked at approval too (recommended), or only new ones?
4. **Who may tick:** only Accounting (as planned), or also the school's own admin for the papers it holds?

## Answers of the owner to the open questions (2026-10-18)
1. Travel: all of those papers, but which apply depends on the trip. So every manual paper can be marked **Not required for this transaction**.
2. Goods: it depends on the transaction, the same way.
3. An ORS already For Review is blocked too.
4. Only the admin who manages Accounting ticks the papers (only one user does it).

## Change found while building
Delivery and trip papers cannot be required before the ORS is approved (they only exist later), so each paper has a stage: before the ORS is approved, or before the DV is created.
