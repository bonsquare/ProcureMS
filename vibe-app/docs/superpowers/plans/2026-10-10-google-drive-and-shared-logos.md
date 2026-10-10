# Google Drive repository, shared logos and system backup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Share the department and division logos across schools, give every user a private Google Drive repository, and let the master user back up the whole database to Drive with a history.

**Architecture:** A `shared_logos` table feeds `OfficialDocument::logos()`. A `GoogleDriveService` (Laravel HTTP client, no new package) owns OAuth tokens and Drive REST calls, stored per user in `google_drive_connections`. Repository files and backups reference Drive by id in `drive_files` and `backup_runs`; a `drive.connected` middleware blocks uploads until the user is connected.

**Tech Stack:** Laravel 13, PHP 8.4, SQLite, PHPUnit 12 (`php artisan test --compact`), Blade, Laravel `Http::fake()` for Google.

**Spec:** `vibe-app/docs/superpowers/specs/2026-10-10-google-drive-and-shared-logos-design.md`. Read it with this plan.

## Global Constraints

- No new Composer package; Google OAuth and Drive calls use `Illuminate\Support\Facades\Http`.
- OAuth scope is exactly `https://www.googleapis.com/auth/drive.file`, `access_type=offline`, redirect `https://procms.celsys.trade/google-drive/callback` (read from `config('services.google.redirect')`).
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` come from the environment; never committed, never in tests except fake values.
- Tokens are stored encrypted (`'encrypted'` cast); never plain text.
- Drive folders: `ProcMS` containing `Backup`, `Logo`, `Files`.
- A repository file is visible only to its owner (`user_id`); master and school admins get 404/403 like anyone else.
- Uploads blocked until the user's connection is `connected`; logo uploads are never blocked.
- Replacing an existing shared department or division logo is master only.
- Backup limit: keep the last 30 automatic backups; upload limit 20 MB for repository files.
- Follow existing code: `permission:` middleware aliases in `bootstrap/app.php`, routes in `routes/web.php` inside the existing auth group, `$this->isMasterUser()` style role check (`$user->role === 'master_user'`), Blade pages with the same `$navigation` array pattern, tests in `tests/Feature` with `RefreshDatabase` and the `tenant()` helper style of `OfficialPrintTest`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Commit steps run only when the user has asked for commits; otherwise skip them.

## Review Focus

- Division written as `" cebu city "` and `"Cebu City"` must resolve to the same shared logo; a school with a blank division must never get one.
- Google redirects back with `?error=access_denied` (user pressed Cancel): no connection is created and the page shows a plain message.
- Google answers `invalid_grant` while uploading: no `drive_files` row is created, the connection becomes `needs_reconnect`, the user sees the reconnect message.
- The user deleted the `ProcMS/Files` folder in Drive: the next upload recreates the folders instead of failing.
- Backup on a non-SQLite connection or when Drive is not connected fails with a recorded `backup_runs` error, not a 500 and not a half-created row marked successful.

---

### Task 1: Shared logos table, resolver and backfill

**Files:**
- Create: `database/migrations/2026_10_10_080001_create_shared_logos_table.php`, `app/Models/SharedLogo.php`
- Modify: `app/Support/OfficialDocument.php`
- Test: `tests/Feature/SharedLogoTest.php`

**Interfaces:**
- Produces: `SharedLogo` (fillable `kind`, `key`, `path`, `uploaded_by`); `SharedLogo::divisionKey(?string $region, ?string $division): ?string` (lower-cased, whitespace-collapsed `"region|division"`, null when division is blank); `SharedLogo::department(): ?self`; `SharedLogo::forDivision(?School $school): ?self`.
- `OfficialDocument::logos(?School $school, ?AgencySetting $agency): array{0: ?string, 1: ?string}` keeps its signature; left = shared department logo, else the agency's own `department_logo_path`, else the DepEd seal; right = school logo, else shared division logo, else agency `division_logo_path`.

- [ ] **Step 1: Write failing tests in `SharedLogoTest`**: `test_division_key_ignores_case_and_spaces` (`divisionKey('Region VII', ' Cebu  City ')` equals `divisionKey('region vii','cebu city')`), `test_blank_division_has_no_key` (null), `test_a_school_uses_the_shared_logo_of_its_division` (two schools, same division, `Storage::fake('public')`, one `SharedLogo` row with an existing file; `OfficialDocument::logos($schoolB, null)[1]` ends with that path), `test_a_school_logo_wins_over_the_division_logo`, `test_the_shared_department_logo_is_used_for_every_school` (left logo), `test_existing_division_logo_is_backfilled_by_the_migration` (insert an `agency_settings` row with `division_logo_path` and a school with that division/region, run the backfill, assert one `shared_logos` row; run again, still one).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SharedLogoTest`. Expected: FAIL (class missing).
- [ ] **Step 3: Create the migration**: table `shared_logos` (`id`, `kind` string, `key` string, `path` string, `uploaded_by` nullable foreignId users nullOnDelete, timestamps, unique [`kind`,`key`]). In the same migration `up()` backfill from `agency_settings` joined to each organization's schools: department logo to key `deped` (first non-empty wins), division logo to `divisionKey(region_name or school region, division_name or school division)`; skip rows with a null key; never overwrite an existing row.
- [ ] **Step 4: Implement `SharedLogo` and update `OfficialDocument::logos()`** as in Interfaces; `publicUrl()` already checks the file exists.
- [ ] **Step 5: Run the filter again.** Expected: PASS. Run `php artisan test --compact tests/Feature/OfficialPrintTest.php` to confirm the existing print tests still pass.
- [ ] **Step 6: Commit** `feat: share department and division logos across schools`.

### Task 2: Saving shared logos (master-only replace) and the settings screens

**Files:**
- Modify: `app/Http/Controllers/HomeController.php` (`updateAgencySettings` ~1960-2010, `updateSchoolDetails` ~2020-2045, the `schoolSettings` data ~1885-1905), `resources/views/partials/settings/info.blade.php` (logo blocks at lines ~62, ~81)
- Test: `tests/Feature/SharedLogoTest.php` (add)

**Interfaces:**
- Consumes: `SharedLogo::department()`, `SharedLogo::forDivision()`, `SharedLogo::divisionKey()` from Task 1.
- Produces: `HomeController::storeSharedLogo(Request $request, string $field, string $kind, string $key): ?string` (private; returns the stored path or null; aborts 403 when a row exists and the user is not `master_user`).

- [ ] **Step 1: Write failing tests** in `SharedLogoTest`: `test_first_upload_of_a_division_logo_is_shared_with_other_schools` (school admin posts `school-settings.agency` with `division_logo`; a second school of the same division resolves it), `test_a_school_admin_cannot_replace_an_existing_shared_logo` (403, row unchanged), `test_the_master_user_can_replace_a_shared_logo`, `test_department_logo_is_one_for_all` (first upload creates the `deped` row; second school admin gets 403 on replace). Use `UploadedFile::fake()->image('logo.png', 300, 300)` and `Storage::fake('public')`.
- [ ] **Step 2: Run** the filter. Expected: FAIL.
- [ ] **Step 3: Implement.** In `updateAgencySettings`, replace the two `->store('logos','public')` writes for department and division with `storeSharedLogo(...)`: it inserts the `SharedLogo` row when none exists for (`kind`,`key`), replaces it (and keeps the old file deleted) only for the master user, otherwise `abort(403, 'Only the master user can replace a shared logo.')`. Keep writing the agency's own `*_logo_path` columns untouched (no new writes). District and school logo code is unchanged.
- [ ] **Step 4: Update `schoolSettings()` data and `info.blade.php`** so the department and division logo blocks show the shared logo and, when one exists and the user is not master, render read-only (no file input) with the text "Shared logo, only the master user can change it."
- [ ] **Step 5: Run** `php artisan test --compact --filter="SharedLogoTest|SchoolSettingsTest|OfficialPrintTest"`. Expected: PASS.
- [ ] **Step 6: Commit** `feat: department and division logos are shared, replaced by the master only`.

### Task 3: Google Drive connection (OAuth, tokens, folders)

**Files:**
- Create: `database/migrations/2026_10_10_080002_create_google_drive_connections_table.php`, `app/Models/GoogleDriveConnection.php`, `app/Services/GoogleDriveService.php`, `app/Exceptions/DriveNotConnected.php`, `app/Http/Controllers/GoogleDriveController.php`
- Modify: `config/services.php`, `routes/web.php` (replace lines for `google-drive` and `google-drive.settings`), `resources/views/google-drive.blade.php`, `app/Http/Controllers/HomeController.php` (remove `googleDrive()` and `updateGoogleDriveSettings()`), `.env.example`
- Test: `tests/Feature/GoogleDriveConnectionTest.php`

**Interfaces:**
- Produces `GoogleDriveConnection` (`user_id` unique, `google_email`, `access_token` and `refresh_token` encrypted casts, `expires_at` datetime, `root_folder_id`, `folder_ids` array, `status` `connected|needs_reconnect`, `connected_at`); `User::driveConnection()` hasOne.
- Produces `GoogleDriveService`:
  - `authorizationUrl(User $user): string` (puts a random `state` in the session key `google_drive_state`)
  - `connect(User $user, string $code): GoogleDriveConnection` (token exchange, reads the account email, calls `ensureFolders`)
  - `accessToken(GoogleDriveConnection $c): string` (refreshes when expired; on `invalid_grant` or 401 sets `needs_reconnect` and throws `DriveNotConnected`)
  - `ensureFolders(GoogleDriveConnection $c): array{root: string, Backup: string, Logo: string, Files: string}`
  - `upload(GoogleDriveConnection $c, string $folder, string $name, string $contents, string $mime): array{id: string, size: int}` (recreates the folders and retries once when Drive answers 404 for the parent)
  - `delete(GoogleDriveConnection $c, string $fileId): void`
  - `webLink(string $fileId): string` (`https://drive.google.com/file/d/{id}/view`)
- Routes (names): `google-drive` GET (connect page), `google-drive.redirect` GET (`/google-drive/connect`), `google-drive.callback` GET, `google-drive.disconnect` DELETE.

- [ ] **Step 1: Write failing tests** with `Http::fake()` for `oauth2.googleapis.com/token`, `www.googleapis.com/oauth2/v2/userinfo` and `www.googleapis.com/drive/v3/files*`: `test_connect_page_offers_google_sign_in`, `test_redirect_sends_the_user_to_google_with_the_drive_file_scope_and_offline_access`, `test_callback_with_a_wrong_state_is_refused`, `test_callback_stores_encrypted_tokens_and_creates_the_four_folders` (assert the raw `access_token` column differs from the fake token), `test_cancel_on_the_google_screen_creates_no_connection` (`?error=access_denied`), `test_an_expired_token_is_refreshed`, `test_invalid_grant_marks_the_connection_needs_reconnect`, `test_a_deleted_folder_is_recreated_on_upload`, `test_a_user_only_sees_and_disconnects_their_own_connection`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=GoogleDriveConnectionTest`. Expected: FAIL.
- [ ] **Step 3: Implement** migration, model, `config/services.php` `google` array (`client_id`, `client_secret`, `redirect` from env), `.env.example` keys (empty values), service, controller (any authenticated user; callback verifies `state` and handles `error`), routes inside the existing auth group, and the new connect page (status, connected Google email, Connect / Reconnect / Disconnect, link to My Drive Files; copy in Filipino-neutral plain English like the other pages). Delete the two old HomeController methods and their routes.
- [ ] **Step 4: Run the filter and** `php artisan test --compact` for the whole suite to catch any page that referenced the removed routes. Expected: PASS.
- [ ] **Step 5: Commit** `feat: connect a personal Google Drive per user`.

### Task 4: Upload gate and reconnect banner

**Files:**
- Create: `app/Http/Middleware/EnsureDriveConnected.php`, `resources/views/partials/drive-banner.blade.php`
- Modify: `bootstrap/app.php` (alias `'drive.connected'`), the layouts or page shells that already include `partials/input-fixes` (`resources/views/layouts/budget.blade.php`, `layouts/procurement.blade.php`, `home.blade.php`) to `@include('partials.drive-banner')`
- Test: `tests/Feature/DriveGateTest.php`

**Interfaces:**
- Consumes: `User::driveConnection()` from Task 3.
- Produces: middleware alias `drive.connected` (redirects to `route('google-drive')` with `error` flash "Connect your Google Drive before uploading files." when the user has no connection or its status is not `connected`; JSON requests get 409); `User::driveIsConnected(): bool`.

- [ ] **Step 1: Write failing tests**: `test_a_user_without_drive_is_redirected_from_a_guarded_route` (register a throwaway guarded route in the test with `Route::middleware(['web','auth','drive.connected'])`), `test_needs_reconnect_is_blocked_like_no_connection`, `test_a_connected_user_passes`, `test_the_banner_shows_only_when_status_is_needs_reconnect`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=DriveGateTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the middleware, alias, `User::driveIsConnected()`, and the banner partial (nothing is rendered for users with no connection or a healthy one).
- [ ] **Step 4: Run the filter.** Expected: PASS.
- [ ] **Step 5: Commit** `feat: block file uploads until Google Drive is connected`.

### Task 5: My Drive Files

**Files:**
- Create: `database/migrations/2026_10_10_080003_create_drive_files_table.php`, `app/Models/DriveFile.php`, `app/Http/Controllers/DriveFileController.php`, `resources/views/drive-files.blade.php`
- Modify: `routes/web.php`, `resources/views/google-drive.blade.php` (link)
- Test: `tests/Feature/DriveFilesTest.php`

**Interfaces:**
- Consumes: `GoogleDriveService::upload/delete/webLink`, middleware `drive.connected`.
- Produces: `DriveFile` (`user_id`, `name`, `drive_file_id`, `mime`, `size`); routes `drive-files.index` GET `/drive-files`, `drive-files.store` POST (`drive.connected`, field `file`, max 20480 KB), `drive-files.destroy` DELETE `/drive-files/{driveFile}`. Every query is scoped to `$request->user()->id`.

- [ ] **Step 1: Write failing tests**: `test_upload_goes_to_the_files_folder_and_is_listed`, `test_upload_is_blocked_when_not_connected`, `test_a_file_over_20mb_is_refused_with_a_plain_message`, `test_other_users_cannot_see_or_delete_my_file` (same school user, school admin, master: each gets 404 on destroy and does not see it in the index), `test_delete_removes_the_drive_file_and_the_row`, `test_invalid_grant_during_upload_creates_no_row_and_marks_needs_reconnect`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=DriveFilesTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the migration, model, controller (the store action reads the file contents, calls `upload('Files', ...)`, creates the row only after Drive confirms; `DriveNotConnected` becomes the reconnect redirect), the page (status, upload form, list with name, size, date, "Open in Drive", Delete) and the routes.
- [ ] **Step 4: Run the filter.** Expected: PASS.
- [ ] **Step 5: Commit** `feat: private Google Drive file repository per user`.

### Task 6: System backup, history and daily run

**Files:**
- Create: `database/migrations/2026_10_10_080004_create_backup_tables.php`, `app/Models/BackupRun.php`, `app/Models/BackupDownload.php`, `app/Services/DatabaseBackupService.php`, `app/Console/Commands/BackupDatabase.php`, `app/Http/Controllers/BackupController.php`, `resources/views/backup.blade.php`
- Modify: `routes/web.php`, `routes/console.php`
- Test: `tests/Feature/DatabaseBackupTest.php`

**Interfaces:**
- Consumes: `GoogleDriveService::upload/delete`, `GoogleDriveConnection` of the user in `$createdBy`.
- Produces: `DatabaseBackupService::run(string $type, ?User $by = null): BackupRun` (`type` `manual|automatic`; automatic uses the first `master_user`'s connection); `DatabaseBackupService::copyToTempFile(): string` (path of a `VACUUM INTO` copy, throws `RuntimeException` when the default connection is not SQLite); `BackupRun::status` `success|failed`; `DatabaseBackupService::prune(int $keep = 30): int` (deletes older automatic backups in Drive and their rows' `drive_file_id`, returns how many). Command `backup:database` (`--manual` not needed; used by the scheduler). Routes: `backup.index` GET `/backup`, `backup.run` POST, `backup.download` GET `/backup/{backupRun}/download`; all return 403 for a non-master user.
- Schedule: `Schedule::command('backup:database')->dailyAt('00:00')->timezone('Asia/Manila')` in `routes/console.php` (the app timezone is UTC, so the zone is set on the schedule; midnight Philippine time).

- [ ] **Step 1: Write failing tests**: `test_only_the_master_user_can_open_run_and_download_backups` (school admin gets 403 on all three), `test_a_manual_backup_is_uploaded_to_the_backup_folder_and_recorded` (assert the fake Drive upload name matches `procms-YYYY-MM-DD-HHMM.sqlite` and a `success` row exists), `test_downloading_logs_who_and_when` (a `backup_downloads` row, shown in the history), `test_a_failed_upload_is_recorded_not_thrown` (Drive returns 500 → `failed` row with `error` text, page still 200), `test_not_connected_master_records_a_failed_run`, `test_prune_keeps_the_latest_30_automatic_backups` (create 32, run, 30 remain, 2 Drive deletes), `test_a_non_sqlite_connection_fails_cleanly` (call `copyToTempFile()` with the driver mocked via `config(['database.default' => 'mysql'])` and assert `RuntimeException`, then that `run()` stores `failed`).
- [ ] **Step 2: Run** `php artisan test --compact --filter=DatabaseBackupTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the tables (`backup_runs`: `type`, `status`, `size` nullable, `drive_file_id` nullable, `file_name` nullable, `created_by` nullable, `error` text nullable, timestamps; `backup_downloads`: `backup_run_id`, `user_id`, timestamps), the service (`DB::statement("VACUUM INTO ?", [$path])` to a file under `storage_path('app/backup-tmp')`, always deleted in `finally`), command, controller, page (Backup now button, history table below with date, type, size, status, downloaded by and when), routes and the daily schedule. Download streams a fresh `copyToTempFile()` copy, logs the row, and deletes the temp file after sending.
- [ ] **Step 4: Show the last failed backup on the System Master Dashboard.** In `HomeController`, pass `lastBackupFailure` (the newest `failed` `BackupRun` newer than the newest `success`, or null) to `home.blade.php` for the master user only, and render one warning line with its date and a link to `backup.index`. Add `test_the_master_dashboard_warns_about_a_failed_backup` (and that a school admin never sees it) to `DatabaseBackupTest`.
- [ ] **Step 5: Run the filter and the whole suite** (`php artisan test --compact`). Expected: PASS.
- [ ] **Step 6: Commit** `feat: master database backup to Google Drive with history`.

### Task 7: Logo archive copy and documentation

**Files:**
- Modify: `app/Http/Controllers/HomeController.php` (after each logo save), `docs/specs.md` (new section 17 "Shared logos, Google Drive and backup"), `HANDOFF_TO_CLAUDE.md` (status line)
- Test: `tests/Feature/GoogleDriveConnectionTest.php` (add)

**Interfaces:**
- Consumes: `GoogleDriveService::upload($connection, 'Logo', ...)` and `User::driveConnection()`.
- Produces: `HomeController::archiveLogoToDrive(?User $user, string $path): void` (private; best effort: does nothing when the user is not connected, catches and logs `DriveNotConnected` and HTTP errors so a logo save never fails because of Drive).

- [ ] **Step 1: Write failing tests**: `test_a_saved_logo_is_copied_to_the_logo_folder_when_connected`, `test_a_logo_still_saves_when_drive_is_not_connected`, `test_a_logo_still_saves_when_drive_fails`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=GoogleDriveConnectionTest`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement** `archiveLogoToDrive` and call it after the four logo saves; write the specs section (the rules from the spec's Decisions list, the Railway variables, the Google Cloud setup, the routes) and the handoff line.
- [ ] **Step 4: Run the whole suite and Pint.** Expected: all green, no style changes left.
- [ ] **Step 5: Commit** `feat: archive logos to Drive and document the Drive and backup setup`.

---

## Rollout (after Task 7, with the user)

1. User creates the Google Cloud OAuth client (consent screen In production, redirect above) and sets `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` in Railway themselves.
2. Deploy from `vibe-app`: `railway up --detach --service procurems` (Git Bash: `MSYS_NO_PATHCONV=1`).
3. Master signs in, connects Drive, runs **Backup now**, checks the file in `ProcMS/Backup`.
