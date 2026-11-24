# Kinlore Server

[![Tests](https://github.com/Kinlore/kinlore-server/actions/workflows/tests.yml/badge.svg?branch=develop)](https://github.com/Kinlore/kinlore-server/actions/workflows/tests.yml?query=branch%3Adevelop)

Kinlore is a self-hosted family photo journal. One family keeps the photos, videos, stories, milestones and growth records of their children on their own server. One deployment holds one family. This repository contains the server: a Laravel API, a Docker Compose stack, and a guide for an install directly on a Linux host. The mobile app is in a separate repository.

## Features

- **One family per server.** The owner creates the admin account in a web browser at `/setup`. Everyone else joins with an invitation link.
- **Roles and permissions.** Members are `admin`, `partner` or `family`. The admin can turn adding content, commenting, liking and downloading on or off for each family member.
- **Children.** A child has a date of birth, a due date (while expected), or both.
- **Five kinds of memories ("moments").** Photos, videos, stories, milestones (from a list of 83 predefined milestones) and growth records (height, weight, head circumference).
- **Photos and videos.** The server keeps the original file and makes a thumbnail and a medium-size image. Videos are converted in the background to an HLS stream in 720p, 480p and 240p. HDR videos are converted to SDR first.
- **Likes and comments** on every memory.
- **Look-back.** "On this day" in earlier years, and "then and now" photos of each child.
- **Offline sync.** The app keeps a local copy and syncs it with the server in pages.
- **Duplicate check.** The app can ask which photos of the phone are already uploaded.
- **Full export.** The admin can download a ZIP file with all original photos and videos and all memories as JSON.
- **Sign-ins and passwords.** The API can list and revoke the sign-ins of a member. The admin can set a new password for a member in the app. The server owner can reset any password with an Artisan command.

## Project status

Version 1.0.0 is the first release. Releases follow [Semantic Versioning](https://semver.org/): from 1.0.0 on, a breaking change of the API needs a new major version. Changes are listed in [CHANGELOG.md](CHANGELOG.md). The mobile app has been tested on the iOS simulator only. Planned work is in [docs/ROADMAP.md](docs/ROADMAP.md).

## Quick start with Docker

You need Docker with Docker Compose.

```sh
git clone https://github.com/Kinlore/kinlore-server.git
cd kinlore-server
cp .env.example .env
```

Edit `.env`. Set at least these five values:

- `APP_KEY`: make one with `echo "base64:$(openssl rand -base64 32)"`.
- `APP_URL`: the address the phones use, for example `https://photos.example.com`.
- `DB_PASSWORD`: a long random password.
- `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET`: the keys of the media storage. Make each one with `openssl rand -hex 32`.

Then start the stack and check it:

```sh
docker compose up -d --build
curl http://localhost/api/health
```

Open `APP_URL` in a web browser and create the admin account. HTTPS, reverse proxies, backups and updates are in [docs/SELF_HOSTING.md](docs/SELF_HOSTING.md).

## Documentation

| Document | Content |
|---|---|
| [docs/SELF_HOSTING.md](docs/SELF_HOSTING.md) | Run the server with Docker, with built-in HTTPS or behind your own reverse proxy. |
| [docs/BARE_METAL.md](docs/BARE_METAL.md) | Install the server directly on a Linux host, without Docker. |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | The parts of the server and how they work together. |
| [docs/API_CONTRACT.md](docs/API_CONTRACT.md) | The behaviour rules of the API that the OpenAPI file cannot express. |
| [kinlore-api/openapi.yaml](kinlore-api/openapi.yaml) | The OpenAPI description of every endpoint. |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Direction and planned work. |
| [CHANGELOG.md](CHANGELOG.md) | Changes per release. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Development setup, tests, code style and pull requests. |

## How it fits together

The mobile app talks to a Laravel API over HTTPS. In Docker, the API container runs Caddy as the web server, PHP-FPM, and a queue worker for videos and exports. PostgreSQL holds the data, SeaweedFS holds the media through its S3 API, and Redis holds the cache and rate limits. A host install can use SQLite and the local disk instead. Details are in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Related repository

- [kinlore-mobile](https://github.com/Kinlore/kinlore-mobile): the mobile app. It works offline and syncs with this server.

## Development and tests

The API is in [`kinlore-api/`](kinlore-api). It needs PHP 8.5 and Composer. The full setup is in [CONTRIBUTING.md](CONTRIBUTING.md).

```sh
cd kinlore-api && php artisan test                         # unit and feature tests
scripts/smoke-test.sh <address> <admin email> <password>  # end-to-end, against a running test instance
scripts/test-bare-metal.sh                                # the host-install guide, in a clean Debian container
```

## Contributing

Bug reports, ideas and pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## Security

Do not report security problems in a public issue. Read [SECURITY.md](SECURITY.md).

## Code of conduct

Everyone who takes part in this project must follow the [code of conduct](CODE_OF_CONDUCT.md).

## License

Copyright (C) 2025-2026 the Kinlore contributors.

Kinlore is free software. You can redistribute it and change it under the terms of the GNU Affero General Public License, version 3, as published by the Free Software Foundation. Kinlore comes with no warranty. See [LICENSE](LICENSE) for the full text.

If you run a changed version of Kinlore as a service for other people, the license requires you to offer them the source code of your changes.
