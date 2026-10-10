# Google Drive repository, shared logos and system backup: design

Date: 2026-10-10. Status: design approved in chat, written spec awaiting review.

## Goal

1. Logos stay on the Railway volume, but the department logo and each division logo are stored once and reused by every school that belongs to them.
2. Every user can connect their own personal Google Drive and keep their own files there. Drive is a private repository of that user; nobody else in the app can see or open the files.
3. The master user backs up the whole database to Drive, by hand and every day, with a history.

## Decisions taken (and who took them)

- Logos are not stored in Drive; the `Logo` folder in Drive holds an archive copy only.
- One user, one school, one Drive. A user's uploads go only to their own Drive. Nothing is shared with other users inside the app; the user e-mails a file themselves if they want to show it.
- A user must connect Drive before they can upload files; until then every file upload is blocked and the page shows a Connect button. Logo uploads are not blocked (they live on Railway).
- Replacing a shared logo (department or division) that already exists is master only.
- No new Composer package: the Google OAuth and Drive REST calls use the Laravel HTTP client.

## Out of scope (version 1)

Data export for school admins, sharing files inside the app, syncing back from Drive, deleting a user's Drive files when their account is removed.

## Part 1: Shared logos

- New table `shared_logos` (`id`, `kind` = `department` | `division`, `key`, `path`, `uploaded_by`, timestamps), unique on (`kind`, `key`).
  - `department`: one row, key `deped`.
  - `division`: key is the normalized (lower-cased, trimmed) region and division name of the school profile.
- Resolution in `App\Support\OfficialDocument::logos()`: left logo = shared department logo, then the national DepEd seal; right logo = the school's own `logo_path`, then the shared division logo of the school's division. No school-specific fallback (unchanged).
- Saving Agency Settings and School Details: a department or division logo can be uploaded when none exists for that key (any school admin); replacing an existing one is allowed to the master user only (403 otherwise; the form shows the current logo read-only). Files stay on the `public` disk under `logos/`.
- The per-organization `department_logo_path` and `division_logo_path` columns stay; a migration copies existing values into `shared_logos` (first one wins; none is overwritten) so nothing is lost. District logo is unchanged (no logo of its own).
- Tests: shared division logo appears for another school of the same division; school without a logo prints the division logo; non-master cannot replace; master can; migration keeps the Lubas logo.

## Part 2: Google Drive connection

- Table `google_drive_connections` (`id`, `user_id` unique, `google_email`, `access_token` and `refresh_token` encrypted, `expires_at`, `root_folder_id`, `folder_ids` json for `Backup`, `Logo`, `Files`, `status` = `connected` | `needs_reconnect`, `connected_at`).
- OAuth web flow with `access_type=offline`, scope `https://www.googleapis.com/auth/drive.file` only, a `state` value stored in the session, redirect `https://procms.celsys.trade/google-drive/callback`. Credentials come from `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` and `GOOGLE_REDIRECT_URI` environment variables on Railway; they are never committed.
- After the first connection the app creates `ProcMS` with `Backup`, `Logo`, `Files` and stores the folder ids. If a folder was deleted in Drive the app recreates it on next use.
- A service `App\Services\GoogleDriveService` owns token refresh, folder creation, upload, listing metadata, delete, and marks the connection `needs_reconnect` when Google answers `invalid_grant` or 401 after refresh.
- Middleware `drive.connected` guards every route that uploads a repository file. When it fails: school users get the Connect page; a banner appears on every page while the status is `needs_reconnect`.
- The existing `/google-drive` page and its manual folder fields (`agency_settings.google_drive_*`) are replaced by the connect page; the columns stay unused for now.
- The master user connects a Drive the same way; no exemption from the connection for their own files.
- Tests: connect callback with a faked Google; state mismatch refused; upload blocked when not connected and when `needs_reconnect`; refresh failure marks `needs_reconnect`; tokens are not stored in plain text.

## Part 3: My Drive Files and backup

### My Drive Files (every user)

- Table `drive_files` (`id`, `user_id`, `name`, `drive_file_id`, `mime`, `size`, `created_at`). Listing and opening are filtered by the signed-in user's id; a user can never read another user's row, not even the master or a school admin.
- Page `/drive-files`: connect status, upload form (limit 20 MB, as the server allows), list (name, size, date, "Open in Drive" link, Delete). Delete removes the file in Drive and the row.
- Tests: another user (same school, school admin, master) gets 403/404 on someone's file; upload and delete go to the right Drive; blocked when not connected.

### System backup (master user only)

- Table `backup_runs` (`id`, `type` = `manual` | `automatic`, `status`, `size`, `drive_file_id`, `file_name`, `created_by`, `error`, timestamps) and `backup_downloads` (`id`, `backup_run_id`, `user_id`, `created_at`).
- A backup copies the SQLite file with `VACUUM INTO` into a temp file, uploads it to the master's `ProcMS/Backup`, then deletes the temp file. File name `procms-YYYY-MM-DD-HHMM.sqlite`.
- Page `/backup` (master only): **Backup now**, **Download** (streams a fresh copy and logs a row in `backup_downloads`), and a history table below with date, type, size, status, and who downloaded and when.
- Daily automatic run at 12:00 midnight Philippine time (Asia/Manila) through the Laravel scheduler (already started by `deploy/entrypoint.sh` with `schedule:work`); keeps the last 30 automatic backups and deletes older ones in Drive. A failed run is recorded with its error and shown on the master dashboard.
- Never given to a school user: the file contains every school's data.
- Tests: only the master can open, run and download; history records the download; retention keeps 30; failure is recorded.

## Build order (each step can be deployed on its own)

1. Shared logos.
2. Drive connection and upload gate.
3. My Drive Files.
4. System backup with history and the daily run.

## Setup the owner has to do once (not code)

Create a Google Cloud project, enable the Drive API, create an OAuth client of type Web application with the redirect above, set the consent screen to In production (Testing mode expires tokens after 7 days and allows 100 users; `drive.file` needs no Google review), and put the Client ID and Secret in the Railway variables. Never paste them in chat or commit them.

## Open points

None blocking. Moving a user to another school keeps their Drive and files unchanged, because the Drive belongs to the user.
