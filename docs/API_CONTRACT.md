# Kinlore API Contract

This document lists the behaviour rules of the Kinlore API that the OpenAPI file cannot express: who may do what, which fields a payload holds, how sync pages its data, how signed links work, and similar rules. The server (`kinlore-api`) and the mobile app ([kinlore-mobile](https://github.com/elyess/kinlore-mobile)) both follow it.

[`kinlore-api/openapi.yaml`](../kinlore-api/openapi.yaml) lists every endpoint with its parameters. It is written by hand from this file and the code. The test `tests/Feature/OpenApiRoutesTest.php` compares its paths with the server's routes.

When the code and this file disagree, fix the code, or change this file on purpose together with the app. Code comments refer to the numbered sections of this file, so keep the numbers.

Conventions:
- Base path `/api`. Authenticated routes use `Authorization: Bearer <token>` (Sanctum).
- JSON envelope: `{ success: bool, message?: string, data?: any, meta?: object, errors?: object }`. Exceptions: `GET /api/health` and `POST /api/v1/sync/pull` have no envelope (`sync/push` uses it).
- Dates in REST bodies: ISO 8601 strings. Calendar dates: `YYYY-MM-DD`. **In sync payloads every time is integer milliseconds since the epoch, or `null`.** Never `0` for "no value".
- Ids: kids, moments, invitations and exports are UUID strings. Users are integers.

## 1. Roles and permissions

One instance holds one family. `users.role` is one of:

| Role | Can do |
|---|---|
| `admin` | Everything. Only role that manages members, invitations and exports. Exactly one admin at all times. |
| `partner` | Add, edit and delete any kid and any content. Like, comment, download. |
| `family` | View everything. Add content, like, comment and download only when the matching permission is on. Edit or delete only the moments they uploaded and their own comments. Cannot create, edit or delete kids. |

Permissions (columns on `users`, booleans, default `true`): `can_add_content`, `can_comment`, `can_like`, `can_download`. They apply only to `family`; for `admin` and `partner` they always count as `true`.

`User::hasPermission(string $permission): bool` with `$permission` in `add_content`, `comment`, `like`, `download` is the single check that all code uses.

`is_admin` is in every user payload as a derived value (`role === 'admin'`).

Rules:
- Kid update/delete: admin, partner, or the kid's creator (`kids.user_id`). Kid create: admin or partner.
- Moment update/delete: admin, partner, the uploader (`moments.user_id`), or the creator of the moment's kid.
- Create moment (any type, REST or sync): needs `add_content`.
- Like/unlike: needs `like`. Comment: needs `comment`. Delete comment: its author or admin.
- `GET /v1/moments/{id}/file/original` and the export download: need `download`. `thumbnail`, `medium` and video playback need no extra permission.
- A failed permission check returns `403` with a message.

New invitations default to `family`.

## 2. User payload

Returned by login, register and `/auth/me` (`/auth/me` adds the count fields):

```json
{
  "id": 1, "name": "Ann", "email": "ann@example.com",
  "role": "admin", "is_admin": true,
  "permissions": { "add_content": true, "comment": true, "like": true, "download": true },
  "created_at": "2026-11-21T10:00:00+00:00",
  "kids_count": 2, "moments_count": 340, "my_moments_count": 120,
  "storage_used": "1.20 GB", "storage_bytes_used": 1288490188, "storage_bytes_available": 400000000000,
  "email_verified_at": null
}
```

`kids_count`, `moments_count`, `storage_*`: whole family (instance). `my_moments_count`: moments this user uploaded.

## 3. Setup, registration and invitations

**First admin: web setup only.** The mobile app never creates the admin. After installing the server (Docker or bare metal), the owner opens `APP_URL` in a web browser:
- `GET /` (web): when the instance has no user, redirects to `/setup`. Otherwise a small status page: "Kinlore is running", the server address to type in the app, and the app's sign-in steps. No user data.
- `GET /setup` (web): the setup form (name, email, password, password confirmation, and a setup token field when `SETUP_TOKEN` is set). When the instance already has a user: redirect to `/`.
- `POST /setup` (web, CSRF-protected, throttled 5/min): creates the admin under a lock, so two parallel setups cannot both succeed. Validation errors show on the form. Success shows "Your admin account is ready" with the server address and the email to use in the app. It never shows the password.
- The seeded admin (`SEED_USER_*`) also works and skips the web setup.
- Behind a reverse proxy, the pages and form action use the proxy's `https` address (trusted proxies, section 10).

API:
- `GET /auth/setup-status` (public): `{ needs_setup: bool, setup_token_required: bool, instance_name: string, setup_url: string }`. `setup_url` is `{APP_URL}/setup`. The app uses it to tell the user to finish setup in a browser.
- `POST /auth/register`: `name, email, password, password_confirmation, invite_token` (required). It only registers invited users and gives them the role and permissions of the invitation. On an instance with no user it returns `403` with the message "Finish setup in a web browser at {setup_url}".
- `GET /auth/invitations/{token}` (public, throttled 10/min): `200 { valid: true, email: string|null, role, invited_by: { name }, expires_at, instance_name }`, or `404` when unknown, used or expired.
- Invitation item (admin endpoints): `{ id, email, role, permissions: {...}, invited_by: { id, name }, expires_at, created_at }`.
- `POST /v1/invitations` (admin): `{ email?, role?: partner|family (default family), permissions?: { add_content?, comment?, like?, download? }, expires_in_days?: 1..30 (default 7) }` → `201` invitation item plus `token`, `invite_link`, `invite_url`:
  - `invite_link` = `kinlore://invite/{token}?server={urlencoded APP_URL}`
  - `invite_url` = `{APP_URL}/invite/{token}`: a small public HTML page with an "Open in Kinlore" button (the `invite_link`), the server address and the token, for people who get the link in a message app.
- `GET /v1/invitations` (admin): pending invitations. `DELETE /v1/invitations/{id}` (admin): revoke.

## 4. Members (admin only)

- `GET /v1/members`: `[{ id, name, email, role, is_admin, permissions, created_at, moments_count }]`.
- `PATCH /v1/members/{user}`: `{ role?: admin|partner|family, permissions?: {...}, password?: string (min 8) }`.
  - `password` sets a new password for a member who forgot theirs and deletes all their tokens (signed out everywhere). When the admin sets their own, the current session stays. The server owner can do the same with `php artisan kinlore:reset-password <email>`.
  - Setting `role=admin` on another user transfers the admin role: the caller becomes `partner`.
  - The admin cannot change their own role in any other way. Returns the member item.
- `DELETE /v1/members/{user}`: removes the user. The admin cannot remove themself. The user's tokens are revoked. Kids they created are reassigned to the admin (no data is lost). Their moments stay, with `user_id = null`. Their likes and comments are deleted.

## 5. Kids

Kid payload: `{ id, user_id, name, date_of_birth: "YYYY-MM-DD"|null, due_date: "YYYY-MM-DD"|null, avatar_color, age: int|null, age_months: int|null, is_expecting: bool, created_at, updated_at }`.
- A kid has a `date_of_birth`, or a `due_date` (expecting), or both (once born, the app sets `date_of_birth` and keeps `due_date`). At least one is required. `date_of_birth` may be today (`before_or_equal:today`).
- `age`: whole years, `null` when not born.

## 6. Moments

Types: `photo`, `video`, `story`, `milestone`, `growth`.

Moment payload (REST and the inside of `data.moment`):
`{ id, kid_id, user_id: int|null, uploader_name: string|null, moment_type, title, description, milestone_id, exif_date (ISO|null), display_date (ISO, exif_date or created_at), latitude: number|null, longitude: number|null, orientation, camera_make, camera_model, file_size, mime_type, original_filename, device_asset_id, duration: number|null (seconds), video_status: "processing"|"ready"|"failed"|null, height_cm: number|null, weight_kg: number|null, head_circumference_cm: number|null, likes_count, comments_count, is_liked, created_at, updated_at, media?: {...}, milestone?: {...} }`.

- `POST /v1/moments`:
  - photo/video: multipart `kid_id, file, title?, description?, device_asset_id?, exif_date?, latitude?, longitude?, camera_make?, camera_model?, orientation?`.
  - story: JSON `kid_id, moment_type=story, title?, description (required)`. Title is stored.
  - milestone: JSON `kid_id, moment_type=milestone, milestone_id, title?, description?, exif_date?`. Title and date are stored.
  - growth: JSON `kid_id, moment_type=growth, exif_date (measured on), height_cm?, weight_kg?, head_circumference_cm?, description?`. At least one measurement is required.
  - `moments.user_id` is set to the caller. Throttle: 60 per minute.
  - **Videos** are stored and the response returns at once with `video_status=processing`. A queued job makes the HLS stream and sets `ready` or `failed`. The client must not wait for processing.
- `PUT /v1/moments/{id}`: `title?, description?, exif_date?, milestone_id?, height_cm?, weight_kg?, head_circumference_cm?`. Returns the moment.
- `DELETE /v1/moments/{id}`.
- `GET /v1/moments`: filters `kid_id?, moment_type?` (comma list), `liked?=true` (liked by the caller), `from?, to?` (YYYY-MM-DD on display date), `page?, per_page? (max 100)`. Sorted by display date, newest first. `meta` has the pagination.
- `GET /v1/moments/{id}`.
- `GET /v1/moments/{id}/file/{original|medium|thumbnail}` (auth header). `original` needs `download`.
- `GET /v1/moments/{id}/playback` → `{ hls_url, expires_at }`. `hls_url` is a signed URL that is valid for 6 hours and needs no header. The master playlist and the variant playlists are rewritten so every line they reference is also a signed URL. Unsigned or expired HLS requests get `403`. The app calls `playback` each time it starts a video.
- Likes: `POST|DELETE /v1/moments/{id}/like`. Comments: `GET|POST /v1/moments/{id}/comments`, `DELETE /v1/interactions/{id}`. A comment is `{ id, moment_id, user_id, type: "comment", content, created_at, updated_at, user: { id, name } }`; authors' email addresses are not shown to the family.

## 7. Look-back

`GET /v1/lookback?date=YYYY-MM-DD` (the app sends its local date; default: today on the server) →
```json
{ "on_this_day": [ { "years_ago": 1, "date": "2025-11-21", "moments": [ moment, ... ] } ],
  "then_and_now": [ { "kid_id": "…", "kid_name": "Mia", "then": moment, "now": moment } ] }
```
- `on_this_day`: photo, video and story moments whose display date has the same month and day in an earlier year. Newest year first. At most 10 moments per year.
- `then_and_now`: for each kid with at least two photos that are at least 6 months apart, the earliest photo and the latest photo.

## 8. Export (admin only)

- `POST /v1/exports` → `202` export item. A queued job builds a ZIP file on the API server's local disk (`storage/app/exports`). Only one export can run at a time (`409` otherwise).
- `GET /v1/exports` → `[{ id, status: pending|processing|ready|failed, size_bytes, error, created_at, completed_at, download_url: string|null }]`, newest first. `download_url` is a signed URL valid for 24 hours, set when `ready`.
- `GET /v1/exports/{id}/download` (signed URL, no header) → the ZIP.
- ZIP layout: `{Kid name}/{YYYY}/{YYYY-MM-DD}_{type}_{last 12 hex characters of id}.{ext}` for photos and videos (original files), plus `{Kid name}/memories.json` with every moment of that kid (all fields, including stories, milestones, growth, comments with author names) and `kinlore-export.json` at the root (instance name, export date, kids).
- The server keeps the 3 newest exports and deletes older ZIP files.

## 9. Sync

`POST /v1/sync/pull` request: `{ lastPulledAt: number|null, cursor?: string|null, limit?: 1..2000 }`.

Response: `{ changes: { kids: {created, updated, deleted}, moments: {created, updated, deleted}, milestones: {created, updated, deleted} }, timestamp: number, has_more: bool, next_cursor: string|null }`.
- **Paging:** one response holds at most 2000 moments (`limit`), in id order. While `has_more` is true, the client pulls again with the same `lastPulledAt` and `cursor = next_cursor`. Kids and milestones come whole with the first page (no cursor); later pages have them empty. The client applies each page, and after the last page stores the `timestamp` of the **first** page. When a page fails, it stores nothing and starts again at the next sync.
- `timestamp` is the server time in milliseconds at the start of the pull.
- The server returns rows with `updated_at >= lastPulledAt - 1000` (database times have second precision). The client must therefore **upsert** every row in `created` and `updated` (insert when missing, otherwise update all fields). Rows may come again.
- `deleted`: ids of kids and moments soft-deleted since `lastPulledAt - 1000`, including the moments of deleted kids.
- Kid row: `{ id, user_id, name, date_of_birth, due_date, avatar_color, created_at, updated_at }` (times in ms, dates as ms at UTC midnight, or null).
- Moment row: `{ id, kid_id, user_id, uploader_name, moment_type, title, description, milestone_id, exif_date, latitude, longitude, orientation, duration, video_status, height_cm, weight_kg, head_circumference_cm, likes_count, comments_count, is_liked, file_size, mime_type, device_asset_id, created_at, updated_at }` plus the existing file fields. `is_liked` is for the caller.

`POST /v1/sync/push` request: `{ changes: { kids: {created, updated, deleted}, moments: {created, updated, deleted} }, lastPulledAt }`.
- Kid fields: `id, name, date_of_birth (ms|null), due_date (ms|null), avatar_color`. The server keeps the client `id`.
- Moment fields: `id, kid_id, moment_type (story|milestone|growth), title, description, milestone_id, exif_date (ms|null), height_cm, weight_kg, head_circumference_cm`. The server keeps the client `id`. Photo and video rows are created by upload only; in `updated` the server accepts only `title, description, exif_date` for them.
- `deleted`: arrays of ids.
- Every change goes through the same permission rules as REST.

Response: `{ success: true, data: { kids_created: [{ local_id, id }], moments_created: [{ local_id, id }], rejected: [{ table, id, reason }] } }`. `local_id` equals `id`, because the server keeps client ids.

## 10. Reverse proxy

The API trusts `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port` and `X-Forwarded-For` from the addresses in `TRUSTED_PROXIES` (default: private networks). Signed links, invite links, the web setup page and redirects then use the public `https` address. The Docker image has Caddy as its web server: with `KINLORE_DOMAIN` set it serves HTTPS with its own certificate, otherwise plain HTTP for your own reverse proxy. Examples are in `docs/SELF_HOSTING.md` and `docs/BARE_METAL.md`.

## 11. Health

`GET /health` (public, no envelope): `{ status, timestamp, service }`. `GET /health/detailed` (auth).
