# Vacant School Takeover (new staff) — Design

Date: 2026-10-10 · Status: draft for review · Part 2 of 2 (Part 1: `2026-10-10-transfer-handover-design.md`)

## Purpose

A school left vacant stays vacant, with all its data, until a **new staff member registers in the system and asks to take it over**. The master reviews the request and, on approval, gives the new person the school and its data. The new person holds their own personal subscription.

## Decisions (agreed with the user)

| Topic | Decision |
|---|---|
| Who | A new person who is not yet in the system. |
| How | On the registration page they choose "Take over a vacant school" instead of registering a new school. |
| Until approval | The school stays vacant and the person cannot sign in or see any school data. |
| Approval | Master only. On approval the person becomes the school's user, with the school's data. |
| Subscription | Personal: a 30-day trial is created for the person on approval. |

## Data

New table `school_takeover_requests`: `user_id`, `school_id`, `status` (`pending` | `approved` | `declined`), `decided_by`, `decided_at`, `decision_note`, timestamps.

The person's user row is created at registration with `status = pending`, `organization_id`/`school_id` empty (so the existing sign-in check and the tenancy scope keep them out of every school). Registration fields are the same as pre-registration (given name, middle initial, surname, username, position, email, phone, password, final-name confirmation).

## Flow

1. **Register.** The registration page offers two choices: *Register a new school* (today) or *Take over a vacant school* (a dropdown of active, vacant schools with no pending takeover request). Submitting the second creates the pending user and a `pending` takeover request. No school or organization is created.
2. **Review (master).** The request appears as a **Takeover request** card on that school's page (School Management) and as a badge in the list, next to the transfer card, with the person's details and Approve / Decline.
3. **Approve (one DB transaction).** Re-check the school is still active and vacant and the person is still pending. Set the user's `organization_id` and `school_id`, `status = active`, role `school_admin`; create the person's `SchoolStaff` record (no roles); create a 30-day trial `Subscription` owned by the person; mark the request approved; audit row on the school. The school is no longer vacant and its data is theirs.
4. **Decline.** The request is declined (note kept); the pending user stays unable to sign in.
5. **Sign-in.** A pending user who tries to sign in gets "Your registration is waiting for approval" (today they only see a generic failure).

## Rules

- One pending takeover request per school and per person.
- The chosen school must be active and have no active user, at registration and again at approval; otherwise approval is refused with a clear message.
- Only the master can approve or decline.
- The person's name and username are final, as in pre-registration.

## Testing

Feature tests: registration with a vacant school creates a pending user and request, no school/organization; non-vacant/inactive/already-claimed schools are not offered or accepted; pending user cannot sign in and sees the waiting message; approval gives the person the school, staff record, role and trial subscription and the school's existing data becomes visible; approval refused if the school got a user meanwhile; decline keeps the person out; master-only; audit row.

## Out of scope

Email notifications, letting a school choose its successor, taking over a school that still has an active user (that is a transfer with handover, Part 1).
