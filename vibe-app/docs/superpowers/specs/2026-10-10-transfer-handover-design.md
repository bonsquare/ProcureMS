# Transfer Handover (review by the other school) — Design

Date: 2026-10-10 · Status: draft for review · Part 1 of 2 (Part 2: `2026-10-10-vacant-school-takeover-design.md`)

Builds on: `2026-10-09-station-transfer-design.md` (station transfer) and `2026-10-10-school-management-design.md`.

## Purpose

When a user asks to transfer to a school that **already has an active user**, that school's user reviews the request in the system (Accept / Decline). After the master approves and the arriving user confirms, both users have access to the destination school for a **5-day handover**, counted **from the day the destination user accepted**. When the handover ends, the previous user is automatically set inactive and signed out. A vacant destination skips the review (nobody to ask) and has no handover.

## Decisions (agreed with the user)

| Topic | Decision |
|---|---|
| Who reviews | The active user of the destination school, in the system. |
| Clock | 5 days from the moment the destination user **accepts**. |
| Both users | Both have access to the destination school until the handover ends. |
| End of handover | The previous (destination) user is set inactive, reason "Transferred", and signed out. |
| Arriving user's old school | Becomes vacant at confirmation (no change from today). |
| Vacant destination | No review, no handover; master approves directly (today's behavior). |
| No answer from the destination | The destination has **5 days from the request** to accept or decline. After that the request **expires automatically** (status `expired`, shown as "No answer from <school> in 5 days"). The user may send a new request. The master cannot approve a request that is not accepted. |
| Approval after the clock ran out | The previous user is set inactive immediately at approval (no overlap). |

## Rules this changes

The "destination must have no active user" rule becomes: the destination must have **no active user, or exactly one active user who has accepted this request**. A school may have two active users only while a handover is running.

## Data

`station_transfer_requests` gets:

- `review_status` (`not_required` | `pending` | `accepted` | `declined`, default `not_required`)
- `reviewer_user_id` (the destination's active user when the request was made), `reviewed_at`, `review_note`
- `review_expires_at` (= `requested_at` + 5 days, set when a review is required); `expired_at`
- `handover_ends_at` (= `reviewed_at` + 5 days, set on accept), `handover_user_id` (= reviewer), `handover_ended_at` (set when the previous user was set inactive)

## Flow

1. **Request** (`StationTransferService::request`). Vacant destination → `review_status = not_required`. Destination with one active user → `pending`, `reviewer_user_id` set. A new request is allowed once the earlier one expired. A destination with another pending or running incoming transfer is refused ("another transfer into this school is in progress").
2. **Review.** The reviewer has 5 days. After `review_expires_at` the request becomes `expired` (closed, no one is changed) by the daily command and also lazily whenever a request is listed or acted on, so an expired request can never be accepted or approved. The reviewer sees an **Incoming transfer request** card in School Settings → Station Transfer (requester, old school, reason, date) with Accept / Decline and a note. Accept sets `accepted`, `reviewed_at`, `handover_ends_at`. Decline sets the request `declined` (note kept) and closes it.
3. **Master.** The request cards (school page and Transfer requests) show the review status. **Approve is blocked** while the review is `pending` or `declined`. Approving moves the arriving user as today; the previous user stays active. If `handover_ends_at` has passed, the previous user is set inactive in the same transaction.
4. **Confirmation** is unchanged (the arriving user confirms the new station).
5. **Handover period.** The school page and both users' Station Transfer tab show "Handover until <date>". The master can **End handover now**.
6. **End.** `StationTransferService::endHandover` sets the previous user inactive (reason Transferred, note "Handover ended"), writes an audit row (no actor) and stamps `handover_ended_at`. It runs from a daily scheduled command (`transfers:end-handovers`) and also lazily in `EnsureAccountActive` when the previous user makes a request after the deadline, so access never outlives the date.

## Screens

- Station Transfer tab: "Incoming transfer request" card for the reviewer; "Handover until <date>" notice for both users while it runs; the requester's own request card shows "Waiting for <school> to accept" / "Accepted" / "Declined by <school>".
- School page (master): review status chip on the request card, handover banner with End handover now.
- Transfer requests queue: review status next to each item; Approve disabled until accepted.

## Errors and rules

- Only the destination's reviewer can accept/decline; a declined or cancelled request cannot be accepted.
- Approval is refused if the review is not `accepted`/`not_required`, or if the destination now has an active user other than the reviewer.
- If the reviewer became inactive before approval, the school is treated as vacant and the master can approve.
- A pending review expires after 5 days; accepting or approving an expired request is refused with a clear message.
- Ending a handover twice does nothing.

## Testing

Feature tests: a pending review expires after 5 days (daily command and lazy path), the request can no longer be accepted or approved, and the user can send a new one; request to an occupied school sets a pending review and refuses a second incoming request; only the reviewer can accept/decline; accept sets a 5-day clock; decline closes the request; approve is blocked until accepted; approve leaves both users active; end-of-handover sets the previous user inactive and signs them out (scheduled command and lazy path); approval after expiry ends the handover immediately; vacant destination unchanged; reviewer deactivated → approvable; master "End handover now"; audit rows.

## Out of scope

Reminders by email before a request expires, extending the 5 days, an arriving user keeping access to their old school, more than one incoming user at a time.
