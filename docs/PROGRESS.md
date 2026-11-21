# Progress Tracker

Last updated: 2026-11-21. Item IDs and details are in [ROADMAP.md](ROADMAP.md).

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
| ❓ | T1: tests need Redis because of `throttleWithRedis()` | Q6 |
| ⬜ | T2: old tests call routes outside `/api/v1` | |
| ⬜ | T3: milestone factory writes the missing `icon_image_id` column | |
| ⬜ | T4: `HealthTest` has no `RefreshDatabase` | |
| ⬜ | B2 + T5: kid age is returned as a decimal | `app/Models/Kid.php:86-108` |
| ⬜ | T6: update old tests to the shared-family rules | |

Current numbers: 71 failing, 27 passing on PR #1. On `main`: 98 failing, 5 passing.

## Phase 3: mobile app

| Status | Item | Notes |
|---|---|---|
| ⬜ | I1: replace the server and members screens with admin invitations | Uses `is_admin` and `/v1/invitations`. |
| ⬜ | Register through `kinlore://invite/{token}` | Sends `invite_token`. |
| ⬜ | I2: sync `openapi.yaml` into `kinlore-mobile/docs` | |

## Phase 4: admin features

| Status | Item | Notes |
|---|---|---|
| ⬜ | List and remove users | |
| ⬜ | Hand the admin role to another user | |
| ⬜ | Public endpoint to check an invitation | |
| ⬜ | Cleanup of expired invitations | |
| ❓ | Record who uploaded each moment | Q5 |
| ❓ | S3: setup lock for the first admin | Q4 |
| ❓ | S4: confirm the HLS public endpoints may skip login | Q2 |
| ❓ | B4: family-wide or per-user counts in `/auth/me` | Q3 |
