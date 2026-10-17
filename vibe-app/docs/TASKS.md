# ProcureMS Tasks

Created 2026-10-17. Statuses: BACKLOG, READY, IN PROGRESS, TESTING, BLOCKED, DONE.
Only one task may be IN PROGRESS. Nothing starts without the owner's approval. A task is DONE only with evidence in "Verification result".
Priority: P1 critical, P2 high, P3 medium, P4 low.

## Sprint 1 (proposed, awaiting "go")

| ID | Module | Task | Pri | Depends on | Status | Acceptance criteria | Verification method |
|----|--------|------|-----|------------|--------|---------------------|---------------------|
| T-001 | Security | List the 118 routes without `permission:` middleware and record, for each, where the permission is enforced (controller check, own-record rule, or none). | P1 | none | DONE | A table in BUGS.md or this file: route, guard location, role allowed. Any route with no guard is logged as a bug. | Read the code; `php artisan route:list --json`. No code change. |
| T-002 | Security | Add denied-role tests for the weak spots T-001 found (B-010 AIP read and preview, B-011 AIP approve role, B-012 GET pages) and for the main write routes. Behavior changes need the owner's decision first. | P1 | T-001 | DONE | Each such route returns 403 for a role without the permission and works for the right role. | `php artisan test --filter=<new test>` executed; full suite still passes. |
| T-003 | Isolation | Review the 116 `withoutGlobalScopes` call sites; confirm each is master-only or filtered by organization/school. | P1 | none | DONE | Each call site classified safe or logged as a bug. | Code read; one cross-organization test per risky site. |
| T-004 | Isolation | Extend `OrganizationIsolationTest` to the money models (ORS/DV, payments, cancelled checks, POW, APP-CSE, cash fund receipts). | P2 | T-003 | DONE | A user of organization B gets 404/403 on every organization A record, by URL. | Tests executed. |
| T-005 | Data integrity | Restore test of the database backup (`/backup`) into a scratch SQLite file; list what the backup omits. | P2 | none | DONE | A restored copy opens and its table row counts match the source. | Run the restore on a copy; record counts. |
| T-006 | Docs | Record the PPMP retirement status: list code, routes, views still using `PpmpPlan`/`PpmpItem`. | P3 | none | READY | List only; no deletion. | Code search. |

## Backlog

| ID | Module | Task | Pri | Depends on | Status | Acceptance criteria | Verification method |
|----|--------|------|-----|------------|--------|---------------------|---------------------|
| T-010 | Planning/Procurement | Decide and, if required, enforce "PR must link to an approved APP item". CONFIRM first. | P2 | T-001 | BACKLOG | Owner decision recorded; if required, a PR without APP link is refused with a test. | `PrAppLinkTest` plus new test. |
| T-011 | Procurement | Browser check of the PR form APP-item picker and PR numbering/status end to end. | P2 | none | BACKLOG | Happy path and returned/rejected release of APP quantity shown in the browser. | Manual run with evidence; `PrAppLinkTest` executed. |
| T-012 | Planning | One end-to-end test SIP → AIP → SOB → APP → PR → PO (extends `PlanningFlowTest`). | P2 | T-011 | BACKLOG | Single test walks the chain and asserts the links on `master_transactions`. | Test executed. |
| T-020 | Delivery | Define delivery and inspection scope (partial delivery, accepted/rejected quantity, IAR). CONFIRM first. | P2 | T-012 | BLOCKED (owner decision) | Written scope approved by the owner. | Owner sign-off. |
| T-021 | Delivery | Route-level `inspection.*` permission for delivery and IAR actions (the permission exists, no route uses it). | P2 | T-020 | BACKLOG | Inspector role can record acceptance; others cannot. | Permission tests. |
| T-022 | Accounting | Decide whether a DV requires an accepted delivery. CONFIRM. | P2 | T-020 | BLOCKED (owner decision) | Decision recorded in specs by the owner. | Owner sign-off. |
| T-030 | Accounting | General ledger, subsidiary ledgers, trial balance. CONFIRM need and form first. | P3 | T-022 | BLOCKED (owner decision) | Ledger totals equal DV journal totals. | Reconciliation test. |
| T-040 | Liquidation | Supporting-document checklist and deficient/returned documents. CONFIRM scope first. | P3 | T-022 | BLOCKED (owner decision) | Per approved scope. | Per approved scope. |
| T-050 | Reports | Inventory which reports exist (only cash reports confirmed) and which are required. | P3 | none | BACKLOG | List agreed with the owner. | Code read. |
| T-060 | Platform | Subscription renewal/payment process. CONFIRM. | P4 | none | BLOCKED (owner decision) | Per decision. | Per decision. |
| T-061 | Platform | Owner sets Google OAuth client and three `GOOGLE_*` Railway variables; Turnstile keys. | P3 | owner | BLOCKED (owner action) | Drive connects on the live site; sign-in shows Turnstile. | Live check by the owner. |
| T-062 | Auth | Forced password change on first sign-in for accounts created with a temporary password. CONFIRM. | P3 | none | BLOCKED (owner decision) | New user must change password at first login. | Test executed. |
| T-070 | Code health | Split `HomeController` (2539 lines). Needs explicit owner approval under the minimal-change policy. | P4 | M0 done | BLOCKED (owner approval) | Behavior unchanged, full suite passes. | Full suite executed. |
| T-071 | Docs | Refresh the phase list and status in `CLAUDE.md` (PPMP→SOB). Update `HANDOFF_TO_CLAUDE.md` or retire it. Touch `specs.md` only with permission. | P4 | none | BACKLOG | Documents agree with the code. | Read-through. |

## Verification log

Add one line per completed task: date, task ID, tests run and result, manual checks.

- 2026-10-17: Baseline, full suite executed: 929 passed, 0 failed, 5705 assertions.
- 2026-10-17: T-001 DONE. Read all 118 routes without `permission:` middleware; every one of the 47 state-changing routes has a controller or service guard. Results in BUGS.md appendix; new items B-010, B-011, B-012. Code read only, no code changed, no tests added.
- 2026-10-17: T-003 DONE. Classified all 116 `withoutGlobalScopes` sites in `app/`; no cross-organization access found by code reading. New items B-013, B-014, B-015 (all need the owner's confirmation). Code read only, no code changed, no tests added.
- 2026-10-17: T-002 DONE. Added `tests/Feature/RoutePermissionGapsTest.php` (6 tests, 23 assertions) that pin the current behavior of B-010, B-011, B-012; no application code changed. They show what happens today, not what should happen: if the owner decides these routes must refuse a role, the matching assertion is flipped together with that change. Executed: new file 6/6 passed; full suite 935/935 passed, 5728 assertions.
- 2026-10-17: T-004 DONE. Added `tests/Feature/MoneyModelIsolationTest.php` (4 tests, 49 assertions): budget allocation, ORS/DV, journal line, cancelled check, cash fund receipt, SOB, Program of Works (plan and items) and APP-CSE of organization B are not listed for a user of organization A, 16 read/write URLs return 404 for A with B's data unchanged, the four cash reports refuse B's school, and a control shows B's own user opens the same records. Tests only, no application code changed. Executed: new file 4/4 passed; full suite 939/939 passed, 5777 assertions.
- 2026-10-17: T-005 DONE. Ran the app's backup routine on a scratch copy of the development database (the real file was not touched) and restored it: integrity ok, no foreign-key violations, 57/57 tables, 10,164 rows, row counts and a row-by-row checksum identical, app boots on it with no pending migrations. Omitted by the backup: uploaded logos and files (B-016). Not tested: Google Drive upload/download and the live Railway database (B-008). No application code changed.
