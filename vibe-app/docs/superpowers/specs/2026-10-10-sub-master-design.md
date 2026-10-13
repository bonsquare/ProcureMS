# Sub-master account with a customizable access checklist: design

Date: 2026-10-10. Status: design approved in chat, written spec awaiting review.

## Goal

A **Sub-master** is an account that works like the master user across every school, with the access the master chooses on a checklist. By default it has everything except deleting. Only the real master creates and manages Sub-masters, in the Master User tab.

## Decisions taken (and who took them)

- Access is a per-account checklist: 8 work areas, 5 management areas, 4 switches (all listed below).
- Defaults for a new Sub-master: all 13 areas on (**including Backup**), the 4 switches off.
- "Delete" is split into three switches: `delete`, `deactivate_user`, `close_budget` (all off by default). A fourth switch `edit_own_name` lets the account change its own full name and username.
- A Sub-master with `backup` saves backups to **their own** Google Drive; the file contains every school's data, every download is logged, and the nightly automatic backup stays with the real master's Drive.
- Sub-masters are never deleted, only deactivated (keeps the audit history).
- Never grantable: creating, editing or deactivating a master or another Sub-master, the checklist itself, deleting a master account.
- Approach A: a new role `sub_master`, a `users.access` JSON column, and central questions on `User` that replace the 54 `role === 'master_user'` checks. No new Composer package.

## Data

- Migration: `users.access` JSON nullable (null for everyone except Sub-masters). No data change for existing users.
- Role value `sub_master`. It is **not** added to `User::ROLES` (the roles a school can assign).
- Checklist keys, fixed list in `config/permissions.php` (`sub_master`):
  - Work areas and the permissions each grants: `planning` = `planning.manage`; `budget` = `budget.manage`; `procurement` = `procurement.create`, `procurement.edit`; `suppliers` = `supplier.manage`; `accounting` = `accounting.manage`, `accounting.approve`; `cash` = `cash.pay`; `liquidation` = `liquidation.create`, `liquidation.approve`; `school_settings` = `organization.settings`.
  - Management areas: `schools` (School Management, pre-registration approval, takeover, add school), `subscriptions`, `transfers` (master decisions on transfer requests), `users` (User Management list), `backup`.
  - Switches: `delete`, `deactivate_user`, `close_budget`, `edit_own_name`.

## Central questions on `User`

- `isMaster()`: role is `master_user` only.
- `isSubMaster()`: role is `sub_master`.
- `seesAllSchools()`: master or Sub-master (replaces the visibility, data-scope and "master is exempt" checks).
- `hasAccess(string $key)`: true for the master; for a Sub-master, the key is in `access`; false for everyone else. Unknown keys are false.
- `hasPermission($permission)` also returns true for a Sub-master when an enabled work area grants it.

## Enforcement (replaces the 54 checks)

1. **Visibility** (about 25: `BelongsToOrganization`, AIP, budget, planning, allotment, finance, School Settings, `SchoolManagementService`, `StationTransferService`, `EnsureAccountActive`, `EnsureStationConfirmed`, `EnsureSubscriptionAllowsWrites`, login school-status check, the `isMasterUser` view variables): use `seesAllSchools()`.
2. **Management gates** (about 15: backup, add school, approve pre-registration, takeover, School Management, Subscriptions, User Management, transfer decisions): `hasAccess('schools'|'subscriptions'|'transfers'|'users'|'backup')`.
3. **Master identity** (about 10: counts and filters that exclude masters from a school's users, `CreateMasterUser`, `ImportSip`, automatic backup): exclude both `master_user` and `sub_master`; creating and automatic backup stay with the real master.
4. **Views** (about 8): menus and links follow `hasAccess` and `seesAllSchools`.
5. **Delete guard**: one middleware in the `web` group. For a Sub-master it refuses (403, "Your account's access does not allow this action.") every `DELETE` request, the user-deactivate action and the budget-close action, unless `delete`, `deactivate_user` or `close_budget` is on. Never refused: `google-drive.disconnect` and `drive-files.destroy` (personal). Refusals are written to the audit log.

## Master User tab

- Master sees two sections: **My account** (details and password, as built) and **Sub-masters** (table with name, username, e-mail, status, access summary such as "11 of 13", actions Edit, Reset password, Deactivate or Reactivate).
- **Add Sub-master**: full name, username, e-mail, phone, position, temporary password (12 characters or more, with the show/hide eye), and the checklist with Select all / Clear per group.
- Routes (master only, 403 for anyone else, including a Sub-master): create, update (details and checklist), password reset (no current password needed, 12 characters or more), status. Names under `master-user.sub-masters.*`.
- Validation: unique e-mail and username (same rules as the master's own account), checklist keys must be in the fixed list.
- A Sub-master sees only **My account**. Full name and username are read-only unless `edit_own_name` is on; the server ignores them otherwise.
- Audit log: create, update (old and new checklist), password reset, status change, refused deletes.

## Login

A Sub-master signs in with e-mail or username like anyone else. Having no school, they are exempt from the school-status, subscription and station-transfer checks, as the master is. A deactivated Sub-master cannot sign in, and an existing session ends on its next request.

## Tests (each fails first)

- Every checklist key: on gives access, off gives 403 (management areas and work areas).
- Delete guard: each DELETE route, user deactivation and budget close refused without the switch and allowed with it; the personal Drive actions always allowed; refusals audited.
- A Sub-master cannot create, edit, reset or deactivate a master or Sub-master and cannot change their own checklist; the master can.
- School-user counts (School Management, takeover, transfer) exclude both master roles.
- Tenant isolation: ordinary users still see only their own school.
- Sub-master login without a school; deactivated Sub-master refused and a live session ended.
- `edit_own_name` off: name and username in a posted form are ignored; on: saved.
- All existing tests (271) keep passing after the 54 replacements.

## Build order (one plan, deployed once at the end)

1. Migration, role, the central questions on `User`, config.
2. Replace visibility and master-identity checks (types 1 and 3).
3. Replace management gates and menus (types 2 and 4).
4. Delete guard middleware.
5. Sub-master management in the tab (create, edit, checklist).
6. Password reset, status, and the Sub-master's own account rules.
7. Documentation in `docs/specs.md` and the final whole-branch review.

## Risks

- Replacing the 54 checks is the largest part. The 271 existing tests and the final review guard it, but it is where a miss could hide.
- A Sub-master with `backup` can obtain a copy of every school's data. Agreed by the owner; every download is logged.

## Open points

None blocking.
