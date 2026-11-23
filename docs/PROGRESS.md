# Progress Tracker

Last updated: 2026-11-23 (branch `develop`). Item IDs and details are in [ROADMAP.md](ROADMAP.md).

Status: ✅ done · 🔄 in review · ⬜ open · ❓ needs a decision (see [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md))

## Phase 1: single instance (PR #1, merged to `main` on 2026-11-21)

| Status | Item | Notes |
|---|---|---|
| ✅ | Find when the server became multi-tenant | `a151002`, 2025-12-29. History is in ROADMAP §2. |
| ✅ | Look for an existing roadmap or design docs | None in `kinlore-server` or `kinlore-mobile`. |
| ✅ | Revert the multi-tenant commits | Commit `e905435`. Security parts of `37946a0` kept. |
| ✅ | First user becomes admin, invite-only sign-up | Commit `b9871a1`. |
| ✅ | Admin-only invitation endpoints | `GET/POST /v1/invitations`, `DELETE /v1/invitations/{id}` |
| ✅ | Shared family reads, admin or creator writes | Kid and moment policies, list queries, sync pull. |
| ✅ | S1: update and destroy requests check `update`, not `view` | |
| ✅ | S2: sync push cannot take over another user's kid | |
| ✅ | B1: sync push keeps the kid ID the app sends | |
| ✅ | B3: restore `/auth/me` fields in `openapi.yaml` | |
| ✅ | Seeded user becomes admin on an empty instance | `UserSeeder` |
| ✅ | Tests for the new rules | `tests/Feature/InstanceAccessTest.php`, 6 tests passing |
| ✅ | Merge PR #1 | |
| ⬜ | Run the migrations on every deployment | Drops the server tables and `kids.server_id`. Docker does this at each start. |
| ✅ | Keep or drop the stash "multi-server WIP before revert (2026-11-21)" | Q1: dropped on 2026-11-22 |

## Phase 2: test suite

| Status | Item | Notes |
|---|---|---|
| ✅ | T1: tests need Redis because of `throttleWithRedis()` | Removed; cache-based throttle everywhere. |
| ✅ | T2: old tests call routes outside `/api/v1` | |
| ✅ | T3: milestone factory writes the missing `icon_image_id` column | |
| ✅ | T4: `HealthTest` has no `RefreshDatabase` | |
| ✅ | B2 + T5: kid age is returned as a decimal | Whole numbers. |
| ✅ | T6: update old tests to the shared-family rules | |

Current numbers on `develop`: 166 passing, 0 failing (2026-11-22).

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

The agreement between server and app is [API_CONTRACT.md](API_CONTRACT.md). Checked by `php artisan test`, the mobile `npm test`, and `scripts/smoke-test.sh` (53/53 on Docker with PostgreSQL, 48/48 on bare metal with SQLite and local disk).

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
| ✅ | Unrouted old controllers (Photo, Story, UserMilestone, Video) and the unused `/v1/media` path | removed on 2026-11-22, about 4,300 lines; no table dropped |
| ⬜ | Storage URLs in moment payloads point at the internal SeaweedFS host | unused by the app |

## Phase 6: web setup, reverse proxy, simulator test (2026-11-21)

| Status | Item | Notes |
|---|---|---|
| ✅ | First admin is created on the web setup page (`/setup`), never in the app | optional `SETUP_TOKEN` |
| ✅ | App: server address with port, https first then http, sign-in only | invite sign-up stays in the app |
| ✅ | Bundled Caddy reverse proxy, trusted proxy headers | since 2026-11-22 Caddy is the web server inside the API image (Phase 8) |
| ✅ | Every feature tested on the iOS simulator against Docker (PostgreSQL, SeaweedFS, Caddy) | see below |

Bugs found in the simulator and fixed: uploads sent a capture date 1000× too large (all videos and EXIF-less photos failed); video durations 1000× too long; comment and description fields lost focus after one character; comment field hidden under the tab bar; hidden upload bar blocked the tab bar; failed uploads were silent and could not be cleared; Settings showed no server and stale storage; due dates in the past were accepted; export dropped files whose ids shared a prefix; splash screen stayed after a reload; keyboard covered Continue; many unlabeled controls for screen readers.

Open:
| Status | Item | Notes |
|---|---|---|
| ✅ | Factory Reset deleted every child of the family on the server | decided 2026-11-22: now 'Reset this phone', phone only, signs out; tested |
| ✅ | Child switcher once failed to open after saving a new child | cause: every sync rebuilt the navigation; fixed |
| ✅ | The first tab tap right after sign-in was sometimes lost | same cause; fixed |
| ⬜ | Comment field not visible to XCTest (deep view tree); check with VoiceOver on a device | |
| ✅ | Milestone cards do not announce the selected state | fixed |
| ✅ | Read-only description looks like an input box | fixed |

## Phase 7: audit fixes (2026-11-22)

A full audit of both repos compared the code with these documents. All findings are fixed. Checked live on Docker and the iOS simulator: a 350 MB video uploads, processes and downloads complete; the export downloads complete; sync runs in two pages with 2,100 memories; password reset works by command and in the app.

| Status | Item | Notes |
|---|---|---|
| ✅ | Large videos failed: upload, video job and original download read the whole file into memory | now streams; PHP keeps its 256 MB limit |
| ✅ | Docker: downloads over about 2 MB could be cut short (nginx could not write its temp files) | found while testing the fix above with a 350 MB video; nginx now sends straight to the client |
| ✅ | The app had no remote copy | private GitHub repo `kinlore-mobile` |
| ✅ | Android blocked plain `http://` servers in release builds | cleartext traffic allowed, like the iOS local-network rule |
| ✅ | No way to recover a forgotten password | `php artisan kinlore:reset-password <email>`; the admin sets a member's password in the app |
| ✅ | First sync loaded the whole library in one response | pages of 2000 memories (API_CONTRACT §9) |
| ✅ | A second queue worker could restart a running job | `retry_after` is now longer than the longest job |
| ✅ | Dead code on the server and in the app | removed; `minio:test` and `GET /v1/user` too |
| ✅ | Nothing ran the tests by itself | GitHub workflow in both repos; `npm test` in the app; a test keeps `openapi.yaml` equal to the routes |
| ✅ | Stale documents (ROADMAP, this file, OPEN_QUESTIONS), wrong comments, no app README | corrected |
| ✅ | SeaweedFS image was `latest` | pinned to 4.54 |
| ⬜ | Android build and a real-phone run | never done; iOS simulator only |
| ⬜ | Tokens never expire; the app has no screen to list or revoke sign-ins | the API has `/auth/sessions` |
| ⬜ | Expired invitations are never deleted | harmless: they are refused |

## Phase 8: Caddy in the image, host install guide (2026-11-22)

| Status | Item | Notes |
|---|---|---|
| ✅ | Caddy replaces nginx inside the API image | one web server for PHP and HTTPS |
| ✅ | HTTPS is optional: `KINLORE_DOMAIN` set gives built-in HTTPS, empty gives plain HTTP for your own reverse proxy | the separate Caddy container and certbot are removed |
| ✅ | Guide for a full install on a host without Docker | [BARE_METAL.md](BARE_METAL.md), files in `deploy/bare-metal/` (Caddy, nginx, systemd, PHP limits, settings, backup) |
| ✅ | The guide is tested | `scripts/test-bare-metal.sh`: clean Debian 13, Caddy and nginx, 300 MB file, 55/55 end-to-end checks through each |
| ✅ | API paths answer JSON without an `Accept` header | a browser or plain curl got a 500 page instead of 401 |
| ✅ | Workflow runs the API tests, the Docker stack in both modes and the host-install guide | `.github/workflows/tests.yml` |

Checked live on Docker in both modes: 50/50 end-to-end checks, `https` links behind a proxy, HTTP/2, the 350 MB upload and download.

## Phase 9: upgrade to Laravel 13 and PHP 8.5 (2026-11-23)

| Status | Item | Notes |
|---|---|---|
| ✅ | Laravel 12 → 13, PHPUnit 11 → 13, Intervention Image 3 → 4, all other packages to their newest | Laravel 13 config defaults: JSON sessions, no PHP objects from the cache |
| ✅ | PHP 8.4 → 8.5 in the image, the host guide and its proof | the workflow also tests PHP 8.6, allowed to fail until Laravel lists it |
| ✅ | SeaweedFS 4.54 → 4.55, Redis pinned to 8.10 | the old `redis:alpine` tag had already written 8.10 data; an older Redis cannot read it |
| ✅ | PostgreSQL stays on 18 | owner's choice; 19 came out on 2026-11-19. Tested move steps are in SELF_HOSTING |
| ✅ | Code on current conventions | enums for roles, types and statuses; Laravel 13 model, queue and console attributes; strict types; Pint with the Laravel preset |
| ✅ | Unused code removed | model scopes, accessors, relations, service methods, `AuthServiceProvider` |
| ✅ | Moment types `text` and `mixed` | removed with the migration rewrite in phase 10 |

Checked: 167 tests three times in a row; on Docker 50/50 end-to-end checks, and the 350 MB original and an old video still served after the storage upgrade; the host-install proof on PHP 8.5; JSON output compared before and after for every payload.

## Phase 10: fresh-install baseline (2026-11-23)

No install of the app or the server exists yet, so nothing has to be migrated.

| Status | Item | Notes |
|---|---|---|
| ✅ | 23 follow-up migrations folded into one | `2026_01_01_000001_create_kinlore_tables.php` creates the final tables; the users table has its roles and permissions from the start |
| ✅ | Unused tables dropped | `albums`, `album_moment`, `images`, `videos`, `stories`, `custom_milestones`, `user_milestones` |
| ✅ | Unused moment and kid columns dropped | `video_id`, `custom_milestone_id`, `story_text`, `tags`, `uploaded_from_device`, `uploaded_from_ip`, `synced_at` (written on every save, never read) |
| ✅ | Old request forms removed | `milestone_date` and `notes` aliases on moment update; `schemaVersion` and `migration` on sync pull |
| ✅ | Mobile: one local database schema, no legacy storage keys | see the mobile repo |
