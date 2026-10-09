# Station Transfer — Design

Date: 2026-10-09 · Status: draft for review

## Purpose

System users are sometimes reassigned to another Official Station (school). They keep one account (same username, user code, password and subscription) and ask the master user to move them. After approval they work in the destination school's data. The school they leave keeps its own data. Nothing is copied or moved between schools.

## Decisions (agreed with the user)

| Topic | Decision |
|---|---|
| Role at new school | Kept as is (a school admin stays a school admin). |
| Destination | A registered active school, or a school not yet registered; the master creates it on approval. |
| Old school left without an admin | Transfer allowed; the master sees a warning. |
| Employee record (`SchoolStaff`) | Old record stays at the old school as inactive history; a new record is created at the new school with no roles, set again there. |
| When it takes effect | Immediately on approval, but the user must log in/continue and confirm the new station before seeing any data. |
| Who decides | Master user only. |

## How tenancy already works (why no data is moved)

Records are scoped by the global scope in `BelongsToOrganization`, using the logged-in user's `organization_id`. A user's `school_id` is their Official Station. Changing those two fields on the account is enough for the user to see the new school's records and stop seeing the old school's.

## Data

New table `station_transfer_requests`:

- `user_id`, `from_school_id`, `from_organization_id`
- `to_school_id` (nullable) and `proposed_school` (JSON, same fields as pre-registration, used when the school is not registered)
- `reason`, `status` (`pending`, `approved`, `declined`, `cancelled`), `requested_at`
- `decided_by`, `decided_at`, `decision_note`, `confirmed_at`

Changes to existing tables:

- `users.station_confirmed_at` (nullable timestamp). Cleared on approval, set on confirmation.
- `school_staff`: an inactive marker with an end date (`status`/`ended_at`; use an existing status column if one exists), so the old record stays as history.

Rules: one pending request per user; the user may cancel while pending; the destination cannot be the user's current school.

## Flow

1. **Request.** User menu → "Station transfer". The user picks a registered active school, or "My school isn't listed" and enters school details, plus a reason. Only active, non-master users can request.
2. **Review.** Master dashboard, Subscription area, "Transfer requests" tab. Each row shows user, old station, new station (badge when unregistered), reason, date, and Approve/Decline. The approve dialog lists what will happen and shows the warning when the user is the old school's only active admin.
3. **Approve (one DB transaction).**
   1. If the school is not registered, create its organization and school with the same validation as pre-registration (unique school ID).
   2. Mark the user's old `SchoolStaff` record inactive with an end date.
   3. Create the new `SchoolStaff` record at the new school with no roles; copy the employee number as a starting value.
   4. Update `users.organization_id` and `users.school_id`; clear `station_confirmed_at`.
   5. Mark the request approved; write audit entries on both schools.
   Any failure rolls everything back and the user stays where they were.
4. **Decline.** Status declined with an optional note; nothing else changes.
5. **Confirm.** A middleware redirects any user with an empty `station_confirmed_at` (who has an approved transfer) to "Confirm your new station", showing old → new station and the roles kept. Confirm sets `station_confirmed_at`; the only other option is Log out. Other logged-in sessions of the same user are covered because the flag is on the account.

## Screens

- **User:** "Station transfer" page with the request form and a status card for the current or latest request (pending with Cancel; approved; declined with the master's note). Past transfers are listed.
- **Master:** "Transfer requests" tab: pending queue and decided history.
- **Confirmation page:** full-width, no module tabs, single Confirm button.

## Errors and rules

- A master user cannot request; a pending request blocks another one.
- Destination equals current school → rejected.
- Unregistered school with a school ID that exists by approval time → approval fails with a clear message, nothing changes.
- Only the master can approve or decline; a school admin cannot, even for their own school's users.
- Approval is blocked if the user's account was deactivated meanwhile.

## Testing

Feature tests (targeted, not the full suite each time):

- request, duplicate pending, cancel, decline;
- approve to a registered school; approve to a new school (school created);
- old school's records stay untouched and become invisible to the user; the new school's records become visible;
- username, user code and role unchanged; old staff record inactive; new staff record has no roles;
- admin-left-without-admin warning shown;
- confirmation middleware redirects until confirmed; rollback leaves everything unchanged when school creation fails;
- non-master cannot approve/decline.

Run these plus the existing school settings and tenancy tests.

## Out of scope

Email notifications, transfers scheduled for a future date, moving or copying records between schools, a user serving two schools at once.
