# Station Transfer — Design

Date: 2026-10-09 · Status: revised after review (personal subscriptions, one user per school)

## Purpose

A system user manages exactly one school (their Official Station) and holds a personal subscription for their liquidation and procurement work. When they are reassigned, they ask the master user to move them. They keep the same account and the same subscription; they then work in the destination school's data. The school they leave keeps its own data and becomes vacant. Nothing is copied or moved between schools.

## Business rules

- One user manages one school. Users cannot add other users (the "Add user" feature in School Settings was removed).
- The subscription belongs to the person, not the school.
- A destination school must be vacant (no active user).

## Decisions (agreed with the user)

| Topic | Decision |
|---|---|
| Role at new school | Kept as is. |
| Destination | A vacant registered active school, or a school not yet registered; the master creates it on approval. |
| School left behind | Becomes vacant; keeps all data; master sees "this school will have no user". |
| Subscription | Personal. It goes with the user. Transfer never touches it. A school created on approval gets no trial of its own. |
| Employee record (`SchoolStaff`) | Old record stays at the old school as ended history; a new record is created at the new school with no roles. |
| When it takes effect | On approval, but the user must confirm the new station before seeing any data. |
| Who decides | Master user only. |

## How tenancy already works (why no data is moved)

Records are scoped by `BelongsToOrganization` using the logged-in user's `organization_id`. A user's `school_id` is their Official Station. Changing those two fields on the account is enough for the user to see the new school's records and stop seeing the old school's.

## Data

New table `station_transfer_requests`: `user_id`, `from_school_id`, `from_organization_id`, `to_school_id` (nullable), `proposed_school` (JSON, used when the school is not registered), `reason`, `status` (`pending`, `approved`, `declined`, `cancelled`), `requested_at`, `decided_by`, `decided_at`, `decision_note`, `confirmed_at`.

Changes to existing tables:

- `subscriptions.user_id` (nullable): the owner. Backfilled to the school's user (prefer a `school_admin`). New subscriptions (pre-registration, master "add school") set it to the new user. `User::activeSubscription()` uses the user's own subscription first and falls back to the organization's latest subscription only for users who own none (legacy rows).
- `school_staff.ended_at` (nullable): marks the old employee record as history; ended rows are hidden from normal queries by a global scope.

Rules: one pending request per user; the user may cancel while pending; the destination cannot be the user's current school.

## Flow

1. **Request.** User menu → "Station transfer". The user picks a vacant registered school, or "My school isn't listed" and fills in school details, plus a reason. Only active, non-master users can request.
2. **Review.** Master user menu → "Transfer requests" (`/transfer-requests`). Each pending row shows user, old and new station (badge when unregistered), reason, date, Approve/Decline. The old school is shown as "will have no user".
3. **Approve (one DB transaction).**
   1. Re-check the user is active and the destination is vacant (or create the school, same fields as pre-registration, without a subscription).
   2. End the user's old `SchoolStaff` record; create a new one at the new school with no roles.
   3. Update `users.organization_id` and `users.school_id`. Role and subscription are untouched.
   4. Mark the request approved; write audit entries on both schools.
   Any failure rolls everything back.
4. **Decline.** Status declined with an optional note.
5. **Confirm.** A middleware redirects a user with an approved, unconfirmed transfer to "Confirm your new station". Confirm sets `confirmed_at`; the only other action is Log out.

## Errors and rules

- A master user cannot request; a pending request blocks another one.
- Destination equals the current school, is inactive, or already has an active user → rejected (at request time and again at approval time).
- Only the master can approve or decline.
- Approval is blocked if the user's account was deactivated meanwhile.

## Testing

Feature tests: request rules; approve to a vacant registered school and to a new school; school data isolation after the move; role, username and subscription unchanged; old staff ended, new staff without roles; destination taken between request and approval; double approval; inactive user; rollback after school creation; confirmation middleware; master-only decisions; subscription ownership (backfill rule, `activeSubscription()` precedence, registration sets the owner).

## Out of scope

Email notifications, scheduled transfers, moving or copying records between schools, a user serving two schools, assigning a replacement user to a vacant school (done through the master's existing tools or another transfer).
