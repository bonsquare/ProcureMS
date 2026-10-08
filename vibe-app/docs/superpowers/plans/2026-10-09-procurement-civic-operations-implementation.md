# Procurement Civic Operations Implementation Plan

**Design specification:** `docs/superpowers/specs/2026-10-09-procurement-civic-operations-design.md`  
**Stack:** Laravel 13, Blade, Tailwind CDN, vanilla JavaScript, PHPUnit  
**Execution style:** Test-driven, incremental, preserving existing routes and official print output

## Objective

Implement the approved Civic Operations redesign across all Procurement interfaces. The work will introduce a shared Procurement shell, centralized workflow presentation logic, a portfolio overview, a paginated request registry, a request workspace, global document and receiving views, and consistent supplier/form/print controls. Existing authorization, calculations, document content, and workflow persistence remain the source of truth.

## Guardrails

- Preserve the user's unrelated working-tree changes.
- Do not introduce a JavaScript framework or asset bundler; the project currently uses Tailwind via CDN and has no `package.json`.
- Keep Inter as the approved typeface even though the UI guidance search suggested Atkinson Hyperlegible as an accessibility alternative.
- Use subtle transitions only and support `prefers-reduced-motion`.
- Meet 4.5:1 text contrast, visible keyboard focus, labeled icon controls, and 44×44 px primary touch targets.
- Paginate registries rather than loading every request or supplier into memory.
- Do not alter prescribed official-document body layouts or calculations.
- Every implementation task begins with a failing test and ends with the narrow test set passing.

## Task 1: Centralize Procurement workflow presentation

**Create:**

- `app/Services/ProcurementWorkspaceService.php`
- `tests/Unit/ProcurementWorkspaceServiceTest.php`

**Modify:**

- `app/Models/ProcurementRequest.php` only if a small relationship or read-only helper is necessary; keep presentation mapping in the service.

### Steps

1. Add unit tests covering representative request statuses and document combinations:
   - Draft → Request stage.
   - Submitted/pending approval → Approval stage.
   - For canvass or RFQ present → Canvass stage.
   - Abstract/award documents present → Award stage.
   - Purchase order present → Purchase Order stage.
   - IAR/receiving documents present → Receiving stage.
   - Completed → Complete stage.
2. Test that the service returns:
   - Ordered stage definitions.
   - Current stage.
   - Completed/current/pending/blocked state for each stage.
   - Semantic tone and accessible text label.
   - Permission-neutral next-action definition.
   - Document completeness and delivery summary.
3. Run `php artisan test --filter=ProcurementWorkspaceServiceTest` and verify the tests fail for the missing service.
4. Implement a single status/document-to-stage map in `ProcurementWorkspaceService`.
5. Keep the service read-only. It must not perform workflow transitions.
6. Re-run the unit test and commit only the task files.

## Task 2: Add feature coverage for the new Procurement navigation and scope

**Create:**

- `tests/Feature/ProcurementWorkspaceTest.php`

**Modify:**

- `routes/web.php`
- `app/Http/Controllers/HomeController.php`

### Steps

1. Write feature tests for these authenticated routes:
   - `procurement` — overview.
   - `procurement.requests` — request registry.
   - `procurement.show` — request workspace.
   - `procurement.documents.index` — global document center.
   - `procurement.receiving` — global receiving workspace.
   - Existing `suppliers` route remains valid and appears as the Suppliers area.
2. Test school-scoped users cannot see requests, suppliers, documents, or receiving information from another school.
3. Test master-user visibility according to existing organization/school scope rules.
4. Test that unauthorized direct request access returns the existing denial behavior.
5. Add routes in collision-safe order before `GET /procurement/{procurementRequest}`:
   - `GET /procurement/requests`
   - `GET /procurement/documents`
   - `GET /procurement/receiving`
   - `GET /procurement/{procurementRequest}`
6. Add thin controller actions that query scoped records, eager-load required relations, and delegate presentation calculations to `ProcurementWorkspaceService`.
7. Paginate request/document/receiving collections. Preserve filter values in pagination links.
8. Run `php artisan test --filter=ProcurementWorkspaceTest` and commit the task.

## Task 3: Build the shared Civic Operations shell and primitives

**Create:**

- `resources/views/layouts/procurement.blade.php`
- `resources/views/partials/procurement/navigation.blade.php`
- `resources/views/partials/procurement/module-tabs.blade.php`
- `resources/views/components/procurement/status-badge.blade.php`
- `resources/views/components/procurement/stage-rail.blade.php`
- `resources/views/components/procurement/empty-state.blade.php`
- `resources/views/components/procurement/metric-card.blade.php`
- `public/css/procurement.css`
- `tests/Feature/ProcurementShellTest.php`

### Steps

1. Write view-level feature assertions for:
   - One main navigation landmark and one Procurement local-navigation landmark.
   - Active module tab using `aria-current="page"`.
   - A visible skip link and keyboard focus styles.
   - Text labels on status badges.
   - Mobile navigation button with accessible name and expanded state.
2. Run the focused test and verify failure.
3. Create the shared layout by extracting duplicated shell behavior from existing Procurement pages.
4. Centralize Tailwind CDN configuration and approved color roles in the layout.
5. Add `public/css/procurement.css` for stable semantic tokens, focus rings, responsive patterns, table/card switching, and reduced-motion behavior that should not be repeated in utility strings.
6. Implement the reusable components with semantic HTML and no business queries inside views.
7. Add minimal vanilla JavaScript for the responsive navigation, ensuring Escape closes it and focus returns to the trigger.
8. Run `php artisan test --filter=ProcurementShellTest` and commit the task.

## Task 4: Implement the Procurement Overview and paginated Requests registry

**Modify:**

- `app/Http/Controllers/HomeController.php`
- `resources/views/procurement.blade.php`

**Create:**

- `resources/views/procurement-requests.blade.php`
- `resources/views/partials/procurement/task-queue.blade.php`
- `tests/Feature/ProcurementOverviewTest.php`

### Steps

1. Write tests for portfolio metrics, role-aware school copy, urgent task queue items, aging/exception indicators, and recent activity.
2. Write request-registry tests for:
   - Search by request number/title.
   - Status/stage filter.
   - School filter for master users only.
   - Pagination.
   - Empty registry vs. no matching results.
   - Visible request-stage label and direct “Open workspace” action.
3. Verify focused tests fail.
4. Refactor `HomeController::procurement()` into the overview query while moving the complete registry to `procurementRequests()`.
5. Replace the current duplicated full HTML page with views extending `layouts.procurement`.
6. Implement approved overview sections:
   - Compact metric cards.
   - Prioritized task queue.
   - Requests needing attention.
   - Stage distribution.
   - Recent activity.
7. Implement the dense but responsive registry. Use card-style rows below 768 px and retain explicit labels.
8. Remove dead or placeholder actions such as an export control that performs no export.
9. Run `php artisan test --filter=ProcurementOverviewTest` and the shell tests, then commit.

## Task 5: Implement the Request Workspace

**Modify:**

- `app/Http/Controllers/HomeController.php`
- `routes/web.php`

**Create:**

- `resources/views/procurement-show.blade.php`
- `resources/views/partials/procurement/request-summary.blade.php`
- `resources/views/partials/procurement/request-items.blade.php`
- `resources/views/partials/procurement/request-documents.blade.php`
- `resources/views/partials/procurement/request-activity.blade.php`
- `resources/views/partials/procurement/next-action.blade.php`
- `tests/Feature/ProcurementRequestWorkspaceTest.php`

### Steps

1. Write tests asserting the workspace renders:
   - Header identifiers, school, amount, owner, and status.
   - Seven-stage rail and correct current stage.
   - Summary, Items, Documents, and Activity tabs/sections.
   - Linked transaction and audit events when present.
   - A permission-aware primary next action.
   - Missing-requirement explanation when progress is blocked.
2. Verify the tests fail.
3. Implement `HomeController::showProcurement()` with scoped authorization and eager loading for school, requester, items, documents/creator, transaction/events, and liquidation links.
4. Build the request workspace as the canonical destination for a request. Registry, document center, receiving, and transaction links should return here where appropriate.
5. Use query-string deep links for workspace sections, e.g. `?section=documents`, while rendering all essential content server-side.
6. Ensure unavailable actions are omitted or explicitly disabled with an explanation according to usefulness.
7. Run `php artisan test --filter=ProcurementRequestWorkspaceTest` and commit.

## Task 6: Migrate create/edit Request forms into the shared workspace

**Modify:**

- `resources/views/procurement-create.blade.php`
- `app/Http/Controllers/HomeController.php` only for presentation data or redirect destinations.
- `tests/Feature/PrAppLinkTest.php`

**Create:**

- `tests/Feature/ProcurementRequestFormTest.php`

### Steps

1. Add tests for visible labels, inline validation, error summary, preserved old input, item-row controls, and successful redirects to the new request workspace.
2. Preserve all existing APP-item and budget-availability validation tests.
3. Verify the new tests fail.
4. Convert the standalone form page to extend `layouts.procurement`.
5. Group the form into clear sections: Request details, Funding and linkage, Items, and Review/submit.
6. Replace the current inline-styled submit input with standard button components and one clear primary action.
7. Keep dynamic item rows keyboard-usable, label add/remove buttons, and announce row changes through a polite live region.
8. Redirect successful create/update operations to `procurement.show` with a success message.
9. Run `php artisan test --filter='ProcurementRequestFormTest|PrAppLinkTest'` and commit.

## Task 7: Migrate Supplier Manager

**Modify:**

- `app/Http/Controllers/HomeController.php`
- `resources/views/suppliers.blade.php`
- `tests/Feature/SaasFoundationTest.php`

**Create:**

- `tests/Feature/ProcurementSupplierWorkspaceTest.php`

### Steps

1. Add tests for school scoping, pagination, search, active/inactive filtering, empty states, permissions, and validation feedback.
2. Verify the tests fail.
3. Extend the shared Procurement layout and activate the Suppliers tab.
4. Replace duplicated navigation and shell markup.
5. Present a supplier registry with explicit contact, eligibility identifiers, status, and actions.
6. Retain create/edit capability with progressive disclosure for owner and compliance fields.
7. Ensure supplier selection data used in Procurement documents remains compatible.
8. Run `php artisan test --filter='ProcurementSupplierWorkspaceTest|SaasFoundationTest'` and commit.

## Task 8: Redesign the global and request-level Document Center

**Modify:**

- `app/Http/Controllers/HomeController.php`
- `resources/views/procurement-documents.blade.php`

**Create:**

- `resources/views/procurement-documents-index.blade.php`
- `resources/views/components/procurement/document-row.blade.php`
- `tests/Feature/ProcurementDocumentCenterTest.php`

### Steps

1. Add tests for:
   - Global document list scoped by school/organization.
   - Request, document number/type, stage, completeness, author, and update date.
   - Filters for type, stage/status, request, and school where authorized.
   - Request-level document checklist and missing requirements.
   - Existing create/update and print links.
2. Verify the tests fail.
3. Build the global document center as an operational registry.
4. Convert request-level `procurement-documents.blade.php` to the shared shell and preserve all current document form fields and JavaScript behavior.
5. Break the 500-line view into focused partials by document group/form concern without changing submitted field names.
6. Replace generic modal content with a labeled dialog, focus trap, Escape behavior, and focus restoration.
7. Expose “View & Print,” “Edit,” “Create,” and “Missing requirement” states explicitly.
8. Run `php artisan test --filter='ProcurementDocumentCenterTest|OfficialPrintTest'` and commit.

## Task 9: Implement Receiving workspace and operational reconciliation

**Modify:**

- `app/Http/Controllers/HomeController.php`
- `resources/views/procurement-delivery-reconciliation.blade.php` only for its screen toolbar integration; preserve printed body.

**Create:**

- `resources/views/procurement-receiving.blade.php`
- `resources/views/partials/procurement/receiving-table.blade.php`
- `tests/Feature/ProcurementReceivingWorkspaceTest.php`

### Steps

1. Add tests covering complete delivery, partial delivery, discrepancy balance, missing PO/IAR, scoped visibility, and links to the request/document records.
2. Verify the tests fail.
3. Extract receiving-row calculation into a reusable read-only service method so the operational screen and printable reconciliation report use identical totals.
4. Implement the global Receiving tab with ordered, received, balance, status, supplier, and latest receiving document.
5. Prioritize partial/discrepancy rows and provide a direct action to complete or inspect the relevant document workflow.
6. Preserve the current letter-sized reconciliation print layout and test its totals against the operational view.
7. Run `php artisan test --filter=ProcurementReceivingWorkspaceTest` and commit.

## Task 10: Unify official preview/print controls without changing official forms

**Modify:**

- `resources/views/partials/official-toolbar.blade.php`
- `resources/views/procurement-print.blade.php`
- `resources/views/procurement-document-print.blade.php`
- `resources/views/procurement-delivery-reconciliation.blade.php`
- `public/css/procurement.css`
- `tests/Feature/OfficialPrintTest.php`

### Steps

1. Expand print tests to assert the shared screen-only toolbar, accessible Close and Print labels, and unchanged print-only behavior.
2. Verify tests fail for the new toolbar contract.
3. Apply Civic Operations styling to the non-print toolbar only.
4. Add document/request context in the screen toolbar where it does not print.
5. Keep every official form body, paper sizing, logos, line weights, and content intact.
6. Verify `@media print` hides all application controls.
7. Run `php artisan test --filter=OfficialPrintTest` and commit.

## Task 11: Complete cross-module links, responsive behavior, and regression coverage

**Modify:**

- `resources/views/transaction-show.blade.php`
- Procurement views and CSS touched above.
- Existing feature tests where route expectations change.

**Create:**

- `tests/Feature/ProcurementWorkflowNavigationTest.php`

### Steps

1. Add an end-to-end server-rendered navigation test using the complete workflow seed:
   - Procurement overview → Requests → Request workspace.
   - Request → Documents → official preview.
   - Request → Receiving/reconciliation.
   - Request → linked transaction timeline and back.
2. Update transaction Procurement links to point to `procurement.show` instead of print/edit when the intent is to inspect a request.
3. Ensure every Procurement route has a predictable back path and active navigation state.
4. Test focus-visible styles and accessible labels through rendered markup assertions.
5. Manually verify viewport widths 375, 768, 1024, and 1440 using the seeded workflow.
6. Manually verify keyboard navigation for menus, dialogs, tabs/section links, dynamic request items, and document forms.
7. Confirm horizontal scrolling is limited to deliberately wide data/official-document surfaces.
8. Run focused Procurement tests, then the full suite:
   - `php artisan test --filter=Procurement`
   - `php artisan test`
9. Run formatting and syntax checks:
   - `vendor/bin/pint --test`
   - `php artisan route:list --name=procurement`
10. Review the final branch diff for accidental official-form or unrelated changes.
11. Commit the final integration task.

## Final Verification Checklist

- [ ] All Procurement operational pages extend the shared Civic Operations layout.
- [ ] Overview, Requests, Suppliers, Documents, and Receiving have deep-linkable destinations.
- [ ] Request workspace shows stage, owner, blockers, documents, activity, and one next action.
- [ ] Registries are paginated and filters preserve query parameters.
- [ ] School/organization scope and permissions are unchanged and tested.
- [ ] Official print bodies are unchanged and print controls remain screen-only.
- [ ] Partial deliveries and discrepancies agree across operational and printable views.
- [ ] Keyboard focus, labels, dialog behavior, reduced motion, and status semantics are verified.
- [ ] Core flows work at 375, 768, 1024, and 1440 px.
- [ ] Focused and full automated suites pass.

## Expected Implementation Sequence

Execute Tasks 1–3 first because every page depends on workflow mapping and the shared shell. Tasks 4–7 may then proceed in order to establish the core user journey. Tasks 8–10 handle the document/receiving boundary where regression risk is highest. Task 11 is the integration and quality gate.

