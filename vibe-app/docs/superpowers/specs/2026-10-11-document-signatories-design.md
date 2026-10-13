# Document signatories in the generating forms - design

Date: 2026-10-11. Status: awaiting the owner's review.

## Purpose

Official documents pick who signs automatically from the roles set in School Settings (Approver, Procurement Officer, BAC Chairperson, Disbursing Officer, Inspection Officer and so on). The generating forms never show who that is and give no way to change it. A school often has two people with one role (a primary and a vice), and today the system silently picks one.

The user wants every generating form to show who will sign each signature slot, and to let the user choose someone else before generating.

## What the owner decided

- The official templates stay exactly as they are. No extra names, stacked lines, new captions or layout changes. One signer per slot, printed in the existing line.
- A change applies to that one document only (option A). It never changes School Settings or other documents.
- Scope: the 10 procurement documents, the Purchase Request (PR), the Obligation Request and Status (ORS) and the Disbursement Voucher (DV).
- The dropdown lists the people who hold the slot's role first (so the primary and the vice are on top), then the rest of the school's active staff.

## Out of scope

- Changing any template layout or caption.
- More than one signer in a slot.
- Changing School Settings roles from a form.
- Editing signatories of a document that is not re-opened through its own form.
- `docs/specs.md` is protected. If a standing specification there has to change, ask the owner first.

## Slot catalog

New class `App\Support\SignatorySlots` is the single list of slots per document. Each slot has a key, the printed label (for the form only), the existing role lookup that finds the default person, and the template variable it feeds.

| Document | Slots (template variable) |
|---|---|
| RFQ | BAC Chairperson (`bacChair`), BAC Secretariat (`bacSecretariat`) |
| Abstract of Bids | BAC Chairperson, BAC Vice Chairperson (`bacViceChair`), BAC Members (`bacMembers`, one pick per printed member row) |
| NOA | School Head (`schoolHead`) |
| NTP | School Head |
| PO | School Head, Disbursing Officer (`disbursingOfficer`) |
| IAR | Inspection Officer (`inspectionOfficer`), Property Officer (`propertyOfficer`) |
| IARS | School Head (the staff lines inside the table stay as the form's own assignments) |
| RIS | Requesting Officer (`requestingOfficer`), School Head, Property Officer |
| ICS | Property Officer, Procurement Officer, Requesting Officer |
| PAR / generic | Prepared by (`requestingOfficer`/`procurementOfficer`), Approved by (`approver`/`schoolHead`) |
| PR | Requested by (`prepared_by`), Approved by (`approved_by`) |
| ORS | Requester / School Head (`requester`), Certifier / Budget or Disbursing Officer (`budgetOfficer`) |
| DV | Accountant (`accountant`), School Head (`head`) |

The exact slots per template are confirmed against each template while building that stage. A slot the template does not print is not offered.

## Storage

Choices are saved with the document, as a snapshot: staff id, name and position at that moment. Old documents therefore never change when staff are later renamed, replaced or deactivated.

- Procurement documents: `procurement_documents.metadata['signatories'][<slot key>]`. No migration.
- PR: new nullable JSON column `procurement_requests.signatories`.
- ORS and DV: new nullable JSON column `liquidation_reports.signatories`, with keys prefixed `ors.` and `dv.`.
- Migrations are additive and nullable. Nothing existing is renamed or dropped.

## The shared component

- A resolver, `SignatorySlots::resolve(document, staff, saved)`, returns for each slot the saved snapshot if there is one, otherwise today's default lookup. It replaces the lookups now repeated in `HomeController` (`printProcurementDocument`, `procurementPrintView`, `printOrs`) and `FinanceController::printDv`. The print methods then pass the same template variables as before. Templates change only to read a snapshot where the variable is a model.
- A Blade partial, `partials/signatory-picker`, renders the slots of one document as dropdowns. Each dropdown lists the people holding the slot's role first, then every other active staff of that school. The default person is pre-selected. The partial has one place in each form and shows only the slots of the document being made (the same show/hide pattern as the existing `data-*-only` fields in the procurement modal).
- Where a template prints the chosen person's position (RFQ, ICS, generic, ORS), it prints the snapshot position. Where a template has a fixed official caption, the caption is unchanged.

## Forms

1. Procurement document modal (`resources/views/procurement-documents.blade.php`): a "Signatories" section, toggled per document type. Re-opening a saved document shows the saved choices and lets the user change them.
2. PR form (`procurement-create.blade.php`): a "Signatories" section with Requested by and Approved by. Editing a PR shows the saved choices.
3. DV form on the Accounting page (`finance.blade.php`): a "Signatories" section in the DV dialog. The Accounting options row for an existing DV gets the same selects (the existing `accounting.dv.options` request grows a `signatories` field).
4. ORS: the ORS form on the Budget page (`budget.blade.php`, saved by `HomeController::storeLiquidation`) gets a "Signatories" section. Its saved choices are read by `printOrs`.

## Validation and safety

- Every submitted staff id must be an active staff member of the document's school. Anything else is a validation error. Another school's staff, an inactive person or an unknown id is refused.
- A slot left blank, or the default left selected, saves nothing for that slot, so the document keeps following School Settings until someone changes it. Choosing a person saves the snapshot.
- Permissions stay as they are for each form (`procurement.edit`/`procurement.create`, `accounting.approve`, `liquidation.create`). No new permission.
- Staff queries keep the existing organization and school scoping (`SchoolStaff::active()`).

## Delivery

One design, built in three stages with a commit after each:

1. Procurement documents (catalog, resolver, partial, modal, print, no migration).
2. PR (migration, form, print).
3. ORS and DV (migration, forms, print).

## Testing

For each document, in a feature test:

- The default signer prints when nothing was chosen. Documents saved before this feature print exactly as before.
- A chosen person prints in the right slot, and the snapshot survives renaming or deactivating that person.
- Staff of another school, an inactive person and an unknown id are rejected.
- The picker lists the role holders first.
- Reopening a saved document shows the saved choices.

Run the existing document and print tests (`OfficialPrintTest`, procurement document tests) with each stage, plus the full suite before each commit.
