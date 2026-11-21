# Kinlore Server Roadmap

Last updated: 2026-11-21. Progress per item is tracked in [PROGRESS.md](PROGRESS.md). Decisions the owner must make are in [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md).

## 1. Direction

One Kinlore deployment holds **one family**:

- **One admin.** The first user who registers, or the seeded user, is the admin (`users.is_admin`).
- **Invited users.** Everyone else joins with an invitation token that the admin creates.
- **Shared family data.** All users see all kids, moments, stories and media.
- **Write rules.** Only the admin or the user who added a kid can change or delete that kid and its content.

A family that wants a second space runs a second deployment. The mobile app connects to several deployments by URL (its `MultiServerContext`).

## 2. History: how the server became multi-tenant

There was no roadmap or design document before this file. This history comes from git.

| Date | Commit | Change |
|---|---|---|
| 2025-12-20 → 12-29 | `5671aad` … `e5f07e1` | Single deployment. Each user owns their own kids (`kids.user_id`). No sharing, roles or invitations. |
| 2025-12-29 | `a151002` | **The split.** Adds `servers`, `server_members` (owner, contributor, viewer), `invitations`, `kids.server_id`. A data migration creates a "{name}'s Family" server for every user. |
| 2025-12-29 | `5abf1e0` | Sync sets `server_id` on kids. |
| 2025-12-30 | `95fa097` | Every sign-up without an invitation creates a new server. This makes the API multi-tenant. |
| 2025-12-30 | `cd1610f` | `/auth/me` lists the user's servers. |
| 2025-12-30 | `37946a0` | Moment, media and sync access use server membership. The commit also adds security hardening. |
| 2026-01-08 → 01-09 | `d8aee80`, `d0717b7`, `dd578ee` | Request classes (including server and invitation ones), the `ApiResponse` refactor, and Redis rate limiting. |
| Uncommitted | (stash) | More multi-server work: public `GET /servers` discovery, `/servers/{id}/info\|health\|moments`, `ServerContextMiddleware`, per-server rate limits, audit logs, server metadata. Saved in the git stash "multi-server WIP before revert (2026-11-21)". It is not applied, because all of it depends on servers. |

## 3. Back to a single instance (PR #1, branch `revert-multi-tenant`)

### 3.1 Revert (commit `e905435`)

- Reverts `a151002`, `5abf1e0`, `95fa097` and `cd1610f`.
- Reverts only the server-membership parts of `37946a0` (MomentPolicy, SyncService).
- **Kept from `37946a0`:**
  - HLS file name checks against path traversal.
  - File type checks on uploads.
  - Login throttle (5 per minute).
  - `authorize('view')` on likes and comments.
  - No exception text in error responses.
- **Kept from `a151002`:** the SQLite compatibility fix in the video moment columns migration.

### 3.2 Admin and invitations (commit `b9871a1`)

- **Admin:** new column `users.is_admin`. The migration marks the oldest existing user as admin.
- **Registration:** the first user needs no token. Every later user needs an `invite_token` that has not expired and was not used. When the invitation is limited to an email address, the registering email must match.
- **Endpoints:** `GET/POST /v1/invitations` and `DELETE /v1/invitations/{id}`, admin only (the `can:admin` gate). The token is returned only once, when the invitation is created.
- **Shared reads:** the kid, moment, story and media lists and sync pull return the whole family's data.
- **Writes:** the update and destroy requests checked `view` permission. They now check `update` (admin or kid creator).
- **Sync:** sync push cannot overwrite or take over another user's kid.
- **Responses:** register, login and `/auth/me` return `is_admin`.
- **Docs:** `openapi.yaml` is updated.

## 4. Findings and bugs

The status of each item is in [PROGRESS.md](PROGRESS.md).

### Security

| ID | Finding | Status |
|---|---|---|
| S1 | Opening read access to the family would also have opened delete access. The update and destroy requests for moments, photos, media, stories and user milestones only checked `view` on the kid. | Fixed in PR #1 |
| S2 | Sync push "created" kids used `updateOrCreate` by ID. Once all users see all kid IDs, a user could overwrite and take over another user's kid. | Fixed in PR #1 |
| S3 | If two users register on an empty instance at the same moment, both could become admin. This is marked as a known shortcut in `AuthService::register`. | Open (low risk) |
| S4 | The HLS "public" endpoints (`/moments/{id}/file/hls`, `/media/{id}/file/hls`) do not require a login. They existed before PR #1. Check that this is intended. | Open |

### Bugs found while reviewing

| ID | Bug | Where | Status |
|---|---|---|---|
| B1 | Sync push lost the kid ID that the app sent. `id` is not fillable on `Kid`, so `updateOrCreate(['id' => …])` dropped it and generated a new UUID. This was on `main` before PR #1. | `SyncService::pushKidsChanges` | Fixed in PR #1 |
| B2 | Kid age is returned as a decimal, for example "3.50598914486 years, 6.02 months". Carbon 3 returns floats from `diffInYears` and `diffInMonths`, and the code does not round them. | `app/Models/Kid.php:86-108` | Open |
| B3 | The revert removed `email_verified_at`, `created_at` and `storage_bytes_available` from the `/auth/me` docs, but the API still returns them. | `openapi.yaml` | Fixed in PR #1 |
| B4 | The `/auth/me` field `moments_count` and the storage fields count only the current user's own kids. The family now shares kids, so this may not be what users expect. | `AuthService::getUserInfo` | Open (decision) |

### Test suite health

On `main`: 98 failing, 5 passing. On PR #1: 71 failing, 27 passing. All 71 remaining failures existed before PR #1.

| ID | Cause | Tests affected | Status |
|---|---|---|---|
| T1 | `throttleWithRedis()` in `bootstrap/app.php` needs a Redis host. Without one, every throttled route returns 500. | Auth, Sync, Health, Kid and others | Open |
| T2 | Old tests call `/api/kids`, `/api/videos`, `/api/milestones/custom` and `/api/sync/*`. Those routes moved under `/api/v1` or were removed in `c4ae150` (2025-12-28). | KidTest, VideoTest, MilestoneTest, SyncTest, MomentTest | Open |
| T3 | The milestone factory writes `icon_image_id`, but the table has no such column. | MilestoneTest | Open |
| T4 | `HealthTest` does not use `RefreshDatabase` ("no such table: users"). | HealthTest | Open |
| T5 | The Kid unit tests fail because of bug B2. | Unit/Models/KidTest | Open |
| T6 | Some old tests assume "users cannot see other users' kids". That rule changed on purpose. Update them when fixing T2. | KidTest, VideoTest | Open |

### Integration

| ID | Item | Status |
|---|---|---|
| I1 | The mobile app still calls the removed `/v1/servers…`, members and invitation endpoints. Its Members and invite screens must move to `/v1/invitations` and `is_admin`. | Open |
| I2 | The mobile copy of `docs/openapi.yaml` must be synced from `kinlore-api/openapi.yaml`. | Open |

## 5. Planned work

### Phase 1: merge the single instance (PR #1)

1. Review and merge PR #1.
2. Run the migrations on every deployment. They drop the server tables and `kids.server_id`.
3. Decide what happens to the multi-server stash: drop it, or keep it for reference.

### Phase 2: make the tests trustworthy

1. Run tests without Redis: use the normal throttle in the testing environment, or start Redis in CI (T1).
2. Move the old tests to `/api/v1` and to the shared-family rules (T2, T6).
3. Fix the milestone factory and `HealthTest` (T3, T4).
4. Fix the kid age calculation and its tests (B2, T5).

### Phase 3: mobile app

1. Replace the server and members screens with "Invite someone" (admin only), shown when `is_admin` is true (I1).
2. Register through the invite deep link `kinlore://invite/{token}` with `invite_token`.
3. Sync `openapi.yaml` into the mobile repo (I2).

### Phase 4: admin features (not built yet)

- List and remove users.
- Hand the admin role to another user.
- A public endpoint that checks an invitation before sign-up, so the app can show "invited by …".
- A scheduled cleanup of expired invitations.
- Record who uploaded each moment, so invited users can delete their own uploads.
- A setup lock for the first admin (S3).
- Decide B4 (family-wide or per-user counts in `/auth/me`).
