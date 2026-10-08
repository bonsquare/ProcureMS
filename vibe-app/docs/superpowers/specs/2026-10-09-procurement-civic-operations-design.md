# Procurement Civic Operations Redesign

**Date:** 2026-10-09  
**Status:** Approved design direction; pending implementation plan  
**Scope:** All operational Procurement interfaces in ProcureMS

## 1. Purpose

Redesign Procurement as one coherent operational workspace rather than a collection of separate pages. The system must help school and master users understand the current state of every request, identify the next required action, manage suppliers and official documents, reconcile deliveries, and retain a clear audit trail.

The approved visual direction is **Civic Operations**: calm, authoritative, information-dense, and suitable for government procurement work. The approved information architecture is **Unified Workspace**.

## 2. Design Principles

1. **One continuous journey.** The interface must present procurement as Request → Approval → Canvass → Award → Purchase Order → Receiving → Complete.
2. **One clear next action.** Each screen emphasizes the most important workflow action and avoids competing primary buttons.
3. **Operational clarity before decoration.** Color, iconography, and hierarchy communicate state and priority.
4. **Progressive detail.** Users see summaries first and open supporting metadata only when needed.
5. **Auditability is visible.** Important changes expose the actor, timestamp, and linked workflow event.
6. **Official output remains compliant.** Mandated printable documents retain their prescribed print layout. The surrounding preview and control interface adopts Civic Operations styling.
7. **Accessible by design.** Status is never communicated by color alone; labels, icons, focus states, and adequate contrast are required.

## 3. Information Architecture

Procurement receives a shared module shell with five top-level work areas:

### Overview

- Portfolio metrics for total requests, pending approval, canvass, awards/POs, receiving exceptions, and completed value.
- A prioritized task queue showing work that requires the current user's attention.
- Aging requests and delivery exceptions.
- Recent procurement activity.
- Quick creation of a new procurement request.

### Requests

- Searchable, filterable registry of procurement requests.
- Saved or role-relevant views may be added without changing the core registry.
- Each row exposes request number, title, school, owner/requester, amount, current stage, age/date, and primary next action.
- Opening a request enters the Request Workspace described below.

### Suppliers

- Supplier directory and profile management.
- Contact, eligibility, status, quotation participation, awards, and procurement history.
- Supplier selection should be available in-place where documents or canvassing require it, while preserving the full Supplier workspace.

### Documents

- Document center spanning RFQ, quotations, abstracts, resolutions, notices, purchase orders, inspection/acceptance, inventory/property forms, and other configured official records.
- Completeness state, latest update, author, and print/preview actions are visible without opening each record.
- Document creation is contextual to the active request and its current stage.

### Receiving

- Delivery tracking, receiving quantities, partial deliveries, discrepancies, inspection/acceptance, and reconciliation.
- Received quantity, ordered quantity, remaining balance, and status are compared at item level.
- Exceptions are prioritized and link directly to the relevant request, PO, IAR, or reconciliation record.

## 4. Request Workspace

Every procurement request uses a consistent case-style workspace.

### Header

- Request number, title, school, amount, current stage, owner, and age/date.
- A single primary next action based on status and permissions.
- Secondary actions live in an explicit menu with text labels.

### Stage rail

The persistent stage rail contains:

1. Request
2. Approval
3. Canvass
4. Award
5. Purchase Order
6. Receiving
7. Complete

Completed, current, pending, and blocked stages use both labels and visual states. Selecting a stage reveals its records and requirements but does not permit invalid workflow transitions.

### Request tabs

- **Summary:** purpose, funding context, amount, dates, request items, owner, and key related records.
- **Items:** item descriptions, units, quantities, estimates, awarded prices, received quantities, and balances.
- **Documents:** stage-grouped document checklist with completeness indicators and preview/print actions.
- **Activity:** chronological audit and transaction events across procurement and linked modules.

### Next-action panel

A side panel on desktop, placed near the primary content on smaller screens, presents:

- Current blocker or required task.
- Due date or age when applicable.
- Missing requirements.
- Responsible role or person.
- Direct action to continue the workflow.

## 5. Visual System

### Color roles

- **Civic Navy (`#103967`):** primary navigation and institutional trust.
- **Action Blue (`#286DA8` family):** primary actions, links, and current stage.
- **Verified Teal (`#369878` family):** complete, approved, or received states.
- **Attention Amber (`#CC8535` family):** pending review, due tasks, and incomplete requirements.
- **Exception Red (`#B64A50` family):** blocked states, rejected actions, and discrepancies.
- Neutral blue-gray surfaces support long work sessions and dense content.

Exact token values may be adjusted during implementation to meet contrast requirements, but semantic roles must remain stable.

### Typography

- Inter remains the primary operational typeface.
- Page and request titles use compact, confident hierarchy.
- Metadata labels use restrained uppercase tracking.
- Monetary values use tabular numerals and consistent alignment.
- Body copy prioritizes readability over visual novelty.

### Shape and elevation

- Moderate corner radii distinguish application surfaces without creating a consumer-app feel.
- Borders define dense operational groups; shadows are reserved for raised overlays and major panels.
- Pills are used only for compact statuses, filters, and small metadata—not for general containers.

## 6. Core Components

### Module navigation

- Shared Procurement shell and active-area tabs.
- Desktop sidebar remains persistent; tablet/mobile navigation collapses.
- Current location is explicit in both the sidebar and local Procurement tabs.

### Metric card

- Short label, prominent value, supporting context, optional icon.
- Metrics link to the corresponding filtered view when actionable.

### Task card

- Priority/status icon and label.
- Concise task title and context.
- Due/age information.
- One direct action.

### Status badge

- Semantic color plus explicit text.
- Required states include Draft, Needs Approval, For Canvass, Award, Purchase Order, Receiving, Delivered/Complete, and Discrepancy/Blocked.

### Operational table

- Sticky or persistent header where practical.
- Search, filter, and sort controls remain visibly associated with the table.
- Clear primary identifier and secondary description.
- Right-aligned monetary values.
- Row-level next action is visible; additional actions use a labeled menu.
- Empty, loading, and error states occupy the table surface and explain the next step.

### Document checklist row

- Document type and number.
- Completeness/status.
- Last update and author.
- Preview, edit, and print actions according to permission.
- Missing requirements are explicit.

### Item reconciliation row

- Ordered, received, and balance quantities.
- Complete/partial/discrepancy state.
- Validation feedback at item level.

## 7. Interaction Rules

- Primary actions must use verbs that describe the result, such as “Submit for Approval,” “Create RFQ,” or “Record Delivery.”
- Destructive or irreversible actions require confirmation that names the affected record.
- Disabled workflow actions explain the unmet requirement.
- Success feedback confirms the record and resulting stage.
- Validation remains near the affected field and includes a summary when multiple errors occur.
- Filters should update results predictably and expose a clear reset.
- Modal dialogs are limited to focused tasks; complex forms use full workspace pages or panels.
- Keyboard focus must be visible, dialogs must trap and restore focus, and controls must have accessible names.

## 8. Permissions and Role Behavior

- Existing authorization remains the source of truth.
- Master users may see cross-school portfolio context where currently permitted.
- School-scoped users see records and metrics for their assigned school.
- Actions unavailable to the current role are omitted or disabled with an explanation, depending on whether visibility is useful.
- The next-action panel is permission-aware and must not recommend an action the current user cannot perform.

## 9. Data and Workflow Behavior

The redesign does not introduce an alternate workflow state machine. It presents existing Procurement models, statuses, documents, linked transactions, suppliers, and receiving data through a coherent view model.

Controllers or dedicated presentation services should provide normalized data for:

- Current stage and stage completion.
- Next permitted action.
- Missing requirements and blockers.
- Task priority and age.
- Document completeness.
- Item-level ordered, awarded, and received quantities.
- Linked transaction and audit activity.

Status-to-stage mapping must be centralized so the overview, registry, request workspace, and document center cannot disagree.

## 10. Error and Empty States

- A failed data load preserves the surrounding workspace and offers a retry where possible.
- Empty registries distinguish “no records exist” from “no results match these filters.”
- Missing suppliers, quotations, official documents, and receiving data show the action needed to continue.
- Invalid workflow transitions are rejected server-side and explained in the interface.
- Partial delivery and quantity discrepancies remain visible until reconciled; they are never represented as complete through color or aggregation alone.
- Print-preview failures offer a return path to the document center and identify the affected document.

## 11. Responsive Behavior

### Desktop (1024 px and above)

- Persistent sidebar, full data tables, stage rail, and side next-action panel.

### Tablet (768–1023 px)

- Collapsible navigation.
- Main content remains structured as panels.
- Wide tables retain all data through safe horizontal scrolling or deliberate column prioritization.

### Mobile (below 768 px)

- Registry rows become summary cards where necessary.
- Primary action may remain sticky when it does not obscure content.
- Stage rail becomes horizontally scrollable or a compact labeled stepper.
- Complex document forms preserve field order, labels, and validation.

Official printable pages remain optimized for their required paper size rather than responsive application layout.

## 12. Pages Covered

The implementation must cover, at minimum:

- Procurement overview and request registry.
- Create and edit procurement request.
- Request details/workspace.
- Supplier manager and supplier-related selection flows.
- Procurement document center and document-editing interfaces.
- Official document preview/print toolbars and surrounding experience.
- Delivery and receiving reconciliation interface.
- Procurement-linked transaction context and audit navigation.
- Procurement-specific menus, overlays, empty states, errors, and responsive layouts.

Unrelated Finance, Planning, Liquidation, or system administration screens are outside this redesign except where a shared shell component must remain compatible.

## 13. Testing Strategy

### Feature tests

- Role and school scoping for overview metrics, request registry, request workspace, suppliers, documents, and receiving.
- Status-to-stage mapping and next-action selection.
- Valid and invalid workflow transitions.
- Document completeness and linked request behavior.
- Receiving totals, partial deliveries, discrepancies, and reconciliation.
- Existing official print routes and required document data.

### View/component tests

- Required states and actions render for representative request stages.
- Permission-aware actions render correctly.
- Empty and validation states provide actionable copy.
- Amount and quantity presentation remains consistent.

### Browser workflow tests

- Create request through approval, canvass, award/PO, receiving, and completion using the complete workflow seed.
- Search and filter the registry.
- Manage suppliers and select suppliers during document workflows.
- Create, preview, and print official documents.
- Record complete and partial deliveries and verify reconciliation.
- Verify desktop, tablet, and mobile navigation and primary tasks.
- Keyboard navigation, dialog focus behavior, visible focus, and non-color status communication.

### Regression requirements

- Existing backend behavior, permissions, seeded workflow, and official print tests remain passing.
- The redesign must not alter prescribed official-document content or calculations unless a separate functional requirement explicitly authorizes it.

## 14. Acceptance Criteria

The redesign is complete when:

1. All operational Procurement pages use the Civic Operations visual system and shared module shell.
2. Users can understand a request's stage, owner, blockers, documents, and next action from its workspace.
3. Overview, registry, suppliers, documents, and receiving are reachable as coherent areas within Procurement.
4. Official forms remain print-compliant and open within consistent controls.
5. Responsive and keyboard workflows are usable for core tasks.
6. Status, permissions, document completeness, and receiving reconciliation are covered by automated tests.
7. The seeded complete workflow can be demonstrated end to end without encountering a visually inconsistent Procurement screen.

