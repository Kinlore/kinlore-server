# Open Questions

Last updated: 2026-11-22. Every question below is answered; nothing waits for a decision. These items need a decision from the project owner. Write the answer under each question. Then move the item to "Decided" and update [PROGRESS.md](PROGRESS.md).

IDs match [ROADMAP.md](ROADMAP.md).

## Q1. Keep or drop the multi-server stash?

**Context:** before the revert, the uncommitted multi-server work was saved in the git stash "multi-server WIP before revert (2026-11-21)". It contains:
- Public `GET /servers` discovery.
- The `/servers/{id}/info|health|moments|milestones` routes.
- `ServerContextMiddleware` and per-server rate limits.
- Audit logs and server metadata columns.

All of it depends on the server tables that PR #1 removes.

**Options:**
- **A. Drop it.** The stash is deleted. This cannot be undone.
- **B. Keep it as a reference branch** (`archive/multi-server-wip`), pushed to GitHub. Nothing is lost.
- **C. Leave it as a local stash.** Nothing is lost, but it exists only on this machine.

**Recommendation:** B. It keeps the work without cluttering `main`.

**Answer:** A, decided 2026-11-22. The stash is dropped.

## Q2 (S4). Should HLS video streaming stay open without a login?

**Context (as it was on 2026-11-21):** these routes had no authentication:
- `/v1/moments/{id}/file/hls` and `/v1/moments/{id}/file/{name}.m3u8|.ts`
- the same two under `/v1/media/{id}` (that path was removed on 2026-11-22)

The comment in `routes/api.php` gives the reason: phone video players cannot send login headers with HLS requests. Anyone who has a moment or media UUID can stream that video. UUIDs are hard to guess, but they can leak, for example from logs or shared links.

**Options:**
- **A. Keep as is.** It is simple. The UUID is the only protection.
- **B. Signed, expiring URLs.** The API returns HLS links with a signature that expires (Laravel signed routes). Players need no headers. This is a moderate amount of work on both the API and the app.
- **C. A token in the query string.** A short-lived token as `?token=`. This works the same way as B, but you manage the tokens yourself.

**Recommendation:** B, before any instance is exposed to the internet.

**Answer:** B, decided 2026-11-21. `GET /v1/moments/{id}/playback` returns a signed link valid 6 hours; every playlist line is signed too. Unsigned requests get 403.

## Q3 (B4). Should the `/auth/me` counts cover the family or only the user?

**Context:** the family now shares all kids. But `/auth/me` still returns `kids_count`, `moments_count` and storage use for the kids the current user added. An invited user who added no kids sees 0.

**Options:**
- **A. Family-wide.** Count all kids, moments and storage on the instance.
- **B. Per user.** Keep the current behavior and document it.
- **C. Both.** Add family totals next to the per-user numbers.

**Recommendation:** A. It matches "one family per instance".

**Answer:** A, decided 2026-11-21. `/auth/me` counts the whole family; `my_moments_count` is added for the user's own uploads.

## Q4 (S3). Should first-admin setup be locked?

**Context:** the first user to register becomes the admin. If two people register on an empty instance at the same moment, both could become admin. The risk is only real when a new instance is reachable on the internet before its owner registers.

**Options:**
- **A. Accept the risk.** It is documented as a known shortcut in `AuthService::register`.
- **B. Setup secret.** The first registration must also send `SETUP_TOKEN` from `.env`.
- **C. Seeded admin only.** Turn off open first registration. The admin comes only from `UserSeeder` (`SEED_USER_EMAIL`).

**Recommendation:** C if you always deploy with a seeded user, because it removes the race and adds no new code path. Otherwise B.

**Answer:** B plus a lock, decided 2026-11-21, then moved to the web on the same day: the first admin is created only on the web page `/setup` (optional `SETUP_TOKEN`, one setup at a time under a lock). `POST /auth/register` is invite-only. A seeded admin still works.

## Q5. Who can delete a moment?

**Context:** moments do not record who uploaded them. Today only the admin or the kid's creator can edit or delete a moment. A grandparent who uploads a photo of someone else's kid cannot delete their own photo.

**Options:**
- **A. Keep the current rule.**
- **B. Record the uploader** (`moments.user_id`). The uploader, the kid's creator and the admin can then delete. This needs a migration and policy changes.

**Recommendation:** B.

**Answer:** B, decided 2026-11-21. `moments.user_id` records the uploader (backfilled from the kid's creator). Uploader, kid creator, partner and admin can edit or delete.

## Q6 (T1). How should tests deal with the Redis throttle?

**Context:** `bootstrap/app.php` calls `throttleWithRedis()`, so every throttled route needs Redis. Without Redis these routes return 500. This causes many of the 71 failing tests.

**Options:**
- **A. Plain throttle in tests.** Use Laravel's normal (cache-based) throttle when `APP_ENV=testing`. Production keeps Redis.
- **B. Redis in tests.** Start Redis in CI and for local test runs (Docker).

**Recommendation:** A. Tests then run anywhere, with no extra services.

**Answer:** No longer needed. The Redis throttle was removed, so tests and bare-metal installs need no Redis.

## Decided

- Q2, Q3, Q4, Q5: answered on 2026-11-21 (see each answer above). Built on branch `develop`.
- Q1: answered on 2026-11-22 (stash dropped). Q6: closed, the Redis throttle is gone.
- Factory Reset in the app: decided 2026-11-22, it became "Reset this phone" and deletes nothing on the server.
- Audit decisions of 2026-11-22: Android allows plain HTTP; the unused `/v1/media` path is removed; password reset by server command and by the admin in the app; sync pull is paged.
