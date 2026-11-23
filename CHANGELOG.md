# Changelog

All notable changes to this project are listed in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

The first release will contain:

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
