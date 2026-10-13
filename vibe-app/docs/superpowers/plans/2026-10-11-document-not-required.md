# "Not required for this transaction" for the optional documents - plan

**Goal:** the Inventory Custodian Slip (ICS) and Property Acknowledgement Receipt (PAR) cards get a "Not required for this transaction" button; a marked document reads as not required, without touching progress, completion or the required-document logic.

**Design (agreed in chat 2026-10-11):**
- Saved per request in a new nullable JSON column `procurement_requests.not_required_documents`: `{ <document_type>: {user_id, name, at} }`.
- Only the optional types (`ProcurementWorkspaceService::OPTIONAL_DOCUMENTS`) accept it; a prepared document cannot be marked; saving the document clears its mark; "Make it required again" removes it. Each press and each undo is written to the audit log (`document_marked_not_required`, `document_required_again`).
- Same permission as editing documents (`procurement.edit`), same school/organization access check as the other document routes. POST for both actions (a Sub-master's delete switch only governs DELETE).
- The card of a marked document is grey with a "Not required" badge, "Marked not required by <name> on <date>" and "Make it required again". PAR keeps its "Template coming soon" lock and gets the button too. The button shows whether or not the Requisition and Issuance Slip exists yet.

**Files:** migration (new), `app/Models/ProcurementRequest.php`, `routes/web.php`, `app/Http/Controllers/HomeController.php` (two actions, clear on save), `resources/views/procurement-documents.blade.php`, `tests/Feature/DocumentNotRequiredTest.php`.

**Tasks (TDD, one commit each):**
1. Column, model cast, the two routes and actions, audit, clear-on-save. Tests: mark and undo (data, audit), only ICS/PAR accepted, a prepared document refused (422), no `procurement.edit` refused (403), another organization's request refused, saving the document clears the mark, progress/missing-list unchanged by the mark.
2. Cards. Tests: both buttons show on the receiving page; a marked card shows "Not required", who/when and "Make it required again" and no button; a prepared document still shows as prepared even with a stale mark; a user without `procurement.edit` sees no button.
3. Full suite, Pint, merge only after the owner says so.

**Out of scope:** `docs/specs.md` (protected; change only if the owner asks), other documents, the Document Center list.
