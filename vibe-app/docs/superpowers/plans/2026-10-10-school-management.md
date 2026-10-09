# School Management Implementation Plan

> Executed inline (Native). Spec: `docs/superpowers/specs/2026-10-10-school-management-design.md`.

**Goal:** Master-only School Management page: list schools, set a school / its user / its employees inactive (and reactivate) with a recorded reason. Nothing is deleted.

**Architecture:** `SchoolManagementService` holds the rules; `SchoolManagementController` validates and delegates; `EnsureAccountActive` middleware signs out users who became inactive; two Blade views; one migration.

**Tech:** Laravel 13, SQLite, Blade/Tailwind, PHPUnit.

## Tasks (each: failing test first, implement, run, commit)

1. **Data + service + tests** — migration `add_deactivation_fields` (`school_staff.end_reason/end_note`, `users.deactivated_at/deactivation_reason/deactivation_note`); `SchoolManagementService` (`setSchoolActive`, `deactivateUser`, `reactivateUser`, `deactivateEmployee`, `reactivateEmployee`); audit rows; rules from the spec.
2. **Middleware** — `EnsureAccountActive` appended to the `web` group: signs out a non-master user whose status is `inactive` or whose school is `inactive`.
3. **Controller, routes, views, sidebar item** — `/school-management` (list with search + status filter), `/school-management/{school}` (status, user, employees, inactive group), POST actions; sidebar item before School Settings (master only).
4. **Docs + full run** — `docs/specs.md` section 10; full suite; browser check; commit.

## Deviations from the spec
None planned. Employees ended by a station transfer also appear in the Inactive group (reason shown as "Transferred").
