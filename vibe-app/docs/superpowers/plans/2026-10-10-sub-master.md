# Sub-master Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `sub_master` role that works like the master across every school with an access checklist the master edits, by default everything except deleting, managed in the Master User tab.

**Architecture:** A `users.access` JSON column and central questions on `User` (`isMaster`, `seesAllSchools`, `hasAccess`, extended `hasPermission`) replace the 54 `role === 'master_user'` checks. One web middleware enforces the three "delete-like" switches. A `SubMasterController` and a Blade partial manage Sub-masters inside `/user-management?tab=master-user`.

**Tech Stack:** Laravel 13, PHP 8.4, SQLite, PHPUnit 12 (`php artisan test --compact`), Blade, Pint (`vendor/bin/pint --dirty --format agent`).

**Spec:** `vibe-app/docs/superpowers/specs/2026-10-10-sub-master-design.md`. Read it with this plan.

## Global Constraints

- No new Composer package.
- Role value `sub_master`; it is **not** added to `User::ROLES` and a school user can never be given it (`SchoolSettingsController` role validation keeps using `User::ROLES`).
- Checklist keys (fixed): work areas `planning`, `budget`, `procurement`, `suppliers`, `accounting`, `cash`, `liquidation`, `school_settings`; management areas `schools`, `subscriptions`, `transfers`, `users`, `backup`; switches `delete`, `deactivate_user`, `close_budget`, `edit_own_name`.
- Defaults for a new Sub-master: the 13 areas on (Backup included), the 4 switches off. A null, empty or unknown-key `access` means no access, never everything.
- Refusal message, exact: `Your account's access does not allow this action.` (HTTP 403, and written to the audit log as `sub_master_action_refused`).
- Only the real master (`isMaster()`) creates, edits, resets or deactivates Sub-masters and changes a checklist. Sub-masters are deactivated, never deleted.
- Temporary and new passwords: at least 12 characters. E-mail and username unique (same rules as `MasterUserController`).
- Personal Drive routes `google-drive.disconnect` and `drive-files.destroy` are never blocked by the delete guard.
- The nightly automatic backup and `CreateMasterUser`/`ImportSip` stay with the real master (`role = master_user`).
- Tests use `RefreshDatabase`; GD is missing, so uploads use `UploadedFile::fake()->create`. Multi-line edits to existing files go through a Python or PowerShell script file (heredocs with apostrophes break in this shell).
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Commit steps run only when the user has asked for commits; otherwise skip them. Deploy once, at the end, after asking the user.

## Rulings made while planning

- Work areas map to permission **families** because `config/permissions.php` uses families: `planning` = `planning.*`, `aip.*`; `budget` = `budget.*`; `procurement` = `procurement.*`, `inspection.*`; `suppliers` = `supplier.*`; `accounting` = `accounting.*`; `cash` = `cash.*`; `liquidation` = `liquidation.*`; `school_settings` = `organization.*`. `dashboard.view` and `reports.view` are always on. (The spec's shorter list named only the permissions the routes check today.)
- The Users tab of `/user-management` needs `hasAccess('users')`; the Master User tab (own account) is open to every master and Sub-master.
- Real route names found with `php artisan route:list --json`: DELETE routes `chart-of-accounts.destroy`, `aip.destroy`, `aip.kras.destroy`, `budget.allocation.destroy`, `planning.app.items.destroy`, `planning.ppmp.destroy`, `planning.sip.activities.destroy`, `planning.sip.destroy`, `units.destroy`, `school-settings.employees.destroy` (plus the two personal Drive ones); switches `budget.allocation.close` (`close_budget`) and `school-management.users.deactivate` (`deactivate_user`).

## Review Focus

- A Sub-master with `access` null, `[]`, or only unknown keys has no access; it never behaves as a master.
- A posted checklist with an unknown key, a non-array, or a switch outside the list is refused and nothing is saved.
- A Sub-master posting extra fields (`role`, `access`, `status`) to their own account form changes none of them.
- Turning a switch off takes effect on the very next request of an open session (nothing is cached).
- `POST` with `_method=DELETE` (method spoofing) is blocked like a real DELETE; a school user cannot be created or updated with role `sub_master`.

---

### Task 1: Role, column and the central questions

**Files:**
- Create: `database/migrations/2026_10_10_090001_add_access_to_users_table.php`, `app/Support/SubMasterAccess.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `config/permissions.php`
- Test: `tests/Feature/SubMasterAccessTest.php`

**Interfaces:**
- Produces `App\Support\SubMasterAccess`: constants `WORK_AREAS` (key => label), `MANAGEMENT_AREAS`, `SWITCHES`; `keys(): array`; `defaults(): array` (the 13 areas); `families(string $area): array` (the permission families above); `sanitize(mixed $input): array` (list of known keys, unique, reindexed; anything else dropped).
- Produces on `User`: `const MASTER_ROLES = ['master_user', 'sub_master']`; cast `access` => array; `isMaster(): bool`; `isSubMaster(): bool`; `seesAllSchools(): bool`; `hasAccess(string $key): bool`; `hasPermission()` extended; factory state `UserFactory::subMaster(?array $access = null)` (null = defaults).

- [ ] **Step 1: Write failing tests** in `SubMasterAccessTest`: `test_a_master_has_every_access_and_sees_all_schools`; `test_a_sub_master_has_exactly_the_checked_areas`; `test_null_empty_or_unknown_access_means_no_access` (`null`, `[]`, `['bogus']` → `hasAccess('schools')` false, `seesAllSchools()` still true, `hasPermission('budget.view')` false); `test_sanitize_drops_unknown_and_duplicate_keys`; `test_defaults_are_the_13_areas_without_the_switches`; `test_work_areas_grant_their_permission_families` (`accounting` on → `accounting.approve` true, `cash.pay` false; `dashboard.view` and `reports.view` always true); `test_other_roles_have_no_master_access` (school_admin: `isMaster`, `seesAllSchools`, `hasAccess('backup')` all false).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterAccessTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the migration (`users.access` json nullable), `SubMasterAccess`, the `User` methods and cast (extract the existing wildcard matching in `hasPermission` into a private `permissionMatches($granted, $permission)` and reuse it for families), the factory state, and a `sub_master` comment in `config/permissions.php` pointing to `SubMasterAccess`.
- [ ] **Step 4: Run the filter and** `php artisan test --compact`. Expected: PASS, suite unchanged.
- [ ] **Step 5: Commit** `feat: sub_master role, access column and central access questions`.

### Task 2: Visibility and master-identity checks

**Files:**
- Modify (visibility, use `seesAllSchools()`): `app/Models/Concerns/BelongsToOrganization.php` (lines 20, 63), `AipController.php` (33, 266), `AllotmentRegistryController.php` (21), `BudgetAllocationController.php` (43, 66), `BudgetController.php` (17, 72), `PlanningController.php` (554), `FinanceController.php` (27), `HomeController.php` (`isMasterUser()` at 49), `UnitController.php` (17), `SchoolSettingsController.php` (22, 135, 146), `SchoolManagementService.php` (35), `StationTransferService.php` (30), `EnsureAccountActive.php`, `EnsureStationConfirmed.php`, `EnsureSubscriptionAllowsWrites.php`, `AuthController.php` (59), and the `isMasterUser` variables in `budget.blade.php`, `layouts/budget.blade.php`, `home.blade.php`, `liquidation.blade.php`, `partials/procurement/navigation.blade.php`.
- Modify (identity, exclude both master roles with `User::MASTER_ROLES`): `SchoolManagementController.php` (32, 58), `StationTransferController.php` (37 and the `abort_if` at 17), `SchoolTakeoverService.php` (22, 58), `StationTransferService.php` (269).
- Leave on `role = master_user`: `CreateMasterUser`, `ImportSip`, `DatabaseBackupService` (26, 82), `MasterUserController` (own-account rules change in Task 6).
- Test: `tests/Feature/SubMasterVisibilityTest.php`

**Interfaces:**
- Consumes: `User::seesAllSchools()`, `User::MASTER_ROLES` (Task 1).

- [ ] **Step 1: Write failing tests**: `test_a_sub_master_sees_every_schools_aips_budget_and_planning` (two schools, each with an AIP and a budget allocation; the Sub-master list pages show both, an ordinary school user sees only their own); `test_ordinary_users_stay_isolated` (re-asserts school A cannot read school B through the same pages); `test_school_user_counts_exclude_both_master_roles` (a Sub-master with a null school does not make a school "occupied" in School Management, takeover or transfer lists); `test_a_sub_master_without_a_school_signs_in_and_passes_the_school_subscription_and_station_checks` (login then `get(route('home'))` is 200); `test_a_sub_master_can_write_without_a_subscription`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterVisibilityTest`. Expected: FAIL.
- [ ] **Step 3: Replace** the listed checks, file by file, with the interface calls (a mechanical swap: `$user->role === 'master_user'` becomes `$user->seesAllSchools()` for visibility; `->where('role', '!=', 'master_user')` becomes `->whereNotIn('role', User::MASTER_ROLES)`). Finish with `grep -rn "master_user" app routes resources` and confirm only the identity entries listed above, `MasterUserController`, the management gates of Task 3, and role strings in seeders remain.
- [ ] **Step 4: Run** the filter and the whole suite. Expected: PASS (271 existing tests plus the new ones).
- [ ] **Step 5: Commit** `refactor: visibility and master identity use seesAllSchools and MASTER_ROLES`.

### Task 3: Management gates and menus

**Files:**
- Modify: `BackupController.php` (64), `HomeController.php` (storeSchool ~220, `userManagement` ~1803, `subscriptions` ~1819, `approveSchoolRegistration` ~2089, the master-only gate ~1780), `SchoolManagementController.php` (108), `SchoolTakeoverController.php` (17, 25), `StationTransferController.php` (103, 116, 125), `SchoolManagementService.php` (82), `SchoolTakeoverService.php` (105), `StationTransferService.php` (285), menus in `layouts/procurement.blade.php` (67, 71), `partials/procurement/navigation.blade.php`, `google-drive.blade.php` (the Backup link), `home.blade.php`.
- Test: `tests/Feature/SubMasterGatesTest.php`

**Interfaces:**
- Consumes: `User::hasAccess()` and the factory state `subMaster()` (Task 1).
- Gate map: `backup` → `backup.*` routes; `schools` → School Management pages, add school, pre-registration approval, takeover; `subscriptions` → `/subscriptions`; `transfers` → master transfer decisions (`StationTransferController` 103, 116, 125); `users` → the Users tab of `/user-management`.

- [ ] **Step 1: Write failing tests**: a data-driven `test_each_management_area_opens_only_when_checked` that, for every key above, takes a representative route (resolve names with `php artisan route:list --name=...`), asserts 200 or redirect for a Sub-master with the key and 403 without it (all other keys on), and 200 for the master; `test_menus_show_only_the_checked_areas` (the nav of a Sub-master without `subscriptions` has no Subscriptions link); `test_a_school_admin_stays_forbidden_everywhere` (including a forged `role` field in the request).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterGatesTest`. Expected: FAIL.
- [ ] **Step 3: Replace** each gate with `abort_unless($user->hasAccess('<key>'), 403)` (master stays true) and update the menu conditions to `hasAccess`/`seesAllSchools`.
- [ ] **Step 4: Run** the filter and the whole suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: management areas follow the access checklist`.

### Task 4: The delete guard

**Files:**
- Create: `app/Http/Middleware/EnforceSubMasterLimits.php`
- Modify: `bootstrap/app.php` (append to the `web` group)
- Test: `tests/Feature/SubMasterDeleteGuardTest.php`

**Interfaces:**
- Produces: middleware class `EnforceSubMasterLimits::handle(Request, Closure): Response`. For a Sub-master it aborts 403 with the exact refusal message and writes `AuditLog` action `sub_master_action_refused` (metadata: route name, method) when: the request method is `DELETE` (including spoofed `_method`) and the route is not `google-drive.disconnect` or `drive-files.destroy` and `hasAccess('delete')` is false; or the route is `school-management.users.deactivate` without `deactivate_user`; or `budget.allocation.close` without `close_budget`. Other users and the master pass through untouched.

- [ ] **Step 1: Write failing tests**: `test_each_delete_route_is_refused_without_the_delete_switch_and_allowed_with_it` (loop over the ten route names in "Rulings"; assert 403 and an audit row without the switch; assert not-403 with it); `test_user_deactivation_and_budget_close_follow_their_own_switches`; `test_the_personal_drive_routes_are_never_blocked`; `test_method_spoofing_is_blocked_too` (`post` with `_method=DELETE`); `test_turning_a_switch_off_applies_on_the_next_request` (same user object reloaded: on, then off, then 403); `test_the_master_and_school_admin_are_not_affected`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterDeleteGuardTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the middleware (read `$request->route()?->getName()` and `$request->method()`; reload nothing from cache) and register it after `ShowDriveReconnectBanner`.
- [ ] **Step 4: Run** the filter and the whole suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: delete guard for sub-masters`.

### Task 5: Managing Sub-masters in the tab

**Files:**
- Create: `app/Http/Controllers/SubMasterController.php`, `resources/views/partials/master-user-subs.blade.php`
- Modify: `routes/web.php`, `resources/views/partials/master-user-tab.blade.php` (include the new partial for the master only), `HomeController::userManagement` (pass the Sub-master list)
- Test: `tests/Feature/SubMasterManagementTest.php`

**Interfaces:**
- Consumes: `SubMasterAccess::sanitize()/defaults()/keys()`, `User::isMaster()`.
- Produces routes (master only): `master-user.sub-masters.store` POST `/user-management/sub-masters`, `master-user.sub-masters.update` PUT `/user-management/sub-masters/{user}`, `master-user.sub-masters.password` PUT `.../{user}/password`, `master-user.sub-masters.status` PUT `.../{user}/status`. `{user}` must be a `sub_master` (otherwise 404). Fields: `name`, `username`, `email`, `phone`, `position`, `password` + `password_confirmation` (store, 12+), `access` (array of keys). Audit actions: `sub_master_created`, `sub_master_updated` (metadata old and new `access`), `sub_master_password_reset`, `sub_master_status_changed`.

- [ ] **Step 1: Write failing tests**: `test_the_master_adds_a_sub_master_with_the_default_checklist` (no `access` posted → defaults; role `sub_master`, status active, school null, password hashed); `test_the_checklist_is_saved_and_changes_are_audited`; `test_unknown_keys_or_a_non_array_checklist_are_refused` (Review Focus 2, nothing saved); `test_unique_email_and_username_and_the_12_character_password`; `test_the_master_resets_a_sub_masters_password_and_the_old_one_stops_working`; `test_the_master_deactivates_and_reactivates_a_sub_master`; `test_a_sub_master_or_school_admin_gets_403_on_all_four_routes`; `test_the_tab_lists_sub_masters_with_an_access_summary` (e.g. `11 of 13`); `test_a_master_cannot_use_these_routes_on_a_non_sub_master` (404).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterManagementTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the controller (validation messages in plain English like `MasterUserController`; `role`, `school_id`, `organization_id` are set by the server, never read from the request) and the partial (table, add form with the three checkbox groups and Select all / Clear per group, edit and reset forms using the show/hide password inputs; wording in plain English).
- [ ] **Step 4: Run** the filter and the whole suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: the master manages sub-masters in the Master User tab`.

### Task 6: The Sub-master's own account and session

**Files:**
- Modify: `app/Http/Controllers/MasterUserController.php`, `partials/master-user-tab.blade.php`, `HomeController::userManagement` (the tab opens for any master or Sub-master; the Users tab needs `hasAccess('users')`), `app/Http/Middleware/EnsureAccountActive.php`, `SchoolSettingsController.php` (role validation unchanged; add a test only)
- Test: `tests/Feature/SubMasterAccountTest.php`

**Interfaces:**
- `MasterUserController::update/password` accept `isMaster() || isSubMaster()` for the signed-in user's own account. For a Sub-master, `name` and `username` are kept as they are unless `hasAccess('edit_own_name')`; `role`, `status`, `access` are never read.

- [ ] **Step 1: Write failing tests**: `test_a_sub_master_edits_their_own_email_phone_position_and_password`; `test_name_and_username_are_ignored_unless_edit_own_name_is_on` (both outcomes); `test_extra_role_access_and_status_fields_change_nothing` (Review Focus 3); `test_the_tab_shows_a_sub_master_only_my_account` (no Sub-masters section); `test_a_sub_master_without_users_access_still_opens_the_master_user_tab_but_not_the_users_tab`; `test_a_deactivated_sub_master_cannot_sign_in_and_an_open_session_ends` (status `inactive` → next request redirects to login); `test_a_school_user_cannot_be_given_the_sub_master_role` (create and update through School Settings with `role=sub_master` fail validation; Review Focus 5).
- [ ] **Step 2: Run** `php artisan test --compact --filter=SubMasterAccountTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the controller changes, the tab conditions, and extend `EnsureAccountActive` so a Sub-master with a non-active status is logged out like a school user (the master stays exempt).
- [ ] **Step 4: Run** the filter and the whole suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: sub-master own account rules and deactivation`.

### Task 7: Documentation and the final check

**Files:**
- Modify: `docs/specs.md` (new section 18 "Sub-master"), `CLAUDE.md` (one status line)
- Test: the whole suite

- [ ] **Step 1: Write section 18** in `docs/specs.md`: the role, the checklist keys and defaults, the permission families, what each management area opens, the three delete-like switches and `edit_own_name`, the guard and its exempt routes, the audit actions, Sub-masters are deactivated not deleted, and the Backup-in-own-Drive note; add the status line to `CLAUDE.md`.
- [ ] **Step 2: Run** `vendor/bin/pint --dirty --format agent` and `php artisan test --compact`. Expected: no style changes left, all tests pass (271 existing plus the new ones).
- [ ] **Step 3: Check the replacements are complete:** `grep -rn "role === 'master_user'\|role !== 'master_user'" app routes resources` shows only the identity entries named in Task 2 and `MasterUserController`.
- [ ] **Step 4: Final whole-branch review** by a fresh reviewer on the most capable model (the plan's Review Focus verbatim), then one fix pass for Critical and Important findings, each with a test that failed first.
- [ ] **Step 5: Ask the user before committing and deploying** (`railway up --detach --service procurems` from `vibe-app`, `MSYS_NO_PATHCONV=1` in Git Bash); the migration runs by itself on deploy.
