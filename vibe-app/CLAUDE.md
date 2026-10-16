<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

---

# ProcureMS project handoff for Claude

This is a Laravel 13 / PHP 8.4 school procurement, budget, accounting, cash, and liquidation system. Continue implementation from the existing repository and follow the product specification and phase order below.

## Non-negotiable product rules

1. Preserve existing relationships between all modules. Do not replace or break existing tables, routes, models, or workflows merely to introduce a new feature.
2. Every record that belongs to a school or organization must remain tenant-isolated. A user must never read or mutate another organization’s data.
3. Prefer additive migrations, nullable foreign keys where legacy data requires them, existing models/services, and named routes. Do not rename or drop existing columns without an explicit migration and a safe data migration.
4. Keep the master transaction record as the cross-module audit spine. New planning, budget, procurement, delivery, accounting, cash, and liquidation records should link to it when applicable.
5. Use organization ID consistently for organization-owned data and school ID consistently for school-owned data. Validate both ownership and permission before writes.
6. Do not seed or invent production data. Test fixtures and the Lubas supplier seed data must remain clearly separated from production setup.
7. Before declaring a phase complete, verify the happy path, permission failures, organization isolation, migration behavior, and the relevant reports/documents.

## Current implementation status

- Phase 1 foundation is substantially implemented: authentication, organizations/schools, roles and permissions, subscription/trial foundation, organization isolation, fiscal years, fund sources, and chart of accounts.
- Phase 2 is implemented but still needs end-to-end validation: SIP, AIP linking, budget integration, fiscal-year controls, fund sources, quarterly/budget-control coverage, and master transactions.
- Parts of Phase 3 are already present: PPMP, APP generation/approval, and planning transaction links. Treat these as in-progress, not complete.
- 2026-10-08: SIP → AIP → PPMP → APP flow validated by `tests/Feature/PlanningFlowTest.php` (happy path, closed fiscal year, permissions, organization isolation). No defects found.
- 2026-10-08: PR lines can now link to approved APP items (`procurement_request_items.app_item_id`, optional/nullable). `App\Services\AppItemLinkService` enforces approved APP, same school, and remaining APP quantity/cost; `returned`/`rejected` PRs release quantity. The PR form has an "Approved APP item" picker, `/planning` shows requested quantity and PR numbers per APP item, and a `pr_linked` event is logged on the planning transaction. Covered by `tests/Feature/PrAppLinkTest.php`.
- 2026-10-08: SIP now follows the official School Improvement Plan template (PDF supplied by the user): each `sip_projects` row is a Specific Program/Project (pillar, KRA, organizational outcome, strategy, 5-point agenda) with `sip_activities` carrying Year 1-3 physical/financial targets, source of fund, responsible person, remarks; `sip_plans` holds print signatories. Official print: `/planning/sip/print?school_id=&start_year=` (`resources/views/sip-print.blade.php`). Covered by `tests/Feature/SipTemplateTest.php`.
- 2026-10-08: All official printed documents share one print engine (`resources/views/partials/print-clean.blade.php` + `partials/official-toolbar.blade.php`). A document opts in with `data-official-page` (+ `data-paper`, `data-orientation`, `data-margin`); the screen shows the paper-size toolbar (Close, Paper Size, Orientation, Zoom, Print / Save as PDF) and each form's normal paper is the default `@page` (restored 2026-10-09). New official documents must use it and must not declare their own `@page`. Header logos come from `App\Support\OfficialDocument::logos()` (school profile, then agency/division; no school-specific fallback; a missing logo prints blank). Covered by `tests/Feature/OfficialPrintTest.php`.
- 2026-10-10: Shared department/division logos (master replaces), per-user Google Drive repository (/google-drive, /drive-files, uploads blocked until connected) and master database backup (/backup, daily 12:00 midnight Manila) are built; see docs/specs.md section 17. The Google Cloud OAuth client and the three GOOGLE_* Railway variables still have to be set by the owner before Drive works on the live site.
- 2026-10-10: Sub-master role built (checklist access, delete guard, managed in the Master User tab); see docs/specs.md section 18.
- 2026-10-10: User Management Users tab is real (Add User, edit, reset password, set inactive, audit log page; docs/specs.md section 19); School Settings users are view only except one's own account; School Settings Official Station tab is now "Make a request" with Official Station and Other types (section 20); Official Station Management, School Management in all menus and the civic sidebar look are in.
- 2026-10-11: Pre-registration confirm dialog fixed and Privacy Policy acceptance recorded with date/time (section 6); sign-in takes email or username (5.2); employee dialogs add roles in place and have Active/Inactive (5.3); RFQ blank-page print fix (1.7); add a supplier from the Abstract and come back, preview retries, Document Center one row per request, NTP delivery days typed by hand (2.4, 2.5).
- 2026-10-11: SIP can be imported from the school's own Excel file (Planning -> SIP -> Import from Excel): upload, editable preview, confirm; `SipWorkbookReader`, `SipImportService` (shared with `sip:import`), `SipImportController`. Design: docs/superpowers/specs/2026-10-11-sip-excel-import-design.md.
- 2026-10-11: The PPMP is replaced by the per-quarter School Operating Budget (SOB): Planning -> SOB panel (`/planning/sob/{id}`, print `/planning/sob/{id}/print`), `SobService`, tables `sob_plans`/`sob_items`; approving a quarter creates/updates Budget allotments; the APP is generated from approved SOBs (`app_items.sob_item_id`); old PPMP rows stay in the database. Design: docs/superpowers/specs/2026-10-11-sob-design.md. Approving an AIP no longer creates allotments.
- 2026-10-11: Every dropdown is now a type-to-search combo through one shared script (`resources/views/partials/searchable-select.blade.php`, included from `partials/input-fixes`); the real `<select>` stays underneath as the source of truth; opt out with `data-native` (the print-preview toolbar is excluded). Design: docs/superpowers/specs/2026-10-11-searchable-select-design.md.
- 2026-10-11: Every generating form shows who signs each signature slot and lets the user pick someone else for that one document: the 10 procurement documents, the PR, the ORS and the DV. `App\Support\SignatorySlots` is the catalog (slots, defaults, validation, overlay); choices are saved as snapshots (staff id, name, position) in `procurement_documents.metadata['signatories']`, `procurement_requests.signatories` and `liquidation_reports.signatories` (`ors`/`dv` keys); the picker is `partials/signatory-picker`. Templates are unchanged. Design: docs/superpowers/specs/2026-10-11-document-signatories-design.md.
- 2026-10-11: The APP-CSE (Annual Procurement Plan for Common Supplies and Equipment, Procurement Service form) is built: the 2026 PS catalog (368 items, `cse_catalog_items`, loaded by migration from `database/data/cse-catalog-2026.json`; a later year with `php artisan cse:import-catalog`), a plan per school and fiscal year with 12 monthly quantities per item (`app_cse_plans`, `app_cse_items`, `AppCseService`), entry inside the APP tab of Planning (`/planning/app-cse/{id}`), and the official print (`/planning/app-cse/{id}/print`, landscape long bond, every catalog row, no logos by the owner's decision). Design: docs/superpowers/specs/2026-10-11-app-cse-design.md.
- 2026-10-14: Server Dashboard tab (Railway hosting, billing and domain status); SOB items go into the APP on approval and the APP-CSE takes every category; SOB, APP and APP-CSE page layouts; SIP and AIP print with text flow and merged cells (docs/specs.md sections 21 to 28).
- 2026-10-15: Purchase Request from the SOB activity and obligation by the Purchase Order (31); ORS date from the PO and the final printed ORS (32); Accounting and Cash by the school's permission, the printed DV, journal entry and "Less" deductions (33); Allotment Registry from SOB items; APP-CSE categories fold and open (34); planning panels (29, 30).
- 2026-10-16: Cloudflare Turnstile on sign-in and registration, off until the keys are set (35); redesigned sign-in page (36).
- 2026-10-16: **Cancelled checks** (37): Cash > Paid > Cancel Check (reason and own password) records the check in the Cancelled Checks register and sends the ORS back to For Review without its DV; Edit ORS (Accounting), then Ready for DV (same DV number), then Edit DV while unpaid (reason and own password); the Allotment Registry follows by itself. Tables `cancelled_checks`, column `liquidation_reports.reissue_pending`.
- 2026-10-16: Fund Cluster on the DV (`dv_fund_cluster`, "General Fund") and on the ORS (`ors_fund_cluster`, 101101), no Source of Fund field in Create ORS, Create ORS form in parts, an ORS that Accounting returned is edited on the Budget page with the official ORS beside the form (30.2, 30.4, 32.2); printed DV and ORS refinements (33.2); Edit DV and Edit ORS windows look like the create windows; DV rows have no journal list and no Signatories button; row badges for ORS, PR and PO; `LiquidationReport::dvLabel()` so "DV-" is never doubled (33.5, 37).
- 2026-10-16: SOB categories are Common Office Supplies, Drugs and Medicines, Construction Components and Supplies, Paper Materials and Products, Common ICT Equipment, Consumables, Other Categories, plus "-" (a particulars that is not goods is kept out of the APP and the APP-CSE); the old names were renamed by a migration (docs/specs.md, SOB section).
- 2026-10-16: The Pillar column heading of the printed SIP and AIP reads "Pillar (Access, Equity, Quality, Resiliency and Well-Being) / Enabling Mechanism"; "Enabling Mechanism" is no longer underlined in the SIP (docs/specs.md 22.1, 23.1).
- Live: Railway deployment 1184a0a7 (2026-10-16 13:34), migrations through `rename_app_categories_and_add_consumables`. All 841 tests pass.
- Still open before Phase 3 is complete: browser check of the PR form picker, PR numbering/status end-to-end validation, and deciding whether the APP link should be required for PRs.
- The Planning page is `/planning`. The source is `resources/views/planning.blade.php`; the controller is `app/Http/Controllers/PlanningController.php`.
- The last GitHub backup is commit `69d1d35` on `master`. Do not reset or discard local changes.

## Standing workflow (owner's instruction 2026-10-16)

When the owner says **commit**, **push** or **deploy** (alone or together), do the whole chain without asking again: update `docs/specs.md` and the status list of this file for the change, commit, push to GitHub, and deploy with `railway up --detach --service procurems` from `vibe-app` (set `MSYS_NO_PATHCONV=1` in Git Bash), then verify with `railway deployment list --service procurems` and give the owner a per-step checklist. A push alone does not deploy; deploy only when the owner says deploy.

## Specifications

**`docs/specs.md` is a protected document: never edit it without the owner's explicit permission for that exact change (see the notice at the top of the file). A yes to a feature is not permission to edit it; if code and specification disagree, stop and ask.** Standing specifications (official documents, procurement workflow and document order, supplier directory, dashboards, School Settings, pre-registration, screen design standards, local development) are in `docs/specs.md`. Follow them for every existing and future module. Official documents use the shared print engine with its paper-size toolbar (Close, Paper Size, Orientation, Zoom, Print / Save as PDF); see docs/specs.md sections 1.5 and 1.6.

## Required phase order

### Phase 1 — Foundation

Complete and verify authentication, organizations, multi-tenancy, subscriptions, users, roles, organization profile, fiscal year, fund sources, chart of accounts, and organization settings.

### Phase 2 — Planning and Budget

Complete and verify SIP, AIP, SIP-to-AIP linking, budget allocations, quarterly allocations, fund-source mapping, budget control, fiscal-year open/close, document numbering, and audit history.

### Phase 3 — Procurement Planning

Complete PPMP, PPMP items/specifications, PPMP approval, APP generation and approval, PR creation/numbering/status, and links among SIP, AIP, APP, budget, and PR.

### Phase 4 — Procurement

Implement RFQ, supplier master data, quotations, quotation comparison, abstract, BAC evaluation/resolution, NOA, PO, NTP, and the complete procurement status workflow.

### Phase 5 — Delivery

Implement delivery records, partial/complete delivery, inspection, acceptance, accepted/rejected quantities, Inspection and Acceptance Report, and links to PO and supplier.

### Phase 6 — Accounting

Implement obligation, DV, journal entries, general ledger, subsidiary ledgers, trial balance, accounting approvals, and links from accounting records back to the originating transaction.

### Phase 7 — Cash

Implement payment processing, check/cash records, cash disbursement and receipt registers, payment status, DV links, and cash balance monitoring.

### Phase 8 — Liquidation

Implement liquidation records, supporting-document checklist, validation, deficient/returned documents, approval workflow, liquidation register, and complete transaction links.

### Phase 9 — Documents

Implement official templates for SIP, AIP, PPMP, APP, PR, RFQ, PO, DV, and liquidation; printable forms, PDF generation, document numbering, template versioning, and document archive.

### Phase 10 — Reporting

Implement SIP/AIP accomplishment, procurement, budget utilization, PPMP/APP, accounting, cash, liquidation, supplier, and school/organization dashboard reports.

### Phase 11 — Advanced

Implement email/SMS notifications, analytics, support access, configurable approval workflows, advanced audit logs, custom organization settings, and performance monitoring.

## Next work sequence

1. Run the application and inspect the current routes/database state.
2. Validate the full Phase 2 and partial Phase 3 flow: SIP → AIP → PPMP → APP → PR.
3. Fix only confirmed defects; keep changes additive and tenant-safe.
4. Add focused feature tests for each corrected workflow and permission/isolation failure.
5. Finish Phase 3 before starting Phase 4 supplier/RFQ work.
6. Update this handoff and the phase checklist after every meaningful milestone.

## Completion standard

A phase is complete only when its database schema, UI, controller/service logic, permissions, organization isolation, transaction links, validation, tests, and user-facing output are all covered. Do not mark a phase complete because only its page or migration exists.

---

# Strict minimal change policy

**Preserve everything that is already working.** The existing design, functionality, layout, structure, and user experience are approved unless the owner explicitly says otherwise. Never redesign, restructure, refactor, or modify unrelated components without explicit permission.

## Minimal change principle

1. Understand the exact problem.
2. Identify the specific component responsible.
3. Inspect the relevant code.
4. Determine the smallest possible correction.
5. Modify only what is necessary.
6. Preserve all unrelated code and functionality.
7. Verify that existing features remain unaffected.

One small request must result in one small, targeted change whenever technically possible.

## Do not change unless explicitly requested

- Design: page layouts, colors and themes, fonts, buttons and icons, spacing outside the requested area, navigation and menus, forms and tables, dashboard layouts, responsive behavior, animations.
- Functionality: business logic, database structures, API endpoints, authentication and authorization, user roles and permissions, existing workflows, reports and document templates, validations and calculations.
- Unsolicited work: code cleanup, component restructuring, file renaming, dependency upgrades, framework migrations, architecture changes, UI improvements, performance optimizations. If a better implementation exists, ask permission first.

A bug fix is not permission to redesign the interface.

## Scope control

Before modifying code, state: the requested change, the affected files, the protected components, and the expected result. If the change requires touching unrelated functionality, explain why and get approval first.

## Implementation rules

- Prefer minimal code changes; preserve existing naming conventions; reuse existing components and styles.
- Avoid replacing entire files; do not modify unrelated files; no new dependencies without approval.
- Never use a redesign as a shortcut to fixing a bug.
- Do not activate unrelated skills or workflows; use specialized skills only when genuinely required.

## Verification

After the change: verify the reported issue is resolved, check related functionality, run relevant existing tests, review the Git diff for unintended modifications, revert unintended changes made by the current task (without overwriting pre-existing user changes), and report any verification that could not be completed.

## Final report

Problem · Fix Applied · Files Modified · Verification · Unchanged (relevant existing features preserved).

**Do not improve, redesign, or refactor what was not asked. Fix only what is explicitly requested. When uncertain, ask before making changes.**
