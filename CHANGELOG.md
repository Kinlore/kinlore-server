# Changelog

All notable changes to this project are listed in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- Moment payloads no longer contain signed links to the stored files. On an install without Docker these links let a member without the download permission fetch every original. The app uses the checked `/file/*` endpoints.
- `/api/health/detailed` is for the admin only.
- SeaweedFS has no default keys: `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` are required, and its S3 port is no longer published on the host. **Existing installs must add both keys to `.env` before the next `docker compose up`.**
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

- Unused code: the `uploads` rate limiter, unused helpers in `User` and `InteractionService`, the `inspire` command, the welcome page, the milestone `category` filter and field, and the `VIDEO_MAX_SIZE_MB` and `VIDEO_PROCESSING_TIMEOUT` settings.

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

[Unreleased]: https://github.com/elyess/kinlore-server/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/elyess/kinlore-server/releases/tag/v1.0.0
