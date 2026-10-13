# Handoff to Claude

## Objective

Continue diagnosing and fixing missing or unclear buttons in the Procurement module of the Laravel application in `vibe-app`.

The application is currently available at `http://127.0.0.1:8000/procurement` and the signed-in account is a master administrator.

## Repository state

- Branch: `master`
- HEAD: `e6070f3 fix: move procurement form into civic shell`
- The working tree is intentionally dirty. Preserve all existing modifications and untracked files.
- Do not use `git reset --hard`, `git checkout --`, broad cleanup, or repo-wide formatting.
- Several Finance, print-template, specification, migration, and configuration changes predate the latest Procurement work and belong to the user.

## Procurement work already implemented

### Admin school filtering

Master administrators can filter Procurement records by school across:

- Overview
- Requests
- Documents
- Receiving
- Suppliers

The selected `school_id` is retained while navigating Procurement module tabs and task links.

Primary files:

- `vibe-app/app/Http/Controllers/HomeController.php`
- `vibe-app/resources/views/layouts/procurement.blade.php`
- `vibe-app/resources/views/partials/procurement/module-tabs.blade.php`
- `vibe-app/resources/views/partials/procurement/task-queue.blade.php`
- `vibe-app/resources/views/procurement.blade.php`
- `vibe-app/tests/Feature/ProcurementSchoolFilterTest.php`

### Responsive action visibility

Wide tables previously placed action columns off-screen between tablet and small desktop widths. The responsive card breakpoint was raised to 1439px, and action-complete cards were added for Documents, Receiving, and Suppliers. The `New request` label is no longer hidden on small screens.

Primary files:

- `vibe-app/public/css/procurement.css`
- `vibe-app/resources/views/procurement-requests.blade.php`
- `vibe-app/resources/views/procurement-documents-index.blade.php`
- `vibe-app/resources/views/partials/procurement/receiving-table.blade.php`
- `vibe-app/resources/views/suppliers.blade.php`
- `vibe-app/resources/views/partials/procurement/supplier-edit-form.blade.php`
- Corresponding Procurement feature tests

Browser verification confirmed that narrow layouts expose `Open workspace`, `View & Print`, `Edit`, `Complete workflow`, `Reconcile`, and supplier actions. Desktop tables remain visible at large widths.

### Procurement approval buttons

The roles configuration already contained `procurement.approve`, but no status route or decision controls existed. The following controls were added to the request workspace:

- Approve request
- Return request
- Reject request
- Submit for approval, for draft or returned requests

Implementation:

- Route: `PATCH /procurement/{procurementRequest}/status`, named `procurement.status`
- Controller: `HomeController::updateProcurementStatus`
- UI: `vibe-app/resources/views/procurement-show.blade.php`
- Regression test: `vibe-app/tests/Feature/ProcurementRequestWorkspaceTest.php`

Allowed transitions currently are:

- `draft` or `returned` → `pending_approval`
- `submitted` or `pending_approval` → `approved`, `returned`, or `rejected`

Permission checks are enforced in the controller:

- Approval decisions require `procurement.approve`.
- Submission or resubmission requires `procurement.edit` or `procurement.create`.

## Verification completed

- After the responsive and school-filter work: `76 tests passed, 499 assertions`.
- After adding approval controls: targeted `ProcurementRequestWorkspaceTest` passed with `2 tests, 22 assertions`.
- The full suite was not rerun after the final approval-control change to conserve usage.

## Items to review next

1. Ask the user which exact buttons are still missing after refreshing and opening a submitted Procurement request.
2. Confirm whether additional workflow transitions are required, such as `Start canvass`, `Mark delivered`, or `Mark complete`.
3. Consider requiring a reason when returning or rejecting a request; no remarks field is currently collected by the new buttons.
4. Review master-admin supplier creation: the existing add-supplier form does not assign a school, so a newly created agency-wide supplier may not appear under a selected school filter.
5. Run focused tests first. Run the full suite only before final integration or if the changes affect shared behavior.

## User preference

The user explicitly requested low usage and asked not to use skills for the latest work. Prefer direct inspection, targeted edits, and narrow tests. Avoid loading skills, large outputs, full browser audits, or the full test suite unless necessary.
