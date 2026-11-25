# Changelog

All notable changes to this project are listed in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The version numbers are explained in "Project status" in the [README](README.md).

## [Unreleased]

## [1.2.0] - 2026-11-25

Use this release with version 1.2.0 or newer of the mobile app.

### Added

- A family name: the friendly name of the server. The admin sets it on the setup page (optional; the default is the admin's full name and "Family", for example "Anna Example Family") and changes it with `PUT /v1/family`. Members get it as `family_name` with every sync pull, and invitations show it in place of `APP_NAME`. Public pages and endpoints do not show it. The new migration adds the `settings` table.

### Upgrade notes

- **Docker:** PostgreSQL and Redis are no longer published on the host. If a tool on the host uses them, add the ports again in a `docker-compose.override.yml` (see [Reach a database or storage port from the host](https://kinlore.github.io/kinlore-server/self-hosting/docker#reach-a-database-or-storage-port-from-the-host)). The settings `DB_PORT`, `REDIS_FORWARD_PORT` and `BIND_ADDRESS` in the root `.env` have no effect any more.
- **Host install:** copy the new `kinlore-queue-default.service` and the changed `kinlore-queue.service` and `kinlore-backup.sh` (see [Updating](https://kinlore.github.io/kinlore-server/self-hosting/updating#from-1-1-to-1-2)). Exports now have their own worker.
- **Setup of a new server:** the setup page always asks for a setup token. Without `SETUP_TOKEN`, get it with `php artisan kinlore:setup-token` or from the Docker log.
- **Database:** two migrations run with the update. One adds the `settings` table. One fills the edit time of memories made before 1.1.0.

### Security

- Setup of a new server always needs a setup token, so a stranger cannot claim a server that is already online. Without `SETUP_TOKEN`, the server makes a random token. The new command `kinlore:setup-token` prints it, and the Docker container prints it in its log.
- Sign-up no longer shows whether an email address belongs to a member, unless the request has a valid invitation.
- Sign-in takes the same time for a known and an unknown email address.
- A sign-in token stops working after 90 days without use.
- `setup_url` comes from `APP_URL`, not from the request's host name.
- Sync push checks each row like the REST API: IDs, text lengths, the milestone and the color.

### Changed

- GIF, WebP and M4V files are accepted. Before, the request check allowed them and the upload then failed.
- Videos and exports have separate workers, so a long export does not delay videos.
- Each sign-in, sign-up, invitation and setup limit has its own counter. Before, they shared one per network address.
- Docker: logs rotate (daily application log, 10 MB container logs), and the API container stops with the error after two minutes when it cannot reach the database.
- SQLite (host install) uses WAL mode and a 5-second lock timeout.
- The host backup keeps replaced and deleted files for 30 days in `old/`, and writes the database copy in one step.
- The Docker guide has backup and restore commands.

### Fixed

- A failed write to the media storage fails the upload. Before, the server answered "created" with no original stored.
- An export fails when an original cannot be read. Before, it ended as "ready" without that file.
- A seeded admin (`SEED_USER_EMAIL`) with capital letters in the email can sign in, and `kinlore:reset-password` finds the address in any case.
- A like on a memory made before 1.1.0 no longer turns another member's offline edit into a conflict.
- Sync conflicts: a repeated create of an existing row is checked like an update, an edit in the same second as the pull counts, and two pushes at once cannot both pass the check.
- A failed video is not counted as uploaded, so the app sends it again, and the new upload replaces the failed one.
- Deleting a failed video removes all its files. Deleting a memory removes the row first and the files after.
- The same file uploaded twice (a retry after a lost answer) gives one memory, not two.
- An upload that fails after the original is stored leaves no row and no files behind. HEIC temp files are always removed.
- A photo date with a time zone offset is stored in UTC, the same as through sync.
- Two "make admin" requests at once cannot leave two admins.

### Removed

- Unused policy methods for restore and permanent delete.

## [1.1.0] - 2026-11-24

Use this release with version 1.1.0 or newer of the mobile app.

### Upgrade notes

- **Docker:** add `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` to `.env` before you start the new version. See [Updating](https://kinlore.github.io/kinlore-server/self-hosting/updating#from-1-0-to-1-1).
- **Host install:** install `libheif-examples`, copy the PHP settings files again, and remove two timeout lines from the Caddy file. With S3 storage, set `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` in `kinlore-api/.env`. See [Updating](https://kinlore.github.io/kinlore-server/self-hosting/updating#from-1-0-to-1-1).
- **Database:** one migration runs with the update. It adds the columns for sync conflicts, removes likes that were stored twice, and drops the empty milestone `category` column.
- **Other API clients:** moment and sync data no longer contain `photo_url`, `medium_url`, `thumbnail_url` and `hls_url`. Use `/file/{type}` and `/playback`. Milestones have no `category`, and `/api/health/detailed` needs an admin sign-in.

### Security

- Moment payloads no longer contain signed links to the stored files. On an install without Docker these links let a member without the download permission fetch every original. The app uses the checked `/file/*` endpoints.
- `/api/health/detailed` is for the admin only.
- SeaweedFS has no default keys: `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` are required, and its S3 port is no longer published on the host.
- The queue worker, which runs FFmpeg on uploaded videos, runs as `www-data`, not as root.
- The seeder creates the first admin only when the server has no user, so a removed account does not come back at the next start.
- The bare-metal backup writes its files for the owner only.

### Fixed

- A video without a thumbnail or medium image yet (still processing, or failed) now answers 404 "File not found" for those files, not 400 "Invalid file type".
- HEIC and HEIF photos (the iPhone default) get their thumbnail and medium image. The original file is kept as it was uploaded. The image has `libheif-tools`; bare-metal installs need `libheif-examples`.
- A photo that fails processing no longer leaves its original and a media record behind. A failed video no longer leaves its full copy on the disk.
- A photo or video moment sent without a file gives 422, not 500.
- Sync push rejects an edit with `conflict` when another member edited the moment or child after the phone's last pull. Likes, comments and the member's own edits do not count. The new migration adds the columns for this. A server failure gives `error` (the phone retries), not `invalid`.
- Growth values pushed through sync have the same limits as in the REST API.
- A birth date of "today" is accepted in time zones ahead of the server.
- Email addresses are compared without case at sign-in, registration, setup and invitation.
- A like sent twice counts once.
- Removing a member updates the like and comment counts on the phones.
- The first and latest moment of a child are right on PostgreSQL, where empty dates sort first.
- Milestones are listed in life-stage order.
- The storage health check fails when the storage does not answer.
- Export: a file copied short fails the export, media files are stored without compression, and partial ZIP files and temp files of a killed export are removed.
- Caddy no longer cuts uploads and downloads after 30 minutes.
- PHP-FPM has 20 workers instead of 5 (Docker image and the new `deploy/bare-metal/php-fpm-kinlore.conf`), so long uploads do not block the API, and 1 GB of memory for large photos.
- A redeploy waits up to one hour for a running video or export job.
- HDR videos are tone-mapped at 720p with a faster preset, so long 4K clips finish in time.
- `VIDEO_QUALITY_VARIANTS` works with cached config.

### Removed

- The milestone `category` field, its filter and `group_by=category`. No milestone had a category.
- Unused code: the `uploads` rate limiter, unused helpers in `User` and `InteractionService`, the `inspire` command, the welcome page, and the `VIDEO_MAX_SIZE_MB` and `VIDEO_PROCESSING_TIMEOUT` settings.

## [1.0.0] - 2026-11-23

First release.

### Added

- One family per deployment. The owner creates the admin account in a web browser at `/setup`, optionally protected by a setup token. Other members join with an invitation link.
- Roles `admin`, `partner` and `family`, with per-member permissions to add content, comment, like and download.
- Children with a date of birth, a due date, or both.
- Moments of five types: photo, video, story, milestone (from 83 predefined milestones) and growth record.
- Thumbnails and medium-size images for photos and videos, and background conversion of videos to HLS streams with signed playback links.
- Likes and comments.
- Look-back: "on this day" and "then and now".
- Offline sync for the mobile app, with paged pulls.
- Full ZIP export of all memories for the admin.
- Member management for the admin, and a `kinlore:reset-password` command for the server owner.
- Docker Compose stack with PostgreSQL, SeaweedFS, Redis and Caddy with optional built-in HTTPS.
- Guide and configuration files for an install directly on a Linux host, with SQLite and the local disk.
- OpenAPI description of the API.

[Unreleased]: https://github.com/Kinlore/kinlore-server/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/Kinlore/kinlore-server/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Kinlore/kinlore-server/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Kinlore/kinlore-server/releases/tag/v1.0.0
