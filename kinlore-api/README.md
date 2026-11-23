# kinlore-api

This folder holds the Kinlore API: a Laravel 13 application that serves the mobile app, the setup page and the invitation page. The [Docker image](Dockerfile) of the server is built from this folder.

Read the [root README](../README.md) for an overview of the project, and [CONTRIBUTING.md](../CONTRIBUTING.md) for the full development setup.

## Requirements

- PHP 8.5 with the extensions `pdo_sqlite`, `gd`, `exif`, `zip`, `intl`, `mbstring` and `bcmath` (`pdo_pgsql` for PostgreSQL)
- Composer
- FFmpeg, to convert videos

## Development commands

Run them in this folder.

| Task | Command |
|---|---|
| Install the dependencies | `composer install` |
| Create the settings file | `cp .env.example .env && php artisan key:generate` |
| Create the tables and the milestone list | `php artisan migrate --seed` |
| Start the development server | `php artisan serve` |
| Process videos and exports | `php artisan queue:work --queue=video-processing,default` |
| Run the unit and feature tests | `php artisan test` |
| Format the code | `vendor/bin/pint` |
| Check the media storage | `php artisan seaweedfs:test` |
| Set a new password for a user | `php artisan kinlore:reset-password <email>` |

For a local setup without a database server or S3 storage, set `DB_CONNECTION=sqlite` and `SEAWEEDFS_DRIVER=local` in `.env`. [CONTRIBUTING.md](../CONTRIBUTING.md) shows the exact lines.

The API is described in [openapi.yaml](openapi.yaml) and in [docs/API_CONTRACT.md](../docs/API_CONTRACT.md).
