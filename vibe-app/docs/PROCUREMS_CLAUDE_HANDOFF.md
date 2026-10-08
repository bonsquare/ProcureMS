# ProcureMS — Claude continuation handoff

This document is the working brief for continuing ProcureMS development with Claude. The authoritative application rules are in `CLAUDE.md`; this file gives the product roadmap and the expected delivery order.

## Product purpose

ProcureMS is a multi-school DepEd procurement and financial-management system. It connects school improvement planning and annual planning to procurement, budget, accounting, cash, delivery, and liquidation while preserving organization-level data isolation and a complete audit trail.

## Core record chain

Use this relationship as the default end-to-end workflow:

`Organization → School → SIP → AIP → Budget → PPMP → APP → PR → RFQ/Quotation/BAC → NOA/PO/NTP → Delivery/Inspection/Acceptance → Obligation/DV/Journal → Payment → Liquidation → Reports/Documents`

A `MasterTransaction` should provide the common audit and status trail where the existing schema supports it. Do not create a disconnected duplicate workflow.

## Phase deliverables

### 1. Foundation

Authentication, organizations, schools, multi-tenancy, subscriptions/trials, users, roles, permissions, organization profile, fiscal years, fund sources, chart of accounts, and settings.

### 2. Planning and Budget

SIP priorities, AIP records, SIP/AIP links, budget allocations, quarterly allocations, fund sources, budget control, fiscal-year status, numbering, and audit history.

### 3. Procurement Planning

PPMP drafts and items, specifications, approval, APP generation/approval, PR creation and status, and links from planning and budget records to PR.

### 4. Procurement

Suppliers, RFQs, quotations, comparison/abstract, BAC workflow, NOA, PO, NTP, and procurement status tracking.

### 5. Delivery

Delivery, partial delivery, inspection, acceptance, accepted/rejected quantities, IAR, supplier and PO linkage.

### 6. Accounting

Obligation, DV, journals, ledger, subsidiary ledger, trial balance, approvals, and transaction linkage.

### 7. Cash

Payment, check/cash records, cash registers, payment status, DV linkage, and cash balances.

### 8. Liquidation

Liquidation, supporting documents, validation, return/deficiency workflow, approval, and liquidation register.

### 9. Documents

Official templates, print/PDF output, document numbering, template versions, and archive.

### 10. Reporting

Planning, procurement, budget, accounting, cash, liquidation, supplier, and SIP/AIP accomplishment reports.

### 11. Advanced

Notifications, analytics, support access, configurable workflows, advanced audit, organization customization, and performance monitoring.

## Current checkpoint

The repository is currently between Phase 2 and Phase 3. SIP and planning screens exist, and PPMP/APP/master-transaction work has started. First validate and stabilize the existing chain before adding Phase 4 features.

## Safe implementation checklist

- Inspect current models, migrations, routes, and tests before editing.
- Check organization and school ownership on every read and write.
- Check the role permission before exposing a page or processing a mutation.
- Use additive migrations and preserve legacy records.
- Link new records to existing transactions instead of duplicating them.
- Add a focused feature test for new behavior and its denial/isolation case.
- Run the narrowest relevant test set and `vendor/bin/pint --dirty --format agent` after PHP changes.
- Check the browser page and Laravel logs for the actual result.
- Record progress in this document and commit each coherent milestone.

## Do not do

- Do not reset, force-push, or delete existing migrations/data.
- Do not bypass organization isolation for convenience.
- Do not change relationships across modules without a migration and compatibility plan.
- Do not mark a phase complete based only on a UI mockup.
- Do not add dependencies without approval.
