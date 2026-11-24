# Kinlore Server Architecture

This document describes the parts of the Kinlore server and how they work together. It is for developers and for people who run the server. The exact behaviour of each endpoint is in [API_CONTRACT.md](API_CONTRACT.md) and [`kinlore-api/openapi.yaml`](../kinlore-api/openapi.yaml).

## Components

One Kinlore deployment holds one family. It has these parts:

| Part | Docker install | Host install |
|---|---|---|
| Web server | Caddy, inside the API container | Caddy or nginx on the host |
| Application | Laravel 13 on PHP-FPM (PHP 8.5) | the same |
| Queue worker | inside the API container, started by supervisord | a `systemd` service (`kinlore-queue`) |
| Database | PostgreSQL 18 | SQLite (default) or PostgreSQL |
| Media storage | SeaweedFS, through its S3 API | the local disk (default) or any S3 storage |
| Cache and rate limits | Redis | the database |

The mobile app ([kinlore-mobile](https://github.com/elyess/kinlore-mobile)) is the only client of the API. A web browser uses three small server pages: the setup page for the first admin, a status page, and the page behind invitation links.

```
  phone (app)               browser
      |                        |
      | HTTPS /api/...         | /, /setup, /invite/{token}
      v                        v
  +-----------------------------------+
  | Caddy (or nginx)                  |
  |   -> PHP-FPM -> Laravel           |
  +-----------------------------------+
      |             |             |
      v             v             v
  database      media storage   jobs table
  (PostgreSQL   (SeaweedFS S3   (database queue)
   or SQLite)    or local disk)     |
                    ^               v
                    +-------- queue worker
                              (FFmpeg, ZIP export)
```

## Request path

1. The web server receives the request. With `KINLORE_DOMAIN` set, Caddy in the API container gets its own HTTPS certificate. Otherwise it serves plain HTTP for a reverse proxy in front.
2. The web server passes PHP requests to PHP-FPM. The document root is `kinlore-api/public`.
3. Laravel routes the request. API routes are in `kinlore-api/routes/api.php` under `/api`. The browser pages are in `kinlore-api/routes/web.php`.
4. Most API routes need a Sanctum bearer token. The app gets the token at sign-in.
5. A form request class in `app/Http/Requests` validates the input. A policy in `app/Policies` or `User::hasPermission()` checks the role and permissions.
6. A controller calls a service in `app/Services`. The controller returns JSON through `App\Http\Responses\ApiResponse`.

Rate limits apply per user, or per IP address before sign-in. The limits are set in `app/Providers/AppServiceProvider.php` and on single routes. The API trusts `X-Forwarded-*` headers only from the addresses in `TRUSTED_PROXIES`.

## Data model

| Table | Content |
|---|---|
| `users` | Family members. Each has a `role` (`admin`, `partner` or `family`) and four permission flags: `can_add_content`, `can_comment`, `can_like`, `can_download`. The flags apply only to `family`. There is exactly one admin. |
| `kids` | The children. A child has a date of birth, a due date, or both. |
| `moments` | The memories. `moment_type` is `photo`, `video`, `story`, `milestone` or `growth`. Each moment belongs to one child and records who uploaded it. |
| `media` | The files of a photo or video moment: paths of the original, the thumbnail, the medium image and the HLS stream, plus the processing status. |
| `moment_interactions` | Likes and comments on moments. |
| `milestones` | The predefined milestones. The seeder loads them from `database/seeders/milestones.json`. |
| `invitations` | Open invitations, with the role and permissions they give. |
| `exports` | ZIP exports and their status. |

Kids, moments, invitations and exports have UUID ids. When the app sends new kids and moments by sync, the server keeps the ids that the app made. So the app can create them offline. Users have integer ids. Kids and moments are soft-deleted, so that sync can report deletions.

Fixed sets of values are PHP enums in `app/Enums`: `Role`, `MomentType`, `VideoStatus`, `ExportStatus` and `InteractionType`.

## Upload and video processing

1. The app sends a photo or video with `POST /api/v1/moments` as a multipart upload.
2. `MomentService` stores the original file at `media/{kid id}/{media id}/original.{ext}` in the media storage. The file is read as a stream, so a large video does not fill PHP memory.
3. For a photo, the server makes a thumbnail and a medium-size image at once with Intervention Image (GD). For HEIC and HEIF it first decodes a temporary JPEG with `heif-dec` (libheif). The original is never changed. If processing fails, the server deletes the original and the media row, and answers with an error.
4. For a video, the server sets `video_status` to `processing`, puts a `ProcessVideoJob` on the `video-processing` queue, and answers at once.
5. The queue worker runs the job. It copies the video to a temporary local folder and uses FFmpeg to:
   - read the duration;
   - make a thumbnail and a medium image from the first frame;
   - convert an HDR video to SDR;
   - make an HLS stream with one variant per height in `VIDEO_QUALITY_VARIANTS` (default `720,480,240`) and 10-second segments.
6. The job copies the HLS files to the media storage next to the original and sets `video_status` to `ready`. On an error or after the 30-minute timeout, it sets `failed`.

Photos and image files are served through the API with the bearer token. Video players cannot send that header. So `GET /api/v1/moments/{id}/playback` returns a signed URL to the HLS master playlist, valid for 6 hours. The server rewrites each playlist so every line in it is also a signed URL. Requests without a valid signature get `403`.

Exports work the same way: `BuildExportJob` builds a ZIP file on the local disk of the API server, and the download link is a signed URL valid for 24 hours.

## Storage

All media goes through one Laravel disk named `seaweedfs` (see `config/filesystems.php`).

- **Docker:** the disk uses the S3 driver against the SeaweedFS filer on port 8333. SeaweedFS runs as three containers: master, volume and filer.
- **Host install:** `SEAWEEDFS_DRIVER=local` stores the files on the local disk, in `MEDIA_ROOT` (default `storage/app/media`). `SEAWEEDFS_DRIVER=s3` with the `SEAWEEDFS_*` settings uses any S3 storage instead.

The database, the media and `APP_KEY` are the three things to back up. Export ZIP files are in `storage/app/exports`. The server keeps the 3 newest.

## Offline sync

The app keeps a local database and works without a connection. Two endpoints keep it in step with the server. `SyncService` does the work.

- `POST /api/v1/sync/pull` returns the kids, moments and milestones that changed since the last pull. One page holds at most 2000 moments, in id order. While `has_more` is true, the app asks again with `next_cursor`. Kids and milestones come whole with the first page.
- `POST /api/v1/sync/push` sends kids and moments that the app created, changed or deleted offline. Each change goes through the same permission rules as the REST endpoints. Photos and videos are not sent by sync; they use the upload endpoint.

Times in sync payloads are milliseconds since the epoch. The full rules are in [API_CONTRACT.md](API_CONTRACT.md) section 9.

## Source tree

| Path | Content |
|---|---|
| `kinlore-api/app/Http/Controllers/Api` | API controllers |
| `kinlore-api/app/Http/Controllers` | Setup page and invitation page |
| `kinlore-api/app/Http/Requests` | Input validation |
| `kinlore-api/app/Services` | Application logic: moments, sync, kids, interactions, file storage |
| `kinlore-api/app/Jobs` | Queue jobs: `ProcessVideoJob`, `BuildExportJob` |
| `kinlore-api/app/Models`, `app/Policies`, `app/Enums` | Models, access rules, fixed value sets |
| `kinlore-api/app/Console/Commands` | `kinlore:reset-password`, `seaweedfs:test` |
| `kinlore-api/routes` | API routes and browser pages |
| `kinlore-api/database` | Migrations, factories, seeders, milestone list |
| `kinlore-api/tests` | Unit and feature tests |
| `kinlore-api/docker` | Caddy, supervisord, PHP and entrypoint files of the Docker image |
| `kinlore-api/openapi.yaml` | OpenAPI description of the API |
| `deploy/bare-metal` | Configuration files for a host install |
| `scripts` | End-to-end test and host-install test |
| `docker-compose.yml` | The Docker stack |
