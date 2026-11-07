# Support requests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the Support page into a request conversation with five statuses, staff assignment and queue commands in Telegram, images kept in the user's own Google Drive, and a 5-day purge of resolved chats.

**Architecture:** `support_messages` stays the requests table and gains type, subject, status, assignee and `resolved_at`; `support_replies` becomes the two-sided conversation; `support_attachments` holds Drive links only. A small `SupportStatus` class owns the status names and labels. Telegram commands are parsed in `TelegramWebhookController` and delegated to a `SupportDesk` service so the rules live in one place.

**Tech Stack:** Laravel 13, SQLite, Blade + Tailwind, `TelegramService`, `GoogleDriveService`, PHPUnit via `php artisan test`.

**Spec:** `docs/superpowers/specs/2026-11-07-support-requests-design.md`

## Global Constraints

- Statuses are exactly `pending`, `for_process`, `on_hold`, `approved`, `resolved`; labels Pending, For process, On hold, Approved, Resolved.
- Types are exactly `general` and `subscription`; the button reads "Create request"; the list reads "Your requests".
- Attachments: JPG, PNG, WEBP, PDF; at most 3 per message; 5 MB each; stored only in the sender's own Google Drive `Support` folder, shared with the administrator; Railway keeps only `support_attachments` rows.
- Purge: `resolved_at` older than 5 days; replies and attachment rows deleted; Drive files untouched.
- The sender's email never appears in Telegram; the assignee is shown to the user as a first name only.
- `Approved` is a label only; it never changes a subscription.
- Replies are never routed by "most recent"; an unmatched staff message is never delivered (existing rule, keep it).
- Tests first (RED seen), minimal change, no mass reformat, CRLF files edited with the Edit tool only (no inline Python), docs updated at each deploy.
- Deploy only when the owner says "deploy"; push after each part.

## Review Focus

- A user opens another user's request URL: 404, never the content (task 3).
- Two staff send `/assign` at once: exactly one wins, the other is told who (tasks 4, 5).
- A resolved request receives a user reply or an `/hold`: refused, status unchanged (tasks 3, 5).
- A purge runs while a request is resolved 4 days 23 hours ago: it stays (task 8).
- An attachment of the wrong type, over 5 MB, or a 4th file: refused with a message, nothing saved (task 7).
- Drive not connected when attaching: the text message is still saved and the user is told to connect Drive (task 7).

---

## Part 1: Page, conversation, statuses (web only)

### Task 1: Statuses and schema

**Files:**
- Create: `database/migrations/2026_11_08_000003_make_support_requests.php`, `app/Support/SupportStatus.php`
- Modify: `app/Models/SupportMessage.php`, `app/Models/SupportReply.php`
- Test: `tests/Feature/SupportRequestSchemaTest.php`

**Interfaces:**
- Produces: `SupportStatus::ALL` (array of the five keys), `SupportStatus::label(string $status): string`, `SupportStatus::isOpen(string $status): bool` (false only for `resolved`); `SupportMessage` fillable gains `type, subject, assigned_name, assigned_telegram_id, resolved_at`; `SupportMessage::attachments()`; `SupportReply` fillable gains `sender, user_id`; `SupportReply::attachments()`.

- [ ] **Step 1: Write the failing test** `test_existing_rows_are_mapped_and_new_columns_exist`: insert rows with status `open`, `answered`, `closed`, run the migration's mapping (re-run via `Artisan::call('migrate')` on a fresh DB seeded before the migration is not possible, so assert the mapping through a public static `MakeSupportRequests::map(string $old): string`-style closure exposed as `SupportStatus::fromLegacy(string): string`) returning `pending`, `for_process`, `resolved`; assert `SupportStatus::label('on_hold') === 'On hold'` and `SupportStatus::isOpen('resolved') === false`; assert the columns exist with `Schema::hasColumns('support_messages', ['type','subject','assigned_name','assigned_telegram_id','resolved_at'])` and `Schema::hasColumns('support_replies', ['sender','user_id'])` and `Schema::hasTable('support_attachments')`.
- [ ] **Step 2: Run** `php artisan test --filter=SupportRequestSchemaTest` and expect FAIL (class/columns missing).
- [ ] **Step 3: Implement** the migration (add columns with `type` default `general`, `sender` default `staff`; create `support_attachments` with the columns in the spec; update legacy status values with three `UPDATE`s; set `resolved_at = updated_at` for the rows mapped to `resolved`), `SupportStatus`, and the model changes. New requests default `status = 'pending'` (change the column default in the migration).
- [ ] **Step 4: Run** the test and `php artisan test --compact`; expect PASS.
- [ ] **Step 5: Commit** `feat: support request statuses and schema`.

### Task 2: Create a request and the sidebar link

**Files:**
- Modify: `app/Http/Controllers/SupportController.php` (`send` becomes `store` keeping route name `support.send`), `resources/views/layouts/budget.blade.php:18` (add the Support item above School Settings: `['icon'=>'support_agent','label'=>'Support','route'=>'support']`), `resources/views/layouts/procurement.blade.php` (same link where its sidebar lists School Settings)
- Test: `tests/Feature/SupportPageTest.php`, `tests/Feature/SupportTelegramTest.php`

**Interfaces:**
- Produces: `POST support.send` accepts `type` (`general|subscription`), `subject` (required, max 120), `body` (required, min 3, max 2000); creates the request with `status = pending`; the Telegram text starts with `🧾 ` for `subscription`.

- [ ] **Step 1: Write the failing tests:** `test_the_sidebar_has_a_support_link` (GET home, assertSee `route('support')` href and label "Support"); `test_a_request_needs_a_subject_and_a_known_type` (missing subject, `type=other` give session errors, nothing saved); `test_a_subscription_request_is_marked_in_telegram` (Http::fake, assert body contains `🧾`).
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** validation with `Rule::in(['general','subscription'])`, the sidebar entries (highlight already handled by `$routeName === 'support'`), and the Telegram prefix. Keep email delivery and `telegram_message_id` logic unchanged.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Commit** `feat: Support link in the sidebar and typed requests`.

### Task 3: Conversation page and user follow-ups

**Files:**
- Create: `resources/views/partials/support-conversation.blade.php`
- Modify: `app/Http/Controllers/SupportController.php` (add `thread(SupportMessage $request)` and `reply(Request, SupportMessage $request)`), `routes/web.php` (`GET /support/{supportMessage}` named `support.show`, `POST /support/{supportMessage}/replies` named `support.reply`, throttle `20,1`, `whereNumber`), `resources/views/support.blade.php`
- Test: `tests/Feature/SupportConversationTest.php`

**Interfaces:**
- Consumes: Task 1 models.
- Produces: `support.show` renders the list (left) and the chat bubbles (right); `support.reply` stores a `SupportReply` with `sender = user`, forwards `"💬 Support #N: <text>"` to Telegram as a reply to the request's `telegram_message_id`, refuses (422/redirect with error) when the request is `resolved`, and returns 404 for another user's request (master may read, not post).

- [ ] **Step 1: Write the failing tests:** `test_a_user_cannot_open_or_answer_another_users_request` (404 both); `test_a_user_can_answer_and_it_goes_to_telegram_under_the_request` (assert stored reply `sender=user`, Http body contains the text and `reply_to_message_id`); `test_a_resolved_request_cannot_be_answered` (status unchanged, no reply row, no Telegram call); `test_the_conversation_shows_both_sides_in_time_order`.
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** the routes, the two controller methods (scope the lookup with `where('user_id', $user->id)` unless master), and the Blade layout: two columns from `md:`, one column below, status chip from `SupportStatus::label`, bubbles for user and Customer Services, "Resolved on <date>, removed on <date + 5 days>" for resolved. Keep contact/Quick help sections. Check the page at 320, 768 and 1280 px (text wraps inside its box) before calling it done.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Commit** `feat: Support conversation page with user follow-ups`; push; report to the owner (not deployed).

## Part 2: Telegram assignment and statuses

### Task 4: `SupportDesk` rules

**Files:**
- Create: `app/Services/SupportDesk.php`
- Test: `tests/Feature/SupportDeskTest.php`

**Interfaces:**
- Produces: `SupportDesk::assign(SupportMessage $request, int $telegramId, string $name): bool` (atomic: `UPDATE ... WHERE id = ? AND assigned_telegram_id IS NULL`, returns whether this call won); `SupportDesk::setStatus(SupportMessage $request, string $status): bool` (false when the request is `resolved` and `$status !== 'resolved'`; sets `resolved_at = now()` on `resolved`); `SupportDesk::queue(): string` (text of every non-resolved request grouped by status in the order pending, for_process, on_hold, approved; unassigned first; each line `#id · subject · school · assignee|unassigned · waiting N h/d`); `SupportDesk::mine(int $telegramId): string`.

- [ ] **Step 1: Write the failing tests:** `test_the_first_person_to_assign_wins` (second `assign` returns false, name unchanged); `test_a_resolved_request_cannot_be_moved_back` ; `test_resolving_sets_the_time`; `test_the_queue_lists_unassigned_first_and_leaves_out_resolved`; `test_mine_lists_only_my_requests`.
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** the four methods; the queue query uses `SupportMessage::withoutGlobalScopes()` and `with('school')`, limited to 50 rows with a "+N more" line.
- [ ] **Step 4: Run** the tests; expect PASS.
- [ ] **Step 5: Commit** `feat: SupportDesk assignment, statuses and queue`.

### Task 5: Telegram commands

**Files:**
- Modify: `app/Http/Controllers/TelegramWebhookController.php`
- Test: `tests/Feature/SupportTelegramTest.php`

**Interfaces:**
- Consumes: `SupportDesk`.
- Produces: commands `/assign [@name|number]`, `/process`, `/hold`, `/approve`, `/resolve` (each as a reply to the request message, or followed by the number), `/queue`, `/mine`; bot confirmations "👤 Support #N · <name>", "▶ Support #N for process · <name>", "⏸ … on hold", "👍 … approved", "✅ … resolved" (replacing the old "answered"/"closed" markers; `/close` remains as an alias of `/resolve`); a refused move on a resolved request replies "Support #N is resolved."; a normal reply sets `for_process` only when the request is `pending`, and sends the existing answered marker.

- [ ] **Step 1: Write the failing tests:** one per command through the webhook (`/assign` stores `from.id` and first name; second `/assign` from another id is told who has it; `/hold 12` by number; `/queue` posts the grouped text; `/mine`); `test_a_normal_reply_moves_a_pending_request_to_for_process_but_not_an_approved_one`.
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** the command parsing with one regex `^/(assign|process|hold|approve|resolve|close|queue|mine)(?:@\w+)?(?:\s+(.+))?$`, resolving the target by reply-to message id or the first number in the argument, then delegating to `SupportDesk`. Unknown `/commands` stay ignored.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Commit** `feat: Telegram assign, status commands, queue and mine`.

### Task 6: Subscription notice and the user's status view

**Files:**
- Modify: `app/Http/Controllers/SubscriptionController.php` (after `PaymentProof::create`), `resources/views/partials/support-conversation.blade.php` ("Handled by <first name>" when assigned)
- Test: `tests/Feature/SupportTelegramTest.php`

**Interfaces:**
- Produces: after a payment proof is saved, `TelegramService::send("🧾 Payment proof · <school> · ₱<amount> · ref <reference>")`; no file, no email; a Telegram failure never fails the proof.

- [ ] **Step 1: Write the failing tests:** `test_a_payment_proof_posts_a_short_notice_without_the_file_or_email`; `test_the_user_sees_the_first_name_of_the_person_handling_the_request`.
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** both; wrap the send so exceptions are logged and swallowed.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Commit** `feat: payment proof notice in Telegram`; push; report to the owner (not deployed).

## Part 3: Images in the user's Drive and the 5-day purge

### Task 7: Attachments to the user's own Drive

**Files:**
- Create: `app/Services/SupportAttachments.php`, `app/Models/SupportAttachment.php`
- Modify: `app/Http/Controllers/SupportController.php` (`store` and `reply` accept `files[]`), `resources/views/support.blade.php`, `resources/views/partials/support-conversation.blade.php`
- Test: `tests/Feature/SupportAttachmentTest.php`

**Interfaces:**
- Produces: `SupportAttachments::store(User $user, array $files, SupportMessage $request, ?SupportReply $reply = null): array` uploads each file with `GoogleDriveService::upload($connection, 'Support', $name, $contents, $mime)`, shares it with `PaymentSettings::get()['master_email']`, creates `SupportAttachment` rows; throws `DriveNotConnected` when the user's Drive is not connected. Validation: `files` max 3; `files.*` `file|mimes:jpg,jpeg,png,webp,pdf|max:5120`.

- [ ] **Step 1: Write the failing tests** (Http::fake for Drive): `test_an_image_goes_to_the_users_drive_and_only_a_link_is_stored`; `test_a_wrong_type_an_oversized_file_and_a_fourth_file_are_refused_and_nothing_is_saved`; `test_without_drive_the_text_is_saved_and_the_user_is_told_to_connect_drive`.
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** the service following `SubscriptionController::storeProof` (upload, share, delete the Drive file if sharing fails), the model, the form field (`multiple`, `accept`), and a bubble attachment line with the Drive link via `GoogleDriveService::webLink`. When Drive is missing, save the text first and redirect to the conversation with a message linking to the Google Drive page.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Commit** `feat: Support attachments saved in the user's own Drive`.

### Task 8: Five-day purge

**Files:**
- Create: `app/Console/Commands/PurgeSupport.php`
- Modify: `routes/console.php` (daily `support:purge` at 01:00 Asia/Manila)
- Test: `tests/Feature/SupportPurgeTest.php`

**Interfaces:**
- Produces: `php artisan support:purge` deletes requests with `resolved_at <= now()->subDays(5)` and their replies and attachment rows (`withoutGlobalScopes`), writes one `AuditLog` line with the count, prints the count.

- [ ] **Step 1: Write the failing tests:** `test_a_request_resolved_five_days_ago_is_deleted_with_its_replies_and_attachment_rows`; `test_one_resolved_4_days_23_hours_ago_stays`; `test_open_requests_are_never_purged`; `test_no_drive_file_is_deleted` (`Http::assertNothingSent`).
- [ ] **Step 2: Run** them; expect FAIL.
- [ ] **Step 3: Implement** the command and the schedule line.
- [ ] **Step 4: Run** the full suite; expect PASS.
- [ ] **Step 5: Update docs** (`docs/specs.md` section 119, `docs/STATUS_HISTORY.md`, `CLAUDE.md` status list: committed, not yet deployed); commit `feat: purge resolved support requests after five days`; push; report to the owner.

## Final

- [ ] Run `php artisan test --compact`; run the code-review skill on the branch; fix findings; ask the owner whether to deploy (never deploy unasked); after a deploy verify with `railway deployment list --service procurems`, update the "Live:" line, commit and push.
