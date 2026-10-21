# Subscription, Trial and Payment System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A working subscription system: Annual plan (PHP 1,000.00), a 3-day trial that cannot print, a Free plan with an end date, a price set by the master for each school, promo codes, proof of payment uploaded to the school's own Google Drive, a live clock, one notification bell, and a subscription-income dashboard.

**Architecture:** One `SubscriptionService` owns every state change (trial, annual, renew, free, suspend, extend, price) and one `SubscriptionPricing` service owns the amount to pay. Limits are enforced on the server by a middleware (`RestrictTrialActions`) and the existing write guard. New models: `SubscriptionPayment`, `PaymentProof`, `PromoCode`. The master pages reuse the pattern of the live block (`subscriptions.live`) and of the reason-and-password window (`partials/action-guard`).

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit, SQLite on Railway, Blade + Tailwind CDN, the existing `GoogleDriveService` (scope `drive.file`).

**Spec:** `docs/superpowers/specs/2026-10-21-subscription-trial-payment-design.md`

## Global Constraints

- Plan amount `1000.00` PHP for 365 days; the amount is set by the server, never taken from the browser.
- Trial is `3` days, one per school; grace is `7` days; Free always has an end date set by the master.
- The app runs on Asia/Manila time (spec 77); an end date is the Philippine day.
- Every master action (grant, remove, extend, suspend, price, approve, reject, promo) needs a reason and the user's own password (`current_password`), is written to `AuditLog` with the reason in `metadata`, and shows in the row's History (spec 72 pattern).
- The master and sub-master (`User::seesAllSchools()`) are never limited.
- The proof is shared only with the master's Google email, never public; files are JPEG, PNG, WEBP or PDF up to 5 MB; QR image up to 300 KB.
- Nothing about the printed official documents or the cash reports changes (specs 55, 56). Additive migrations only. Tenant isolation as in the rest of the app.
- Existing trials keep their own end date.

## Review Focus

1. A trial user who opens a print URL directly (new tab, saved link) gets the trial prompt, never the document.
2. Two masters (or a double press) approving the same proof: one payment only.
3. A proof whose amount does not equal the amount to pay, or the same file used twice (also by another school): refused.
4. A promo that ends or is used up between submitting and approving does not change a submitted proof's price.
5. A school whose Drive is not connected or needs reconnecting gets a clear way to connect, not a 500.
6. Midnight Manila: an end date of today still allows today, the next day does not.
7. A Free school at its end date becomes read-only and gets the warning in the 7 days before.

## File Structure

- `database/migrations/*_subscription_system.php`: tables `subscription_payments`, `payment_proofs`, `promo_codes`; columns `charge_amount`, `charge_ends_at` on `subscriptions`.
- `app/Models/SubscriptionPayment.php`, `PaymentProof.php`, `PromoCode.php`; `app/Models/Subscription.php` (statuses, helpers).
- `app/Services/SubscriptionPricing.php`, `SubscriptionService.php`, `SubscriptionIncomeService.php`.
- `app/Console/Commands/SyncSubscriptions.php` + `routes/console.php` schedule.
- `app/Http/Middleware/RestrictTrialActions.php`; `resources/views/trial-prompt.blade.php`.
- `app/Http/Controllers/SubscriptionAdminController.php` (master actions, promo codes, payment settings, proofs), `SubscriptionController.php` (school page and proof upload), `NotificationController.php`.
- Views: `partials/subscriptions-live.blade.php` (extended), `subscription.blade.php`, `partials/topbar-tools.blade.php`.
- Tests: one file per task under `tests/Feature/`.

---

### Task 1: Data model and pricing

**Files:** Create the migration, the three models, `app/Services/SubscriptionPricing.php`; Test `tests/Feature/SubscriptionPricingTest.php`.

**Interfaces:**
- Produces: `SubscriptionPricing::LIST_PRICE = 1000.00`; `quote(School $school, ?string $promoCode = null): array{list_price: float, discount: float, amount: float, promo: ?PromoCode, source: 'list'|'school'|'promo'}`; `PromoCode::usableAt(?CarbonInterface $at): bool`.

- [ ] **Step 1: Write the failing tests** `test_list_price_is_1000`, `test_the_price_set_for_a_school_wins_over_a_promo`, `test_a_promo_applies_only_when_the_school_has_no_price`, `test_percent_and_fixed_promos`, `test_an_ended_unused_or_inactive_promo_is_refused`, `test_the_amount_is_never_below_one_peso`, `test_a_school_price_after_its_end_date_is_ignored`.
- [ ] **Step 2: Run them; expect FAIL** (`php artisan test --compact tests/Feature/SubscriptionPricingTest.php`).
- [ ] **Step 3: Create the migration and models** (fields as in the spec's Data section; `used_count` default 0; `code` unique, stored upper case), then `SubscriptionPricing::quote` returning the cheaper only among promo candidates and the school price first.
- [ ] **Step 4: Run; expect PASS.** Run `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** `feat: subscription data model and pricing`.

### Task 2: Lifecycle service, 3-day trial, Free, daily sync

**Files:** Create `SubscriptionService.php`, `SyncSubscriptions.php`; Modify `Subscription.php` (`TRANSACTIONAL_STATUSES` gains `free`), the three places that create a trial (`HomeController` school creation, `AuthController::register`, `SchoolTakeoverService`), `routes/console.php`; Test `tests/Feature/SubscriptionLifecycleTest.php`.

**Interfaces:**
- Produces on `SubscriptionService`: `startTrial(School $school, User $owner): Subscription` (refuses a school or DepEd School ID that already had one, `ValidationException`); `activateAnnual(Subscription|School $target, PaymentProof|array $payment, User $by): SubscriptionPayment`; `renew(...)` (adds 365 days to the end date, or to today when ended); `grantFree(School $school, CarbonInterface $endsAt, User $by, string $reason): Subscription` (end date required and in the future); `changeFree(...)`, `removeFree(...)`; `suspend(...)`, `reactivate(...)`, `extend(Subscription $s, int $days, User $by, string $reason)`; `setCharge(School $school, ?float $amount, ?CarbonInterface $endsAt, User $by, string $reason)`; `sync(): array{trial: int, grace: int, expired: int}`.
- Consumes: `SubscriptionPricing`.

- [ ] **Step 1: Write failing tests**: `test_a_new_trial_lasts_three_days`, `test_a_school_gets_one_trial_only`, `test_the_existing_trial_keeps_its_end_date`, `test_free_needs_an_end_date_and_only_the_master_gives_it`, `test_free_has_the_full_system_and_becomes_read_only_after_its_date`, `test_annual_runs_365_days_then_grace_7_days_then_read_only`, `test_renewing_adds_a_year_to_the_end_or_to_today`, `test_the_end_date_day_itself_still_allows_writes`, `test_sync_moves_states_and_never_gives_free_access` (a missed run still blocks by the date), `test_every_action_writes_the_audit_log_with_the_reason`.
- [ ] **Step 2: Run; expect FAIL.**
- [ ] **Step 3: Implement the service** (each method in a DB transaction with a row lock, `AuditLog` with `reason`); `sync()` scheduled `dailyAt('00:05')->timezone('Asia/Manila')`; replace the three inline trial creations with `startTrial` (30 → 3 days).
- [ ] **Step 4: Run; expect PASS.** Run the whole suite for the registration and takeover tests that create trials.
- [ ] **Step 5: Commit** `feat: subscription lifecycle, 3-day trial, Free plan, daily sync`.

### Task 3: Trial cannot print

**Files:** Create `app/Http/Middleware/RestrictTrialActions.php`, `resources/views/trial-prompt.blade.php`; Modify `bootstrap/app.php` (alias `trial.restrict`) and the print/report routes in `routes/web.php`; Test `tests/Feature/TrialPrintBlockTest.php`.

**Interfaces:**
- Produces: middleware that, for a user whose `activeSubscription()->status === 'trial'` and who is not `seesAllSchools()`, answers every route in a named list with the trial prompt (HTTP 402 view: the plan price, how to pay, a link to `/subscription`, a Close button); `Subscription::isTrial(): bool`.

- [ ] **Step 1: Write failing tests**: `test_a_trial_user_gets_the_prompt_on_every_print_route` (data provider over the print routes: `liquidation.print`, `accounting.dv.print`, the procurement document and PR prints, SIP, AIP, SOB, APP, APP-CSE, POW, delivery reconciliation, `cash.report.*`), `test_the_prompt_is_the_answer_to_a_direct_url`, `test_an_active_free_and_master_user_prints_as_before`, `test_the_live_previews_inside_forms_still_work_in_trial`.
- [ ] **Step 2: Run; expect FAIL.**
- [ ] **Step 3: Implement** the middleware and attach it to the routes by name (one list in the middleware, not scattered).
- [ ] **Step 4: Run; expect PASS** (also `OfficialPrintTest`, `CashReportPrintTest`).
- [ ] **Step 5: Commit** `feat: a trial cannot print`.

### Task 4: Other trial limits and the trial banner

**Files:** Modify `RestrictTrialActions` (Record Payment `cash.pay`, `cash.payment.update`, `cancelCheck`, general Drive upload `drive-files.store`, backup download), `partials/apple-design` or a new `partials/trial-banner.blade.php` included from `input-fixes`; Test `tests/Feature/TrialLimitsTest.php`.

**Interfaces:** Consumes `Subscription::isTrial()`. The Drive proof upload route (Task 7) is not restricted.

- [ ] **Step 1: Write failing tests**: `test_a_trial_cannot_record_a_payment_or_cancel_a_check`, `test_a_trial_cannot_upload_a_general_file_or_download_a_backup`, `test_the_trial_banner_shows_the_days_left`, `test_free_and_active_are_not_limited`.
- [ ] **Step 2–4:** run (FAIL), implement, run (PASS).
- [ ] **Step 5: Commit** `feat: trial limits and banner`.

### Task 5: Master actions on the Subscriptions page

**Files:** Create `SubscriptionAdminController.php`; Modify `routes/web.php`, `partials/subscriptions-live.blade.php` (row buttons and the View window with History), `subscription` History endpoint reuse of `liquidation.history` pattern (`subscriptions.history`); Test `tests/Feature/SubscriptionAdminTest.php`.

**Interfaces:**
- Produces routes (master and sub-master with `subscriptions` access; Free and Price are **master only**): `subscriptions.store` (Create Subscription), `subscriptions.activate`, `subscriptions.extend`, `subscriptions.suspend`, `subscriptions.reactivate`, `subscriptions.free.grant`, `subscriptions.free.remove`, `subscriptions.price`, `subscriptions.show` (View), `subscriptions.history`; all POST/PATCH take `reason` and `password`.
- Consumes: `SubscriptionService`.

- [ ] **Step 1: Write failing tests** for each action: works with a reason and the password; refused without a reason or with a wrong password; a sub-master cannot Grant Free or Set Price; the audit entry holds the reason; the row shows in History; the page follows (`subscriptions.live` hash changes).
- [ ] **Step 2–4:** run (FAIL), implement controller, routes and the row buttons using `data-guard` forms, run (PASS).
- [ ] **Step 5: Commit** `feat: working buttons on the Subscriptions page`.

### Task 6: Promo codes and payment settings

**Files:** Modify `SubscriptionAdminController` (promo CRUD, settings), `partials/subscriptions-live.blade.php` (Promo Codes panel, Payment Settings panel); Test `tests/Feature/PromoAndPaymentSettingsTest.php`.

**Interfaces:** Produces routes `promo-codes.store|update|toggle`, `payment-settings.update` (bank name, account name, account number, QR image, master Google email); settings keys `pay_bank_name`, `pay_account_name`, `pay_account_number`, `pay_qr` (data URI), `pay_master_email` in `SystemSetting`; `PaymentSettings::get(): array`.

- [ ] **Step 1: Write failing tests**: `test_only_the_master_manages_promo_codes_and_settings`, `test_a_code_is_unique_and_stored_upper_case`, `test_the_qr_must_be_png_or_jpg_up_to_300kb`, `test_the_email_is_validated`, `test_changes_ask_reason_and_password_and_are_audited`.
- [ ] **Step 2–4:** run (FAIL), implement, run (PASS).
- [ ] **Step 5: Commit** `feat: promo codes and payment settings`.

### Task 7: School Subscription page, proof of payment on the school's Drive, approval

**Files:** Create `SubscriptionController.php`, `resources/views/subscription.blade.php`; Modify `GoogleDriveService` (`share(connection, fileId, email)`), `routes/web.php`, `EnsureSubscriptionAllowsWrites` (the subscription, promo-check and Drive routes are allowed while read-only), `SubscriptionAdminController` (proofs); Test `tests/Feature/PaymentProofTest.php`.

**Interfaces:**
- Produces: `GET /subscription` (`subscription`), `POST /subscription/promo` (`subscription.promo`, returns the quote as JSON), `POST /subscription/proof` (`subscription.proof`, needs `drive.connected`), master `POST /subscriptions/proofs/{proof}/approve|reject`; `GoogleDriveService::share(GoogleDriveConnection $connection, string $fileId, string $email): void` (read-only permission for that email); `PaymentProof` columns as in the spec.
- Consumes: `SubscriptionPricing::quote`, `SubscriptionService::activateAnnual`, `PaymentSettings::get`.

- [ ] **Step 1: Write failing tests** (Drive HTTP faked with `Http::fake`): `test_the_school_sees_list_price_discount_and_amount_to_pay`, `test_the_proof_uploads_to_the_school_drive_and_is_shared_with_the_master_email_only`, `test_the_amount_must_match_and_the_file_type_and_size_are_checked`, `test_the_same_file_cannot_be_used_twice_or_by_another_school`, `test_one_pending_proof_and_five_a_day`, `test_without_drive_connection_the_school_is_sent_to_connect`, `test_approve_records_the_payment_activates_and_counts_the_promo_once`, `test_a_decided_proof_cannot_be_decided_again`, `test_the_price_is_fixed_at_submission`, `test_reject_needs_a_reason_the_school_sees`, `test_a_school_never_sees_another_school_proof`, `test_a_read_only_school_can_still_open_the_subscription_page_and_pay`.
- [ ] **Step 2–4:** run (FAIL), implement (row lock and status check inside the approval transaction), run (PASS).
- [ ] **Step 5: Commit** `feat: school subscription page and proof of payment`.

### Task 8: Live proofs and notifications source

**Files:** Modify `partials/subscriptions-live.blade.php` (Pending proofs panel inside the live block); Test extend `tests/Feature/SubscriptionsLiveTest.php`.

- [ ] **Step 1: Write the failing test** `test_a_new_proof_reaches_the_open_page` (hash changes when a proof is submitted or decided).
- [ ] **Step 2–4:** run (FAIL), implement, run (PASS).
- [ ] **Step 5: Commit** `feat: live pending proofs`.

### Task 9: Clock and one notification bell

**Files:** Create `NotificationController.php`, `partials/topbar-tools.blade.php`; Modify `partials/input-fixes.blade.php` (include), `routes/web.php` (`GET /notifications`, `GET /server-time`); Test `tests/Feature/NotificationsTest.php`.

**Interfaces:** Produces `GET /notifications` → `{count: int, items: [{title, detail, url, level}]}` by role as in the spec; `GET /server-time` → `{now: ISO 8601 with +08:00}`; the script inserts `[clock] [bell] [profile]` before the profile icon on every app page and removes the old per-page bell.

- [ ] **Step 1: Write failing tests**: `test_every_user_gets_the_subscription_notice` (trial days left, ending soon, expired, Free ending in 7 days), `test_the_master_gets_activation_requests_registrations_and_proofs`, `test_school_users_get_returned_ors_dvs_waiting_and_missing_papers_by_permission`, `test_notifications_stay_in_the_users_school_scope`, `test_server_time_is_philippine_time`, `test_the_pages_include_the_clock_and_one_bell` (page contains `data-topbar-tools`, no duplicate old bell).
- [ ] **Step 2–4:** run (FAIL), implement, check the script with `node --check` on the rendered page, run (PASS).
- [ ] **Step 5: Commit** `feat: clock and notification bell on every page`.

### Task 10: Dashboard Subscription Income

**Files:** Create `app/Services/SubscriptionIncomeService.php`; Modify the master dashboard view (`home.blade.php`) and `HomeController` dashboard data; Test `tests/Feature/SubscriptionIncomeTest.php`.

**Interfaces:** Produces `SubscriptionIncomeService::summary(?int $year): array{users: int, activation_requests: int, gross: float, system_subscription: float, operating: float, net: float}` with 30% / 20% / 50% of the gross (rounded to centavos so the three add up to the gross); the dashboard has a year choice (all years, or one).

- [ ] **Step 1: Write failing tests**: `test_gross_is_the_sum_of_amounts_really_paid`, `test_the_division_is_30_20_50_and_adds_up_to_the_gross`, `test_a_year_filters_by_the_payment_date`, `test_users_registered_and_activation_requests_counts`, `test_only_the_master_and_sub_master_see_the_section`.
- [ ] **Step 2–4:** run (FAIL), implement, run (PASS).
- [ ] **Step 5: Commit** `feat: subscription income on the dashboard`.

### Task 11: Documents and the full run

**Files:** Modify `docs/specs.md` (new sections 79 onward), `CLAUDE.md` (status lines).

- [ ] **Step 1:** Add the specs sections (plan and lifecycle, trial, Free and prices, payment system, clock and notifications, dashboard) and the status lines.
- [ ] **Step 2:** Run the whole suite (`php artisan test --compact`); expect all green. Run `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 3: Commit and push** `docs: subscription system`. Deploy only when the owner says so (the owner collects all development for one deploy).
