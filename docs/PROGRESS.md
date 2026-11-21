# Progress Tracker

Last updated: 2026-11-21 (branch `develop`). Item IDs and details are in [ROADMAP.md](ROADMAP.md).

Status: ✅ done · 🔄 in review · ⬜ open · ❓ needs a decision (see [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md))

## Phase 1: single instance (PR #1)

| Status | Item | Notes |
|---|---|---|
| ✅ | Find when the server became multi-tenant | `a151002`, 2025-12-29. History is in ROADMAP §2. |
| ✅ | Look for an existing roadmap or design docs | None in `kinlore-server` or `kinlore-mobile`. |
| 🔄 | Revert the multi-tenant commits | Commit `e905435`. Security parts of `37946a0` kept. |
| 🔄 | First user becomes admin, invite-only sign-up | Commit `b9871a1`. |
| 🔄 | Admin-only invitation endpoints | `GET/POST /v1/invitations`, `DELETE /v1/invitations/{id}` |
| 🔄 | Shared family reads, admin or creator writes | Kid and moment policies, list queries, sync pull. |
| 🔄 | S1: update and destroy requests check `update`, not `view` | |
| 🔄 | S2: sync push cannot take over another user's kid | |
| 🔄 | B1: sync push keeps the kid ID the app sends | |
| 🔄 | B3: restore `/auth/me` fields in `openapi.yaml` | |
| 🔄 | Seeded user becomes admin on an empty instance | `UserSeeder` |
| 🔄 | Tests for the new rules | `tests/Feature/InstanceAccessTest.php`, 6 tests passing |
| ⬜ | Merge PR #1 | |
| ⬜ | Run the migrations on every deployment | Drops the server tables and `kids.server_id`. |
| ❓ | Keep or drop the stash "multi-server WIP before revert (2026-11-21)" | Q1 |

## Phase 2: test suite

| Status | Item | Notes |
|---|---|---|
| ✅ | T1: tests need Redis because of `throttleWithRedis()` | Removed; cache-based throttle everywhere. |
| ✅ | T2: old tests call routes outside `/api/v1` | |
| ✅ | T3: milestone factory writes the missing `icon_image_id` column | |
| ✅ | T4: `HealthTest` has no `RefreshDatabase` | |
| ✅ | B2 + T5: kid age is returned as a decimal | Whole numbers. |
| ✅ | T6: update old tests to the shared-family rules | |

Current numbers on `develop`: 148 passing, 0 failing.

## Phase 3: mobile app

| Status | Item | Notes |
|---|---|---|
| ✅ | I1: replace the server and members screens with admin invitations | Members, Invite, Member detail screens. |
| ✅ | Register through `kinlore://invite/{token}?server=…` | Also `https://<server>/invite/{token}` page. |
| ✅ | I2: sync `openapi.yaml` into `kinlore-mobile/docs` | OpenAPI 3.1, lint clean. |

## Phase 4: admin features

| Status | Item | Notes |
|---|---|---|
| ✅ | List and remove users | Kids move to the admin; moments stay. |
| ✅ | Hand the admin role to another user | |
| ✅ | Public endpoint to check an invitation | |
| ⬜ | Cleanup of expired invitations | |
| ✅ | Record who uploaded each moment | Q5 |
| ✅ | S3: setup lock for the first admin | Q4 |
| ✅ | S4: HLS needs signed, expiring links | Q2 |
| ✅ | B4: family-wide counts in `/auth/me` | Q3 |

## Phase 5: contract v2 and Backthen features (branch `develop`)

The agreement between server and app is [API_CONTRACT.md](API_CONTRACT.md). Checked by `php artisan test` (148 tests), the mobile type check, and `scripts/smoke-test.sh` (53/53 on Docker with PostgreSQL, 48/48 on bare metal with SQLite and local disk).

| Status | Item | Notes |
|---|---|---|
| ✅ | Roles admin / partner / family, permission switches | add content, like, comment, download |
| ✅ | Due dates (expecting), pregnancy weeks in the app | |
| ✅ | Growth entries (height, weight, head) | timeline item and history |
| ✅ | Look-back: on this day, then and now | |
| ✅ | Timeline filters by type and "liked by me" | date range filter not in the app yet |
| ✅ | Full ZIP export for the admin | |
| ✅ | Videos processed in the background | upload returns at once |
| ✅ | Sync v2 (ms times, deletes, rejected changes, upserts) | |
| ✅ | Docker: HTTP default, TLS when a certificate exists, Postgres 18, required secrets, internal ports on localhost, trusted proxies | |
| ✅ | Bare metal: SQLite and local disk media, no Redis | `docs/SELF_HOSTING.md` |
| ⬜ | Push and email notifications, daily digest | needs owner's mail or push credentials |
| ⬜ | Tag one memory to several children; per-child access per member | |
| ⬜ | Web app for desktop upload and viewing | |
| ⬜ | Look-back uses UTC month and day; 29 February is skipped | minor |
| ⬜ | Old WatermelonDB app data is not migrated | unsynced local items and the old upload queue are lost on upgrade; a full pull restores server data |
| ⬜ | Unrouted old controllers (Photo, Story, UserMilestone, Video) and in-request video processing in `MediaService::upload` | dead code, remove |
| ⬜ | Storage URLs in moment payloads point at the internal SeaweedFS host | unused by the app |

## Phase 6: web setup, reverse proxy, simulator test (2026-11-21)

| Status | Item | Notes |
|---|---|---|
| ✅ | First admin is created on the web setup page (`/setup`), never in the app | optional `SETUP_TOKEN` |
| ✅ | App: server address with port, https first then http, sign-in only | invite sign-up stays in the app |
| ✅ | Bundled Caddy reverse proxy (`--profile proxy`), trusted proxy headers | nginx and Traefik examples in SELF_HOSTING |
| ✅ | Every feature tested on the iOS simulator against Docker (PostgreSQL, SeaweedFS, Caddy) | see below |

Bugs found in the simulator and fixed: uploads sent a capture date 1000× too large (all videos and EXIF-less photos failed); video durations 1000× too long; comment and description fields lost focus after one character; comment field hidden under the tab bar; hidden upload bar blocked the tab bar; failed uploads were silent and could not be cleared; Settings showed no server and stale storage; due dates in the past were accepted; export dropped files whose ids shared a prefix; splash screen stayed after a reload; keyboard covered Continue; many unlabeled controls for screen readers. Factory Reset is now admin-only.

Open:
| Status | Item | Notes |
|---|---|---|
| ❓ | Keep Factory Reset at all? It soft-deletes every child of the family on the server | owner decision |
| ⬜ | Child switcher once failed to open after saving a new child (not reproduced) | watch |
| ⬜ | The first tab tap right after sign-in was sometimes lost | watch |
| ⬜ | Comment field not visible to XCTest (deep view tree); check with VoiceOver on a device | |
| ⬜ | Milestone cards do not announce the selected state | minor |
| ⬜ | Read-only description looks like an input box | minor |
