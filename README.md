# Kinlore Server

[![Tests](https://github.com/Kinlore/kinlore-server/actions/workflows/tests.yml/badge.svg?branch=develop)](https://github.com/Kinlore/kinlore-server/actions/workflows/tests.yml?query=branch%3Adevelop)

Kinlore is a self-hosted family photo journal. One family keeps the photos, videos, stories, milestones and growth records of their children on their own server. One server holds one family. This repository holds the server: a Laravel API, a Docker Compose stack, and the files for an install directly on a Linux host. The mobile app is in [kinlore-mobile](https://github.com/Kinlore/kinlore-mobile).

**Documentation: <https://kinlore.github.io/kinlore-server/>**

## Features

- **One family per server.** The owner creates the admin account in a web browser. Everyone else joins with an invitation link.
- **Roles and permissions.** Members are `admin`, `partner` or `family`. The admin turns adding content, commenting, liking and downloading on or off for each family member.
- **Five kinds of moments.** Photos, videos, stories, milestones (from a list of 83) and growth records.
- **Original files kept.** Also HEIC photos from iPhones. Videos are converted in the background to a stream in 720p, 480p and 240p.
- **Likes, comments and look-back** ("on this day" and "then and now").
- **Offline sync** with the app, and a duplicate check for phone photos.
- **Full export** of all originals and all moments as a ZIP file.

## Quick start with Docker

```sh
git clone https://github.com/Kinlore/kinlore-server.git && cd kinlore-server
cp .env.example .env    # set APP_KEY, APP_URL, DB_PASSWORD, SEAWEEDFS_KEY, SEAWEEDFS_SECRET
docker compose up -d --build
docker compose exec api php artisan kinlore:setup-token
# open APP_URL in a browser, enter the token, and create the admin account
```

The full tutorial is [Quick start](https://kinlore.github.io/kinlore-server/guide/quick-start).

## Documentation

| Page | Content |
|---|---|
| [Install with Docker](https://kinlore.github.io/kinlore-server/self-hosting/docker) | The Docker Compose stack |
| [Install without Docker](https://kinlore.github.io/kinlore-server/self-hosting/bare-metal) | An install directly on Debian or Ubuntu |
| [Configuration](https://kinlore.github.io/kinlore-server/self-hosting/configuration) | Every setting |
| [HTTPS and reverse proxy](https://kinlore.github.io/kinlore-server/self-hosting/https-and-reverse-proxy) | Built-in HTTPS, or your own proxy |
| [Backup and restore](https://kinlore.github.io/kinlore-server/self-hosting/backup-and-restore) | What to save, and how to bring it back |
| [Updating](https://kinlore.github.io/kinlore-server/self-hosting/updating) | New releases and their extra steps |
| [App guide](https://kinlore.github.io/kinlore-server/app/install) | Install and use the mobile app |
| [API rules](https://kinlore.github.io/kinlore-server/reference/api) and [architecture](https://kinlore.github.io/kinlore-server/reference/architecture) | For developers |

The pages are in [`docs/`](docs). The OpenAPI description of the API is [kinlore-api/openapi.yaml](kinlore-api/openapi.yaml).

## Project status

The newest release is 1.4.0. Changes are listed in [CHANGELOG.md](CHANGELOG.md). Version numbers have the form `major.minor.patch`. A patch release has fixes only and needs no manual step. Use server 1.4 with app 1.2 or newer. Planned work is in the [roadmap](https://kinlore.github.io/kinlore-server/project/roadmap).

## Development and tests

The API is in [`kinlore-api/`](kinlore-api). It needs PHP 8.5 and Composer. The setup, the tests and the code style are in [CONTRIBUTING.md](CONTRIBUTING.md).

```sh
cd kinlore-api && php artisan test     # unit and feature tests
```

## Contributing, security and conduct

- Bug reports, ideas and pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first.
- Do not report security problems in a public issue. Read [SECURITY.md](SECURITY.md).
- Everyone who takes part must follow the [code of conduct](CODE_OF_CONDUCT.md).

## License

Copyright (C) 2025-2026 the Kinlore contributors.

Kinlore is free software under the GNU Affero General Public License, version 3. It comes with no warranty. See [LICENSE](LICENSE). If you run a changed version of Kinlore as a service for other people, the license requires you to offer them the source code of your changes.
