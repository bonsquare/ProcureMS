# School Management (master user) — Design

Date: 2026-10-10 · Status: draft for review

## Purpose

The master user manages every school from one place and can take people out of service when they retire, resign or transfer. Nothing is ever deleted: people and schools are set **inactive**, with a recorded reason, and can be reactivated.

## Decisions (agreed with the user)

| Topic | Decision |
|---|---|
| Delete | Never. Only Inactive (and Reactivate). |
| Who | Master user only. |
| Reasons | Retired, Resigned, Transferred, Other (+ effective date and note). |
| School left behind | An inactive user makes the school vacant; vacant schools can receive a transferred user. |
| Subscription | Personal; stays with the user. Untouched by any action here. |
| Records | Procurement, liquidation and all other school records are never touched. |

## Rules already in the system that this builds on

- Sign-in requires `users.status = active` and the user's school `status = active` (`AuthController`).
- A school with no active user is vacant; only vacant active schools can be chosen as a transfer destination.
- `school_staff.ended_at` hides an employee from every list and role lookup (global scope `active`).
- Activating a school sets both `schools.status` and `organizations.status` (`approveSchoolRegistration`).

## Page

New sidebar item **School Management** (`/school-management`), visible to the master user only.

**List.** One row per school: name, School ID, division, its user, status (Active / Inactive / Vacant), employee count, pending transfer requests. Search by name/ID and filter by status.

**School detail** (`/school-management/{school}`), three sections:

1. **School status.** Set Active or Inactive (updates the school and its organization). Inactive: its user cannot sign in and it cannot be a transfer destination.
2. **System user.** Shows the user. "Set inactive" asks for reason, effective date and note, sets `users.status = inactive` (so the school becomes vacant). "Reactivate" is refused when the school already has another active user.
3. **Employees.** Each employee can be set inactive (reason, date, note → `ended_at`, `end_reason`, `end_note`) and reactivated (clears them). Inactive employees are listed in a separate "Inactive" group so they stay visible to the master.

## Data

- `school_staff`: add `end_reason` (string, nullable) and `end_note` (text, nullable); `ended_at` is set to the effective date.
- `users`: add `deactivated_at`, `deactivation_reason`, `deactivation_note` (all nullable); cleared on reactivation.
- Every action writes an `audit_logs` row (`action`: `school_set_inactive`, `school_reactivated`, `user_set_inactive`, `user_reactivated`, `employee_set_inactive`, `employee_reactivated`) with the reason in `metadata`.

A `SchoolManagementService` holds the rules; the controller only validates and calls it.

## Sessions

An already signed-in user who becomes inactive, or whose school becomes inactive, is signed out on their next request (a small middleware), so retiring someone takes effect immediately. The master user is never affected.

## Errors and rules

- Only the master can use these routes (403 otherwise).
- A user cannot be set inactive twice or reactivated when already active; same for employees and schools.
- The master cannot set their own account inactive; master users are not listed as school users.
- Reactivating a user is refused if the school is inactive or already has another active user.
- Reason is required; effective date defaults to today and cannot be in the future.

## Testing

Feature tests: master-only access; list filters and Vacant status; school Inactive/Active (blocks sign-in and removes it from transfer destinations); user inactive → vacant, can no longer sign in, signed out on next request, reactivation rules; employee inactive/reactivate (hidden from role lookups, shown in the Inactive group); validation (reason required, future date); audit rows; subscription and school records unchanged.

## Out of scope

Hard delete, bulk actions, emailing affected people, assigning a replacement user (done through Station Transfer or school pre-registration).
