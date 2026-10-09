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
- Still open before Phase 3 is complete: browser check of the PR form picker, PR numbering/status end-to-end validation, and deciding whether the APP link should be required for PRs.
- The Planning page is `/planning`. The source is `resources/views/planning.blade.php`; the controller is `app/Http/Controllers/PlanningController.php`.
- The last GitHub backup is commit `69d1d35` on `master`. Do not reset or discard local changes.

## Specifications

Standing specifications (official documents, procurement workflow and document order, supplier directory, dashboards, School Settings, pre-registration, screen design standards, local development) are in `docs/specs.md`. Follow them for every existing and future module. Official documents use the shared print engine with its paper-size toolbar (Close, Paper Size, Orientation, Zoom, Print / Save as PDF); see docs/specs.md sections 1.5 and 1.6.

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
