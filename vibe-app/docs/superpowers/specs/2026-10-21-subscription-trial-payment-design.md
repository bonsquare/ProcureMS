# Subscription, trial and payment system: design

Date: 2026-10-21. Owner's decisions come from the chat of 2026-10-21. Status: waiting for the owner to review this written spec.

## Goal

Turn the Subscriptions page into a working system: one paid plan (Annual, PHP 1,000.00 a year for each school) with promo codes and discounts, a **Free** plan (the full system, with an end date) that the Super admin gives to a school, a 3-day trial that cannot print, a payment flow where a school uploads its proof of payment to **its own Google Drive** (nothing is stored on Railway), and a master dashboard that shows the subscription income and how it is divided. Add a live date and time clock and one notification bell that works on every page.

## Rules

### Plan and lifecycle
- One plan: **Annual, PHP 1,000.00**, billing cycle `annual`, 365 days. The amount is set by the server and never taken from the browser.
- States: `trial` (3 days) → `active` (365 days) → `grace_period` (7 days after the end, full access) → `expired` (read-only). `free` (the full system until the end date the master sets; given and removed by the master only) and `suspended` (read-only, by the master). A renewal adds 365 days to the end date, or to today when it already ended.
- A school gets **one trial only**: refused when the school (or its DepEd School ID, when typed) already had one.
- Trials that exist today keep their own end date; the 3 days apply to new trials. The print block applies to every account in trial.
- A daily command (`subscriptions:sync`, 00:05 Manila time) moves trial or active to the next state when its date has passed. The write guard (`EnsureSubscriptionAllowsWrites`) still decides read-only by the status and the end date, so a missed run never gives free access.
- The master and the sub-master (users who see all schools) are never limited.

### The Free plan (given by the Super admin)
- **Only the Super admin (master user) gives it**, to one school at a time, from the Subscriptions page, with a reason and the user's own password; a school cannot choose or request it.
- **It has the full system**: every facility works, printing, Record Payment, Google Drive and the rest, exactly like an Annual subscription.
- **It always has an expiration date, and the master sets it** (the date is required; there is no Free without an end). The master can change the date, extend it, or take the Free plan back at any moment (the school then returns to the state its Annual subscription has, or to read-only when it has none). At the end date the school becomes read-only like an expired subscription; a notice warns it in the 7 days before, and it can become Annual by paying.
- Every grant, change, extension and removal is written to the audit log and to the History of the row.

### What each school pays (discount and promo codes)
- **The master charges each school**: on a school the master sets **how much it pays** (a price in pesos, from 1.00 to 1,000.00, with an optional end date), with a reason and password. The difference from the list price of 1,000.00 is its discount.
- The school's **Subscription page and its payment show it**: the list price (1,000.00), the **discount** the school has, and the **amount to pay**, so the school sees it is discounted and exactly how much to transfer. The proof of payment must be for that amount.
- **Promo codes** (created by the master in a Promo Codes list): a code, a percentage or a fixed amount, valid from and to, a maximum number of uses, and active or not. The school types the code on its Subscription page; the server checks it (active, in date, not used up) and shows the new price before the school pays.
- **No stacking**: the price the master set for a school always wins; a promo code applies only to a school that has no price set by the master.
- The price is **fixed when the proof of payment is submitted** (list price, discount, promo and amount to pay are saved on the proof), so a later change does not alter a payment already made. The use of a promo is counted when the master approves the payment, once.
- The income in the dashboard counts the **amounts really paid**, not the list price.
- Promo code tries are rate limited.

### What a trial cannot do
- **Print anything.** Every printed official document (ORS, DV, PR and all procurement documents, SIP, AIP, SOB, APP, APP-CSE, POW, delivery reconciliation) and every cash report. The request shows the **trial prompt** page: printing needs the paid subscription (PHP 1,000.00 a year) and how to pay. The live previews inside the forms stay.
- Record a payment (Cash), upload general files to Google Drive, download a backup. Uploading a **proof of payment** is allowed (it is the way out of the trial).
- Everything else works for the 3 days.

### Payment system
- **Payment Settings** (master only, on the Subscriptions page): bank name, account name, account number, the QR code image (PNG or JPG up to 300 KB, kept in `system_settings` as a data URI), and the **Google email** the proofs are shared with. The school sees these on its Subscription page.
- **School side** (`/subscription`, the school's system user): the status and days left, the plan and the amount, the account details and the QR, and the form "Upload proof of payment": reference number, date paid, and the image or PDF (up to 5 MB). The amount is fixed at 1,000.00. Google Drive must be connected first (the page offers Connect Google Drive). The app uploads the file to a folder of the school's Drive, then **shares it read-only with the master's Google email** (never public), and stores only the pointer (file id, view link, name, size, SHA-256).
- **Master side**: Pending proofs on the Subscriptions page with View proof (opens the Drive link), **Approve** (records the payment of 1,000.00, activates or renews the school, writes the reference and who approved) and **Reject** (with a reason the school sees). One decision per proof; a decided proof cannot be decided again.
- A school may have one pending proof at a time and at most five submissions a day.

### The Subscriptions page (master and sub-master)
Every button works: **Grant Free** and **Remove Free**, **Set Price** (what the school pays), **Promo Codes** (create, edit, switch off), **Create Subscription** (pick the school: Trial or Annual; Annual records the payment), **View** (window: plan, dates, payments, proofs, history), **Activate / Renew** (records a payment), **Extend** (extra days for a trial or grace period, with a reason), **Suspend**, **Reactivate**, and the proofs above. Each action asks for a **reason and the user's own password**, is written to the audit log and appears in the History icon of the row (the same pattern as section 72 of docs/specs.md).

### Security layers
1. All limits are enforced on the server (middleware and controllers), never only by hiding a button.
2. Reason + own password + audit log + History for every master action.
3. The proof is shared only with the master's Google email, never public; the file type is checked by its content (JPEG, PNG, WEBP or PDF) and its size.
4. The same file (same SHA-256) cannot be used as proof by two schools or twice by one school.
5. The decision of a proof locks the row and only moves from pending to approved or rejected once, so a double press or two masters cannot pay twice.
6. Rate limit on the proof upload; one trial per school.
7. The subscription pages follow school scope: a school sees only its own proofs and payments.

### Clock and notifications
- **Clock**: date and time in Philippine time in the header, before the bell and the profile icon, ticking every second and synchronised with the server time when the page loads.
- **Notifications**: one bell and one dropdown on every app page (replaces the bell that exists on 8 pages only), with a count on the bell, fed by `GET /notifications` for the user's role: everyone gets the subscription notice (trial days left, ending soon, expired); the master and sub-master get the schools waiting for activation, new registrations and proofs to review; school users get returned ORS, DVs waiting for payment and ORS with missing papers (according to their permissions). Each item links to its page.

### Dashboard (master and sub-master)
A **Subscription Income** section with: Users registered, Requests for activation (schools waiting in Schools for Activation), and the **Gross subscription income** (the sum of the recorded payments, for all years or one chosen year) with its division of the 100%: **30% System Subscription**, **20% Allowance, Maintenance and Operating Expense**, **50% Net Income**, each in pesos and as a bar.

## Data
- `subscription_payments`: school, subscription, amount (paid), list_price, discount_amount, promo_code_id (nullable), reference, paid_at, period start and end, method, recorded_by, proof_id (nullable).
- `promo_codes`: code (unique), type (percent or fixed), value, starts_at, ends_at, max_uses, used_count, active, created_by.
- The price the master sets for a school: `charge_amount`, `charge_ends_at` on `subscriptions`; the Free plan's end date is `subscription_end` of the `free` subscription.
- `payment_proofs`: school, user, reference, paid_at, list_price, discount_amount, promo_code_id, amount_to_pay, Drive file id, view link, name, mime, size, sha256, status (pending, approved, rejected), reviewed_by, reviewed_at, reject_reason.
- `system_settings` keys for the account details, the QR and the master's Google email.
- No change to the existing `subscriptions` columns except the new plan value `annual` and the status `suspended` / `expired` already understood by `allowsTransactions()`.

## Out of scope
Online payment gateways, refunds, proration, e-mail or SMS (Phase 11 is not needed), any change to the layout of the official documents.

## Testing
A feature test for each rule (the Free plan given only by the master, with a required end date, the full system while it lasts and read-only after it; the price set by the master wins over a promo, the school sees list price, discount and amount to pay, the proof must match the amount, price fixed at submission, promo use counted once at approval, expired and used-up codes refused): trial print block and prompt, 3-day trial and once-only, the daily sync, the write guard for each state, proof upload (type, size, duplicate, daily limit, Drive not connected, sharing call), approve and reject (once only, payment and renewal), every master action with reason and password, school scope, the notification feed per role, the dashboard figures and the 30/20/50 division, and that the printed documents are unchanged.
