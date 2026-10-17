# ProcureMS Bugs and Risks

Created 2026-10-17. No defect has been reproduced in the running app yet: the full suite passes (929/929) and the audit was read-only. Items below are **suspected risks from code reading** until reproduced. Status values: OPEN, CONFIRMED, FIXED, WONT FIX. A bug is FIXED only after a test or check is executed.

| ID | Module | Description | Severity | Reproduction steps | Expected | Actual | Status |
|----|--------|-------------|----------|--------------------|----------|--------|--------|
| B-001 | Authorization | 118 routes (plus the `/privacy` view) carry no `permission:` route middleware. T-001 (2026-10-17) read every one: all 47 state-changing ones have a controller or service guard (see appendix); no unguarded write found. Denied-role tests are still missing (T-002). | Low after T-001 | Read-only code review. | Guard on every write | Guard found on every write | CODE-REVIEWED; weak spots pinned by tests (T-002) |
| B-002 | Tenant isolation | T-003 (2026-10-17) classified all 116 `withoutGlobalScopes` sites in `app/` (see appendix 2): none lets a tenant user read or write another organization's data by code reading. Every service site works on a school or plan already authorized by its controller. Money models proven by `MoneyModelIsolationTest` (T-004). | Low after T-003 | Read-only code review. | No cross-organization access | None found | CODE-REVIEWED; money models pinned by tests (T-004) |
| B-003 | Delivery and Accounting | FIXED 2026-10-17 by the owner's decisions D5 and D6. Saving an Inspection and Acceptance Report (IAR) now needs `procurement.edit` or `inspection.edit` (document save and preview routes use `permission:procurement.edit|inspection.edit`; every other document still needs `procurement.edit`, checked in `storeProcurementDocument`). A DV for an ORS made from a Purchase Order is refused (422) until the request has an IAR; an ORS without a PO, "New DV (no ORS)" and the edit of an existing DV are not held. A blank received quantity on a new IAR is refused. Older IARs keep their stored values. | Medium | Create DV for a PO-linked ORS with no IAR. | Refused with a message | Refused | FIXED; `DeliveryAcceptanceRulesTest` (6 tests) and full suite pass |
| B-004 | Auth | A user created by Add User gets a temporary password with no forced change (stated in specs section 19). | Medium | Add User, sign in with the temporary password. | Prompt to change password | No prompt | OPEN (known, documented) |
| B-005 | Documentation | `CLAUDE.md` phase order still lists PPMP, replaced by SOB. Root `HANDOFF_TO_CLAUDE.md` is stale. | Low | Read both files. | Match the code | Differ | OPEN |
| B-006 | Code health | PPMP leftovers, listed by T-006 (2026-10-17), nothing deleted. See appendix 3. In short: no route, controller action or view creates a PPMP any more; the tables and models stay because older APP items point at them and because they keep an AIP from being deleted. | Low | Code search. | Owner decides what may be retired | Listed | LISTED; retirement is the owner's decision |
| B-007 | Operations | RESOLVED 2026-10-17 by T-007: the live Railway variables are `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`. Only these keys were read; no secret was printed. | Low | `railway variables --service procurems --json`, filtered. | `APP_DEBUG=false` | `APP_DEBUG=false` | RESOLVED, VERIFIED on the live service |
| B-008 | Operations | Backup restore. T-005 (2026-10-17): the app's own backup routine (`DatabaseBackupService::copyToTempFile`, `VACUUM INTO`) was run on a scratch copy of the local development database (57 tables, 10,164 rows); the result passed `integrity_check`, `foreign_key_check` found nothing, every table's row count and a checksum of every row matched the source, and the app ran on it (113 migrations ran, none pending; records readable). Not tested: the Google Drive upload and download legs (covered only by the faked tests), and the live Railway database. The database is still one SQLite file on one volume, one replica. | Low after T-005 | Scratch copy, backup routine, compare script (not kept in the repo). | A restored copy matches | Matches (development data) | RESTORE VERIFIED on a development copy; live Drive legs UNVERIFIED |
| B-009 | Workflow | PR form, APP link, numbering and status, checked in the browser by T-011 (2026-10-17) on a scratch copy of the development database. Result: the form now starts from an SOB activity (spec 31.1), not the old APP picker; with the APP still draft the activity list is empty ("No SOB activity with items left to request"); after the APP was approved 3 activities appeared, choosing one filled the line (Bond Paper, 10 ream, ₱300, linked to APP item 8, total ₱3,000); the PR saved as PR-2026-002 (the organization's next number); Return released the quantity, Submit for approval held it again (activity gone from the list), Approve worked; no console errors. Not run in the browser: Reject (same release rule in code, covered by `PrAppLinkTest`), the PO step. | Low after T-011 | See result. | Works end to end | Works, except B-017 | VERIFIED in browser (except Reject and PO) |
| B-010 | Authorization (AIP) | FIXED 2026-10-17 by the owner's decision D2 (AIP view requires `aip.view`). `aip.show`, `aip.print` and the two AIP preview routes now carry `permission:aip.view`. Roles without it (for example `office_user`, `encoder`, `bac_user`, `procurement_officer`) get 403. Side effect to know: a role that has `planning.view` but not `aip.view` (`procurement_officer`) sees AIP links on the Planning page that now lead to 403. | Medium | Sign in as `office_user`, open `/aip/{id}/print`. | 403 | 403 | FIXED; `RoutePermissionGapsTest` (3 tests) and full suite pass |
| B-011 | Authorization (AIP) | AIP changes are guarded by `canManageBudget()` (`budget.manage`), not an `aip.*` permission, although roles are given `aip.*`/`aip.approve`. A role with `aip.approve` only (`school_head`) cannot approve through the controller. | Low | Sign in as `school_head`, POST `/aip/{id}/approve`. Run by `RoutePermissionGapsTest`. | Matches the role table (CONFIRM intent) | 403, confirmed by test | DECIDED by the owner (2026-10-17): no change. The budget manager approves the AIP; each school has one user, so the school_head role is not used in practice. |
| B-012 | Read access | FIXED 2026-10-17 by the owner's decision D4. `GET /reports` needs `reports.view`, `GET /procurement/create` needs `procurement.create`, `GET /accounting/chart-of-accounts` needs `accounting.view` or `accounting.manage` (checked in the controller, because `accounting.manage` does not imply `accounting.view`, and the budget officer manages the chart). `GET /dashboard/diagnostics` stays open to every signed-in role by decision. Known effect: `office_user` and `encoder` (no `reports.view`) now get 403 on Reports; `viewer`, `school_head`, `procurement_officer` and similar get 403 on the Chart of Accounts if they have no accounting permission. | Low | Sign in as `office_user`, open `/reports`. | 403 | 403 | FIXED; `RoutePermissionGapsTest` (3 tests) and full suite pass |

| B-013 | Tenant isolation | `StationTransferController@create` lists every active school in the system (name and whether occupied) to any signed-in user so they can pick a transfer destination, across organizations. Names only. | Low | Sign in as a school user and open the transfer request form. | CONFIRM that listing all schools is intended | Lists all schools | OPEN, CONFIRM with owner |
| B-014 | Sub-master scope | NOT A BUG, by design (T-014, 2026-10-17). In specs.md section 18 a Sub-master "works like the master across every school"; the checklist "areas" are work and management areas (permission families such as `accounting.*`, and `schools`, `users`, `backup`...), not regions or organizations. A Sub-master with the `users` area sees users and audit logs of every school; without it they get 403. Covered by `SubMasterGatesTest`, `SubMasterVisibilityTest`, `SubMasterAccessTest`, `UserDirectoryTest` (37 tests, 260 assertions, passed). | n/a | Run the four test files. | Matches spec 18 | Matches | CLOSED, by design |
| B-015 | School isolation | Access checks (`scopedSchoolIds`, AIP `schoolIds`) allow every school of the user's organization, not only the user's own school. Registration creates one organization per school, so this is harmless today; it matters if one organization holds several schools. | Low | Put two schools in one organization; sign in as one school's user; open the other school's AIP. | CONFIRM intended | Expected to open (code reading) | OPEN, CONFIRM with owner |
| B-016 | Operations | The backup holds the database file only. Uploaded logos and files live on the Railway volume at `/data/public` and are not in it, so restoring a backup on a new volume would bring back records but not the uploaded logos (they would need uploading again; a missing logo prints blank). | Low | Read `DatabaseBackupService` and `deploy/entrypoint.sh`. | CONFIRM whether files must be backed up too | Database only | OPEN, CONFIRM with owner |
| B-017 | Procurement | On the New Purchase Request form, a master user sees a PR Number preview (PR-2026-003) that is for no organization: choosing a school does not update it, and the number saved was the chosen school's next number (PR-2026-002). Cause by code reading: `nextPurchaseRequestNumber(false)` uses the signed-in user's organization, which a master user does not have. The field is display only (it has no name and is not submitted), so the saved number is correct. | Low | Sign in as master, open Procurement > New Request, choose Lubas Elementary School, compare the preview with the saved PR number. Reproduced 2026-10-17. | The preview shows the chosen school's next number | Shows another sequence's number | CONFIRMED in browser; not fixed (needs owner approval) |
| B-018 | Reports | The Reports page (`/reports`, `HomeController::reports`, view `reports.blade.php`) shows hard-coded sample content and reads nothing from the database: the four tiles (Total Procurement ₱4.82M, Total Liquidated ₱3.96M, Outstanding Balance ₱860K across 14 reports, Average Processing 4.2 days), the monthly chart (labelled 2025), "Spending by School" with five fixed school names and amounts, and "Recent Exports" with fake files (for example "Q3 Accounting Compliance.pdf"). A user with `reports.view` reads these as real figures. The real reports are elsewhere (appendix 5). | Medium | Sign in as a school admin, open Reports, compare with the school's own data. Read from the code 2026-10-17; the controller passes no data and the view has no database reference. | Figures from the school's own records, or no figures | Fixed sample figures | FIXED 2026-10-17 by the owner's decision D13: the sample figures, the fake period and Export button, the chart, the school list, the Custom Report button and the fake exports are gone; the page lists only links to real reports, each shown to roles that can open it (`ReportsPageTest`, 4 tests; full suite passes) |

## Appendix: T-001 result (2026-10-17)

Method: `php artisan route:list --json`, then for each route without `permission:` middleware the controller method and its helpers were read for a guard. Read-only; no code changed.

| Group | State-changing routes | Where enforced |
|-------|-----------------------|----------------|
| AIP (13: store, update, destroy, approve, KRA and activity edits, budget/fund updates) | 11 | `authorizeManage()` (`canManageBudget`) plus `authorizeAip()` (school). The 2 `preview` routes: see B-010. |
| Budget Allocation (4: store, close, update, destroy) | 4 | `authorizeManage()`, `authorizeItem()`, `schoolIds()`; closed items refused. |
| Procurement (preview, status change) | 2 | `canCreateProcurement`, `authorizeSchoolAccess`, `authorizeProcurementAccess`; status uses `procurement.approve` or `procurement.edit/create` plus a transition table. |
| User management (users, sub-masters, master user) | 10 | `authorizeUsers`, `authorizeMaster`, `master()` (`isAnyMaster`); master rename via `canRename`. |
| School Management (status, users, handover) | 4 | `master()` = `hasAccess('schools')`. |
| Station transfer and takeover | 8 | Master actions: `abort_unless` / `afterDecision`. User actions (`store`, `cancel`, `review`, `confirm`): service checks the request belongs to the actor (`StationTransferService`), `confirm` selects only the actor's own request. |
| Master-only (backup, demo reset, screen, school approve, create school) | 5 | `authorizeMaster`, `isMaster`, `abort_unless`. |
| Google Drive and Drive files | 3 | Scoped to `user_id` of the signed-in user; `drive.connected` middleware on upload. Own-data only. |
| Login, register, logout | 3 | Public by design; login and register are throttled. |

Not checked by T-001: whether each guard returns the right answer for every role. That is T-002.

## Appendix 2: T-003 result (2026-10-17)

116 uses of `withoutGlobalScope(s)` in `app/` (seeders and the 2 in `app/Console` commands excluded; they run by the operator, not by a tenant user). Per-group counts are approximate. Read-only.

| Group | Sites | Why it bypasses the scope | Safe because |
|-------|-------|---------------------------|--------------|
| `BelongsToOrganization` trait, `User` boot, `SchoolStaff`, `ChartOfAccount::ensureDefaults` | 13 | Saving hooks and self-maintenance (username, employee number, default chart) | Keyed by the model's own id or an explicit organization id |
| `SobService`, `AppCseService`, `SipAipService`, `SipImportService`, `FiscalYearService`, `MasterTransactionService` | 37 | Services run on a plan or school the controller already loaded or authorized (`Rule::in(schoolIds)`, route binding through the scoped model, `AppCseController::scoped`) | Each query is filtered by that school, plan or aip id |
| `DashboardKpiService` | 10 | Reads for a list of school ids | List comes from `scopedSchoolIds()` (organization schools, or all for master) |
| `AipController`, `PlanningController` | 4 | "Is this AIP/SOB/PPMP still used" checks before delete | Boolean `exists()` on an already authorized record |
| `BudgetAllocationController`, `CashReportController`, `FinanceController`, `HomeController` (agency settings, school lookups) | 12 | Lookup of organization id or agency settings from a school or report | The school id was validated first (`Rule::in`, `authorizeSchoolAccess`) or the report was route-bound through the scoped model |
| `UserDirectoryController`, `SubMasterController`, `SchoolManagementService`, `AuthController` | 16 | Master-area screens and sign-in lookup | Guarded by `authorizeUsers` / `authorizeMaster` / `hasAccess('schools')`; see B-014 for Sub-master scope |
| `StationTransferService/Controller`, `SchoolTakeoverService`, `EnsureAccountActive`, `SharedLogoBackfill`, `DatabaseBackupService`, `DemoResetService` | 22 | Cross-school workflows run by master or by the requester on their own request (includes 9 relations on `StationTransferRequest` and `SchoolTakeoverRequest` so names show) | Ownership of the request checked (`user_id === actor`); see B-013 |

Models without the tenant trait: `DriveFile`, `GoogleDriveConnection` (scoped by `user_id` in code), `StationTransferRequest`, `SchoolTakeoverRequest` (master workflow), `SharedLogo` and `CseCatalogItem` (shared on purpose), `DocumentCounter` (always queried with an explicit organization id), `Organization`, `BackupRun/BackupDownload`, `SystemSetting`. No tenant data found among them.

## Appendix 3: T-006 result, PPMP leftovers (2026-10-17)

Search: `ppmp` (any case) in `app`, `routes`, `resources`, `config`, `database`, `tests`. Read only; nothing changed or deleted. No route in `routes/` mentions PPMP.

| Where | What | Still used for |
|-------|------|----------------|
| `app/Models/PpmpPlan.php`, `PpmpItem.php` | Models of tables `ppmp_plans`, `ppmp_items` | Reading old PPMPs |
| `database/migrations/2026_10_07_000007_add_planning_and_master_transactions.php` | Creates both tables, `app_items.ppmp_item_id` (unique with `app_plan_id`), and adds `master_transaction_id` to `ppmp_plans` | History; cannot be edited safely |
| `database/migrations/2026_10_11_120001_create_sob_tables.php` | Makes `app_items.ppmp_item_id` nullable and adds `sob_item_id` | The switch to SOB |
| `app/Models/AppItem.php` | `ppmp_item_id` in `$fillable` and the `ppmpItem()` relation | Old APP items (3 in the development database) |
| `app/Http/Controllers/PlanningController.php` (lines 14, 56-58, 433-437, 497-500) | `legacyPpmpAipIds`, eager loads `items.ppmpItem`, falls back to the PPMP's master transaction when logging APP events | Old APP items; stops an AIP with an old PPMP from being deleted |
| `app/Http/Controllers/AipController.php` (lines 13, 307-308) | Refuses to delete an AIP that has a PPMP, SOB or budget | Delete guard |
| `app/Services/MasterTransactionService.php` (lines 7, 39) | A master transaction with a PPMP counts as "in use" | Delete guard |
| `app/Services/AppItemLinkService.php` (lines 60-63) | Eager loads `appItem.ppmpItem`, falls back to its master transaction for the `pr_linked` event | Old APP items |
| `resources/views/planning.blade.php` (lines 332, 491) | `$legacyPpmpAipIds` check, and one more PPMP reference at line 491 | Same delete guard |
| `database/seeders/PlanningDemoSeeder.php` | Still builds a demo PPMP and APP items from it | Demo data only |
| Tests: `PpmpRemovedTest`, `SobAppTest`, `PrAppLinkTest`, `PlanningEditDeleteTest`, `SobModelsTest`, `AipDeleteTest`, `AipApprovalReportOnlyTest` | Cover that the PPMP is gone from the UI and that old PPMPs still behave | Regression |

Data: the development database has 1 PPMP plan, 3 PPMP items and 3 APP items linked to them. The live database was not inspected (UNVERIFIED).

Why it cannot simply be removed: old APP items, the delete guards and the event fallback read these tables. Retiring them would need a data migration and the owner's approval (strict minimal change policy). Nothing is blocked by leaving them.

## Appendix 4: T-020a result, delivery and inspection as built (2026-10-17)

Read only; nothing changed. Sources: `HomeController` (`storeProcurementDocument`, `procurementReceiving`, `printDeliveryReconciliation`), `ProcurementWorkspaceService::receiving`, routes, `FinanceController`, `LiquidationReport`.

| Question | What the code does |
|----------|--------------------|
| What records a delivery? | One Inspection and Acceptance Report (IAR) per request, a `procurement_documents` row of type `inspection_acceptance_report`. It needs the Purchase Order first. |
| Received quantity | Typed per PO line in `metadata.received_items`. The server refuses a quantity above the PO quantity. A blank value counts as fully received (the PO quantity), so an IAR saved with blanks reads as Complete. |
| Partial delivery | Shown, not recorded as separate deliveries: per line `balance = ordered - received`, status Partial or Complete, request status partial, complete, or missing PO/IAR. There is one IAR, so a later delivery means editing that same IAR; the earlier quantities are overwritten. No delivery date or delivery-receipt number is kept apart from the IAR date. |
| Accepted versus rejected quantity | Not present (no such field or column). |
| Inspector | `inspection_officer_name` typed in the IAR, or taken from staff whose document role or position says inspection officer. |
| Who may act | `storeProcurementDocument` needs `procurement.edit`. The `inspection.*` permission is granted to role `inspector` and to the Sub-master `procurement` area but no route checks it (B-003). The `inspector` role has only `procurement.view`, so by the table it cannot save an IAR. |
| After the IAR | Inventory and Acknowledgement Receipt of Supplies, Requisition and Issuance Slip, then optional Inventory Custodian Slip and Property Acknowledgement Receipt, each limited by the received quantity. |
| Prints | The IAR, and a Delivery Reconciliation (PO versus received, partial and complete rows), needing both PO and IAR. |
| Link to payment | None. Nothing in `FinanceController`, the ORS or DV creation, or `LiquidationReport` reads an IAR or the received quantity. An ORS can be made from a PO and a DV paid with no IAR. (This is decision D6.) |
| Tests | `ProcurementReceivingWorkspaceTest`, `ProcurementWorkspaceTest`, `ProcurementDocumentCenterTest` cover the Receiving page and documents. |

Gaps for the owner to rule on (D5, D6): no accepted/rejected quantity, no separate deliveries, a blank counts as fully received, no payment gate, and `inspection.*` unused.

## Appendix 5: T-030a and T-050 results, Accounting, Cash and reports as built (2026-10-17)

Read only; nothing changed. Sources: `routes/web.php`, `FinanceController`, `BudgetAllocationController`, `CashReportController`, `CashReportService`, `DashboardKpiService`, `HomeController::reports`, `reports.blade.php`.

**Accounting (T-030a)**

| Feature | State |
|---------|-------|
| Chart of accounts per organization (standard accounts, add/edit/delete with `accounting.manage`) | Built |
| ORS (obligation) from a PO or by hand; review by Accounting (for review, pending documents, approved, returned) | Built |
| DV with deductions, journal entry (at least one debit and one credit; stored per line in `dv_journal_lines`: account code, title, debit, credit) | Built |
| Journal totals check | Per DV only: the form needs at least two lines; whether debits equal credits is checked per DV (not re-verified in this inventory) |
| General ledger, subsidiary ledgers, trial balance | **Not present.** No route, query or view adds the journal lines up by account. `DvJournalLine` is read only by the DV print and by `CashReportService` (first debit line, for the Cash Disbursement Register). |
| Budget side | Allotment Registry, Budget Allocation, 12 budget reports with CSV (below), `budget/balance` position per school and fund |

**Cash**

| Report or screen | Where | State |
|------------------|-------|-------|
| Pay and cancel check, cancelled checks register, cash fund receipts | Cash tab | Built, tested |
| Report of Checks Issued (Appendix 35), Checks and Advices, Cash Disbursement Register, Bank Reconciliation | `cash.report.*`, need `cash.pay` | Built, tested (`CashReportPrintTest`, `CashReportServiceTest`) |

**Reports that exist and read real data**

| Report | Where |
|--------|-------|
| 12 budget reports: Annual Allocation, Quarterly Utilization, Budget vs Obligation, Budget vs Liquidation, Remaining Balance, Fund Source Summary, Expense Item Summary, Office/Department, Over Budget / Near Limit Alerts, Obligated but Not Yet Liquidated, Liquidated Expenses, Chart of Accounts Budget Report (screen and CSV) | `budget.allocation.report` |
| Dashboard KPIs (procurement, liquidation, budget, suppliers, planning steps) | `DashboardKpiService`, `DashboardKpiTest` |
| Official prints: SIP, AIP, SOB, APP, APP-CSE, POW, PR, procurement documents, ORS, DV, delivery reconciliation | Each module |

**Reports that do not exist or are not real**

| Item | State |
|------|-------|
| The Reports page (`/reports`) | Sample content only (B-018) |
| Procurement expenditure report, Accounting compliance report, liquidation register, supplier report, subscription report | Named on the Reports page as "Available Reports" but only AIP, Budget, Allotment Registry, Chart of Accounts, Accounting and Cash have links; the others have no data behind them (UNVERIFIED which tile has no link; the page carries no data at all) |
| Audit log (screen and CSV export) | Built for the master and Sub-master with `users` |

For the owner to rule on: D8 (ledgers and trial balance) and D13 (Reports page: show real figures, link the real reports, or leave).
