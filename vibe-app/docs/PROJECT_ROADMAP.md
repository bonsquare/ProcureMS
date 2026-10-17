# ProcureMS Project Roadmap

Created 2026-10-17 from a read-only audit. No application code was changed by the audit.
`docs/specs.md` remains the protected product specification; this file does not replace or contradict it.
Labels: **VERIFIED** = seen in code or a test run this audit. **UNVERIFIED** = not confirmed. **CONFIRM** = needs the owner's decision.

## 1. Audit facts that shape the plan

| # | Fact | Status |
|---|------|--------|
| 1 | Stack is Laravel 13 / PHP 8.4 with Blade + Tailwind. There is no Vue (no `package.json`; only `resources/js/app.js`). | VERIFIED |
| 2 | Database is SQLite (Railway volume at `/data`), not MySQL. | VERIFIED (Dockerfile, `.env.example`, `phpunit.xml`) |
| 3 | Full suite: 929 tests, 929 passed, 5705 assertions (2026-10-17). | VERIFIED (executed) |
| 4 | 219 routes; 119 have no `permission:` route middleware. Spot-checked ones (AIP, Budget Allocation, procurement status) guard in the controller. The rest are not yet checked. | PARTIAL |
| 5 | Tenant isolation: global scope `BelongsToOrganization`, covered by `OrganizationIsolationTest` (5 tests). 119 `withoutGlobalScopes` call sites are not reviewed. | PARTIAL |
| 6 | `HomeController.php` is 2539 lines with 45 public methods (procurement, liquidation, suppliers, settings, dashboard). | VERIFIED |
| 7 | `inspection.*` permission exists (role `inspector`, sub-master checklist) but no route uses it. Delivery is only the Receiving workspace, a delivery-reconciliation print and an Inspection and Acceptance Report document type. | VERIFIED |
| 8 | No general ledger, trial balance or subsidiary ledger found. Journal entry exists only on the DV (`DvJournalLine`). | VERIFIED (search found none) |
| 9 | "Liquidation" is the ORS and DV pipeline on `liquidation_reports`. No supporting-document checklist or deficient/returned-document workflow was found. | VERIFIED (search found none) |
| 10 | PPMP is replaced by SOB (2026-10-11). `PpmpPlan` and `PpmpItem` models and tables remain. The phase list in `CLAUDE.md` still names PPMP. | VERIFIED |
| 11 | No root `SPECS.md`; the spec is `docs/specs.md`. Root `HANDOFF_TO_CLAUDE.md` is stale (HEAD `e6070f3`). | VERIFIED |

## 2. Development phases (ordered by real dependency)

The planning chain feeds money and procurement, so it is the base. Order below follows data dependencies, not the module list.

| Phase | Name | Why here | Depends on |
|-------|------|----------|------------|
| P0 | Safety net: permission, isolation and backup verification | Every later slice writes money records; holes here multiply | none |
| P1 | Planning chain closed: SIP → AIP → SOB → APP / APP-CSE | SOB approval creates allotments; APP is generated from SOB; PR links to APP | P0 |
| P2 | Procurement chain: PR → RFQ → Abstract → PO → NTP | PR needs approved APP items; PO obligates the ORS | P1 |
| P3 | Delivery and inspection: delivery, partial delivery, IAR | Payment must follow accepted delivery. Currently thin. | P2 |
| P4 | Accounting: ORS → DV → journal; ledgers and trial balance | DV needs ORS and (CONFIRM) accepted delivery | P2, P3 |
| P5 | Cash: payment, check, cancel check, cash reports | Needs DV | P4 |
| P6 | Liquidation proper: supporting-document checklist, deficiency, approval | CONFIRM scope with the owner | P5 |
| P7 | Reports and dashboards | Read-only; needs P1–P6 data | P1–P6 |
| P8 | Platform: subscription and billing, Drive, backup, Turnstile, notifications | Independent; schedule by owner need | P0 |
| P9 | Code health: split `HomeController`, retire PPMP code | Only with owner approval (strict minimal change policy) | none |

Master administration, School Settings and Station Transfer are built and tested; they are maintained, not phased.

## 3. Module priorities

| Module | State | Priority |
|--------|-------|----------|
| Authorization and tenant isolation | Built, partly verified | Critical |
| School Planning (SIP, AIP, SOB, APP, APP-CSE, POW) | Built, tested | High (close open items) |
| Government Procurement | Built through PO and documents | High |
| Delivery and inspection | Thin | High (CONFIRM) |
| Finance: Budget, Accounting, Cash | Built through DV, payment, cancel check, cash reports | High |
| Ledgers and trial balance | Missing | Medium (CONFIRM need) |
| Liquidation checklist workflow | Missing | Medium (CONFIRM need) |
| Reports | Cash reports built; other reports UNVERIFIED | Medium |
| Google Drive, Backup | Built; owner must set Google variables | Medium |
| Subscription | Write-gate built; payment or renewal flow not found | Low–Medium |

## 4. Milestones and completion criteria

A milestone is done only when every item has: schema, UI, controller or service, permissions, organization isolation, transaction links, validation, tests **executed and passing**, and the user-facing output checked. This is the standard already in `CLAUDE.md`.

| Milestone | Done when |
|-----------|-----------|
| M0 Safe base | Every state-changing route has a verified permission check and a test for the denied case. Isolation test covers each organization-owned model. Backup restore tried once. No open Critical or High bug in BUGS.md. |
| M1 Planning closed | The SIP → AIP → SOB → APP → PR flow passes one end-to-end test and a browser check; the APP-link-required decision is recorded. |
| M2 Procurement closed | A PR reaches a PO with every official document printing; status transitions tested. |
| M3 Delivery closed | Delivery, inspection and acceptance records exist and gate the DV (if CONFIRMED). |
| M4 Money closed | ORS → DV → check → cancel/reissue passes end to end; ledgers if confirmed. |
| M5 Liquidation closed | Per the owner's confirmed scope. |
| M6 Reports | Each report reconciles to its source records. |

## 5. Development controls

Statuses: BACKLOG, READY, IN PROGRESS, TESTING, BLOCKED, DONE.

- **Work-in-progress limit: one major feature.** One task is IN PROGRESS at a time.
- A new need found mid-task is classified: (a) blocks the current task → record the dependency in TASKS.md, propose the minimum change, ask the owner; (b) does not block → add to BACKLOG. Never switch tasks without the owner's approval.
- DONE requires acceptance criteria met and verification evidence written in the task (test names and result, or the manual check done). Generated code alone is never DONE. A test counts only if it was executed.
- READY needs: approved by the owner, dependencies DONE, acceptance criteria written.
- Execution order for every task: read specs → confirm the approved task → inspect existing code → list dependencies → short plan → smallest change → run relevant tests → check related workflows → report → update status only when verified.
- Strict minimal change policy, the protected `docs/specs.md` rule, and the commit/push/deploy workflow in `vibe-app/CLAUDE.md` stay in force. No unrelated refactoring and no new packages without approval.

## 6. Dependency map

```
SIP (sip_projects, sip_activities)
  → AIP (aips, aip_kras, aip_activities)   [SIP imported from Excel or entered]
      → SOB (sob_plans, sob_items)          [approval creates budget allotments]
          → APP (app_items.sob_item_id)     [generated from approved SOBs]
          → APP-CSE (app_cse_items)         [PS catalog, 12 monthly quantities]
          → POW (pow_plans)                 [Form Generator from SOB items]
          → Allotment Registry / Budget Allocation
APP item ──→ Purchase Request (procurement_request_items.app_item_id; nullable)
PR ──→ RFQ → Abstract of Bids → Purchase Order → NTP   (procurement_documents)
PO ──→ ORS (obligation, liquidation_reports) ──→ DV (+ journal lines, deductions)
DV ──→ Cash payment, check; Cancelled Checks; cash reports
Every step logs to master_transactions / transaction_events (audit spine)
Shared: BelongsToOrganization scope, permissions (config/permissions.php), SignatorySlots,
        OfficialDocument print engine, DocumentNumberService, FiscalYearService
```

Requirements that need the owner's confirmation (not invented here):

1. Must a PR be linked to an approved APP item? (open item in `CLAUDE.md`)
2. Must an accepted delivery (IAR) exist before a DV can be created?
3. Is a general ledger / trial balance required, and in which form?
4. What is "Liquidation" to be beyond ORS/DV: checklist, deficiency returns, approval levels?
5. Is a forced password change on first sign-in required (specs section 19 says not implemented)?
6. Subscription: how are payments and renewals handled?
