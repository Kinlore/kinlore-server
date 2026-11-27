# API rules

This page is for developers who work on the server or on a client. It lists the behaviour rules of the Kinlore API that the OpenAPI file cannot express: who may do what, which fields a payload holds, how sync pages its data, how signed links work, and similar rules. The server (`kinlore-api`) and the mobile app ([kinlore-mobile](https://github.com/Kinlore/kinlore-mobile)) both follow them.

[`kinlore-api/openapi.yaml`](https://github.com/Kinlore/kinlore-server/blob/develop/kinlore-api/openapi.yaml) lists every endpoint with its parameters. Tools such as Postman can import it. The test `tests/Feature/OpenApiRoutesTest.php` compares its paths with the routes of the server.

When the code and this page disagree, fix the code, or change this page on purpose together with the app. Code comments refer to the numbered sections of this page (the file `docs/reference/api.md`), so keep the numbers.

## Conventions

- Base path `/api`. Authenticated routes use `Authorization: Bearer <token>` (Sanctum). A token stops working after 90 days without use (`401`); the member signs in again.
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
  "id": 1, "name": "Anna Example", "email": "anna@example.com",
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

**First admin: web setup only.** The mobile app never creates the admin. After installing the server (with or without Docker), the owner opens `APP_URL` in a web browser:
- `GET /` (web): when the instance has no user, redirects to `/setup`. Otherwise a small status page: "Kinlore is running", the server address to type in the app, and the app's sign-in steps. No user data.
- `GET /setup` (web): the setup form (`first_name`, `last_name`, an optional `friendly_name`, `email`, `password`, `password_confirmation`, and `setup_token`). A small script on the page fills `friendly_name` with `<first> <last> Family` until the admin types in it. When the instance already has a user: redirect to `/`.
- The setup token is `SETUP_TOKEN` when it is set. Without it, the server makes a random token and keeps it in `storage/app/setup-token` until the first admin exists. `php artisan kinlore:setup-token` prints it, and the Docker container prints it in its log at start. No HTTP response contains the token.
- `POST /setup` (web, CSRF-protected, throttled 5/min): creates the admin under a lock, so two parallel setups cannot both succeed. The account name is `first_name` and `last_name` joined by one space. `friendly_name` becomes the family name (below); empty gives `<first> <last> Family`. Validation errors show on the form. Success shows "Your admin account is ready" with the server address and the email to use in the app. It never shows the password.
- The seeded admin (`SEED_USER_*`) also works and skips the web setup.
- Behind a reverse proxy, the pages and form action use the proxy's `https` address (trusted proxies, section 10).

API:
- `GET /auth/setup-status` (public): `{ needs_setup: bool, setup_token_required: bool, instance_name: string, setup_url: string }`. `setup_url` is `{base}/setup` (see the public address rule below). `setup_token_required` is the same as `needs_setup`. The app uses it to tell the user to finish setup in a browser.
- `POST /auth/register`: `name, email, password, password_confirmation, invite_token` (required). `device_name` is optional (see sign-in below). It only registers invited users and gives them the role and permissions of the invitation. On an instance with no user it returns `403` with the message "Finish setup in a web browser at {setup_url}". A taken email gives a `422` email error only together with a valid invitation; without one the answer is the invitation error.
- `POST /auth/login` (public, throttled 5/min): `email, password` (required), `device_name` (optional). Wrong credentials give a `422` email error. Login and register return the user payload, `token` and `token_type: "Bearer"`.
- `device_name`: a string of up to 60 characters of plain text, without control characters. The server trims it. It names the token, so the user can tell devices apart. Without it, or when it is empty, the name is `mobile-app`. Older app versions do not send it.
- `GET /auth/sessions` (signed in): the caller's tokens, each `{ id, name, abilities, last_used_at, created_at, is_current }`. `name` is the `device_name` of the sign-in. `POST /auth/refresh` gives a new token with the same name. `DELETE /auth/sessions/{id}` signs that device out.
- `GET /auth/invitations/{token}` (public, throttled 10/min): `200 { valid: true, email: string|null, role, invited_by: { name }, expires_at, instance_name }`, or `404` when unknown, used or expired.
- Invitation item (admin endpoints): `{ id, email, role, permissions: {...}, invited_by: { id, name }, expires_at, created_at }`.
- `POST /v1/invitations` (admin): `{ email?, role?: partner|family (default family), permissions?: { add_content?, comment?, like?, download? }, expires_in_days?: 1..30 (default 7) }` → `201` invitation item plus `token`, `invite_link`, `invite_url`, `public_url_configured`:
  - `invite_link` = `kinlore://invite/{token}?server={urlencoded base}`
  - `invite_url` = `{base}/invite/{token}`: a small public HTML page with an "Open in Kinlore" button (the `invite_link`), the server address and the token, for people who get the link in a message app.
  - `public_url_configured` (bool): `false` when `APP_URL` is empty or a placeholder. The links then use the address of this request. The app can tell the admin to set `APP_URL`.

**Public address rule.** `{base}` is `APP_URL` when it is a real address. `APP_URL` is then used even when the request comes in on another host name. `APP_URL` is a placeholder when it is empty, `localhost`, `127.0.0.1`, `::1`, `0.0.0.0`, or a host under `example.com`, `example.org` or `example.net`. Then `{base}` is the address of the request, as seen through the trusted proxies (section 10). This rule applies to `invite_link`, `invite_url`, `setup_url`, the invite page and the setup pages.
- `GET /v1/invitations` (admin): pending invitations. `DELETE /v1/invitations/{id}` (admin): revoke.

**Family name.** The family name is the friendly name of this server, at most 60 characters. The app shows it in place of the server address.

- It is stored at setup. An empty field gives the admin's full name and "Family", for example "Anna Example Family". The full name keeps two relatives with the same last name apart. When no name is stored, the server uses the same suggestion.
- `PUT /v1/family { name }` (admin): changes it. Returns `{ family_name }`.
- Members get it as `family_name` in every sync pull page, so a new name reaches each phone with its next sync.
- The holder of a valid invitation sees it as `instance_name` in `GET /auth/invitations/{token}` and on the invitation page.
- Nobody else sees it. `GET /auth/setup-status`, `GET /`, the health endpoints and the page of an invalid invitation show `APP_NAME` or no name. A stranger who finds the server address must not learn whose family it is.

## 4. Members (admin only)

- `GET /v1/members`: `[{ id, name, email, role, is_admin, permissions, created_at, moments_count }]`.
- `PATCH /v1/members/{user}`: `{ role?: admin|partner|family, permissions?: {...}, password?: string (min 8) }`. `409` when the caller is no longer the admin (two transfers at once).
  - `password` sets a new password for a member who forgot theirs and deletes all their tokens (signed out everywhere). When the admin sets their own, the current session stays. The server owner can do the same with `php artisan kinlore:reset-password <email>`.
  - Setting `role=admin` on another user transfers the admin role: the caller becomes `partner`.
  - The admin cannot change their own role in any other way. Returns the member item.
- `DELETE /v1/members/{user}`: removes the user. The admin cannot remove themself. The user's tokens are revoked. Kids they created are reassigned to the admin (no data is lost). Their moments stay, with `user_id = null`. Their likes and comments are deleted.

## 5. Kids

Kid payload: `{ id, user_id, name, date_of_birth: "YYYY-MM-DD"|null, due_date: "YYYY-MM-DD"|null, avatar_color, age: int|null, age_months: int|null, is_expecting: bool, created_at, updated_at }`.
- A kid has a `date_of_birth`, or a `due_date` (expecting), or both (once born, the app sets `date_of_birth` and keeps `due_date`). At least one is required. `date_of_birth` may be today or tomorrow, for time zones ahead of the server (`before_or_equal:today`).
- `age`: whole years, `null` when not born.

## 6. Moments

Types: `photo`, `video`, `story`, `milestone`, `growth`.

Moment payload (REST and the inside of `data.moment`):
`{ id, kid_id, user_id: int|null, uploader_name: string|null, moment_type, title, description, milestone_id, exif_date (ISO|null), display_date (ISO, exif_date or created_at), latitude: number|null, longitude: number|null, orientation, width: int|null, height: int|null, camera_make, camera_model, file_size, mime_type, original_filename, device_asset_id, duration: number|null (seconds), video_status: "processing"|"ready"|"failed"|null, height_cm: number|null, weight_kg: number|null, head_circumference_cm: number|null, likes_count, comments_count, is_liked, created_at, updated_at, media?: {...}, milestone?: {...} }`. It has no file links: the app uses `/file/{type}` and `/playback`.

`width` and `height` are the pixel size of the photo or video as it is shown: after the EXIF orientation of a photo and after the rotation of a video. They are `null` for story, milestone and growth moments, for a video that is still processing, and for an older photo or video that `php artisan kinlore:media-dimensions` has not filled. For an older photo, that command stores the size of the 1200-pixel preview: the ratio is right, the size can be smaller than the original.

- `POST /v1/moments`:
  - photo/video: multipart `kid_id, file (required, also with moment_type=photo|video), title?, description?, device_asset_id?, exif_date?, latitude?, longitude?, camera_make?, camera_model?, orientation?`.
  - story: JSON `kid_id, moment_type=story, title?, description (required)`. Title is stored.
  - milestone: JSON `kid_id, moment_type=milestone, milestone_id, title?, description?, exif_date?`. Title and date are stored.
  - growth: JSON `kid_id, moment_type=growth, exif_date (measured on), height_cm?, weight_kg?, head_circumference_cm?, description?`. At least one measurement is required.
  - `moments.user_id` is set to the caller. Throttle: 60 per minute.
  - **Videos** are stored and the response returns at once with `video_status=processing`. A queued job makes the HLS stream and sets `ready` or `failed`. The client must not wait for processing.
  - **Allowed files:** JPEG, PNG, GIF, WebP, HEIC/HEIF, MP4, MOV, M4V, AVI, MKV and WebM, up to 500 MB.
  - **Same file again:** a photo or video that the kid already has returns that moment with `200`, not a new one. The server compares the MD5 hash of the file (stored as `device_fingerprint`), so the same file from any member or phone counts. The same `device_asset_id` with the same file size counts too. A deleted moment does not count. A new upload of a video whose `video_status` is `failed` replaces the failed moment.
  - **Storage failure:** `500` when the file cannot be stored. Nothing is kept, so the client may send it again.
  - **Duplicate check:** `GET /v1/kids/{kid}/moments/uploaded-identifiers` lists the files that are already uploaded. Videos whose `video_status` is `failed` are not listed, so the app uploads them again.
  - **Dates:** an `exif_date` with a time zone offset is stored in UTC.
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
- `then_and_now`: for each born kid, the first photo taken on or after the birth day and the latest photo, when they are at least 6 months apart. Only photos with an `exif_date` count. Kids without a `date_of_birth` get no pair.

## 8. Export (admin only)

- `POST /v1/exports` → `202` export item. A queued job builds a ZIP file on the API server's local disk (`storage/app/exports`). Only one export can run at a time (`409` otherwise).
- `GET /v1/exports` → `[{ id, status: pending|processing|ready|failed, size_bytes, error, created_at, completed_at, download_url: string|null }]`, newest first. `download_url` is a signed URL valid for 24 hours, set when `ready`. An export ends as `failed` when an original cannot be read from the storage, so a `ready` export always holds every file.
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
- Moment row: `{ id, kid_id, user_id, uploader_name, moment_type, title, description, milestone_id, exif_date, latitude, longitude, orientation, width, height, duration, video_status, height_cm, weight_kg, head_circumference_cm, likes_count, comments_count, is_liked, file_size, mime_type, device_asset_id, created_at, updated_at }`. No file fields. `width` and `height` are as in the moment payload (section 6). `is_liked` is for the caller.

`POST /v1/sync/push` request: `{ changes: { kids: {created, updated, deleted}, moments: {created, updated, deleted} }, lastPulledAt }`.
- Kid fields: `id, name, date_of_birth (ms|null), due_date (ms|null), avatar_color`. The server keeps the client `id`.
- Moment fields: `id, kid_id, moment_type (story|milestone|growth), title, description, milestone_id, exif_date (ms|null), height_cm, weight_kg, head_circumference_cm`. The server keeps the client `id`. Photo and video rows are created by upload only; in `updated` the server accepts only `title, description, exif_date` for them.
- `deleted`: arrays of ids.
- Every change goes through the same permission rules as REST.

Response: `{ success: true, data: { kids_created: [{ local_id, id }], moments_created: [{ local_id, id }], rejected: [{ table, id, reason }] } }`. `local_id` equals `id`, because the server keeps client ids.
- `reason`: `forbidden`, `invalid` (fails validation: an `id` or `kid_id` that is not a UUID, an unknown `milestone_id`, a name or title over 255 characters, a description over 5000, an `avatar_color` that is not `#RRGGBB`), `deleted` (the row is deleted on the server), `conflict` or `error` (server failure; the change may be sent again).
- `conflict`: a row in `created` or `updated` whose server copy was edited by another member after `lastPulledAt` is not applied. An edit in the same second as `lastPulledAt` counts as after it. Likes, comments and the pushing member's own edits do not count. The next pull sends the server copy. With `lastPulledAt` `null` or `0` there is no conflict check.

## 10. Reverse proxy

The API trusts `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port` and `X-Forwarded-For` from the addresses in `TRUSTED_PROXIES` (default: private networks). Signed links, invite links, the web setup page and redirects then use the public `https` address. The Docker image has Caddy as its web server: with `KINLORE_DOMAIN` set it serves HTTPS with its own certificate, otherwise plain HTTP for your own reverse proxy. Examples are in [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).

## 11. Health

`GET /health` (public, no envelope): `{ status, timestamp, service }`. `GET /health/detailed` (admin only). Its `checks.public_url` has `status: "warning"` when `APP_URL` is empty or a placeholder (see the public address rule in section 3), and `"ok"` otherwise. The warning does not change the overall status.
