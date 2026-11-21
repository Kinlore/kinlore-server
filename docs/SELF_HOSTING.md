# Self-hosting Kinlore

You can run the Kinlore API in Docker (recommended) or directly on a server ("bare metal"). Both use the same code and the same settings.

## What the server needs

| Part | Docker | Bare metal |
|---|---|---|
| PHP 8.2+ with `pdo_pgsql` or `pdo_sqlite`, `gd`, `exif`, `zip`, `intl`, `mbstring`, `bcmath` | in the image | install it |
| FFmpeg (video to HLS) | in the image | install it, set `FFMPEG_BINARIES` and `FFPROBE_BINARIES` |
| Database | PostgreSQL 18 container | PostgreSQL, or SQLite for a small family |
| Media storage | SeaweedFS containers (S3 API) | local disk (`SEAWEEDFS_DRIVER=local`), or any S3 service |
| Cache and rate limits | Redis container | Redis, or the database/file cache |
| Queue worker (videos, exports) | runs inside the API container | run `php artisan queue:work --queue=video-processing,default` |

## Option 1: Docker

1. Copy the settings and fill them in:
   ```sh
   cp kinlore-api/.env.example kinlore-api/.env
   ```
   Set at least `APP_KEY` (make one with `php artisan key:generate --show`, or `echo "base64:$(openssl rand -base64 32)"`), `APP_URL` (the address the phones use, for example `https://photos.example.com`), and a strong `DB_PASSWORD`.
2. Choose how the first admin is made (see "First admin" below).
3. Start everything:
   ```sh
   docker compose up -d --build
   ```
4. Check it: `curl http://localhost/api/health` returns `{"status":"ok",...}`.

Settings you can put in a `.env` file next to `docker-compose.yml`:

| Variable | Default | Meaning |
|---|---|---|
| `APP_PORT` / `APP_SSL_PORT` | `80` / `443` | Host ports of the API. |
| `DB_PORT` | `5432` | Host port of PostgreSQL. |
| `REDIS_FORWARD_PORT` | `6379` | Host port of Redis. |
| `SEAWEEDFS_S3_PORT` | `8333` | Host port of the SeaweedFS S3 API. |
| `API_ENV_FILE` | `./kinlore-api/.env` | Settings file for the API container. It may be missing. |

**HTTPS.** The container serves plain HTTP on port 80. You have two choices:
- Put your own reverse proxy (Caddy, Traefik, nginx) in front, which handles the certificate. This is the simplest.
- Or use the bundled certbot: get a certificate for your host name into the `letsencrypt` volume. When `/etc/letsencrypt/live/<host of APP_URL>/fullchain.pem` exists, the container switches to HTTPS by itself at the next start. Set `SERVER_NAME` if the host differs from `APP_URL`.

**Upgrading from PostgreSQL 17 or older.** The PostgreSQL 18 image keeps its data in `/var/lib/postgresql/18/...`. An old data volume does not start with it. Back up with `pg_dump`, start a fresh volume and restore. Do not delete the old volume before the restore works.

## Option 2: Bare metal

1. Install PHP, Composer, FFmpeg and a database (see the table above).
2. Install and configure:
   ```sh
   cd kinlore-api
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
3. Edit `.env`. A small, simple setup with no extra services:
   ```ini
   APP_URL=https://photos.example.com
   DB_CONNECTION=sqlite
   DB_DATABASE=/var/lib/kinlore/database.sqlite
   SEAWEEDFS_DRIVER=local
   MEDIA_ROOT=/var/lib/kinlore/media
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```
   For PostgreSQL, set `DB_CONNECTION=pgsql` and the `DB_*` values. For S3 storage, keep `SEAWEEDFS_DRIVER=s3` and set `SEAWEEDFS_ENDPOINT`, `SEAWEEDFS_KEY`, `SEAWEEDFS_SECRET` and `SEAWEEDFS_BUCKET`.
4. Create the tables and the first admin:
   ```sh
   php artisan migrate --force
   php artisan db:seed --force   # only creates the admin when SEED_USER_* is set
   ```
5. Serve it with nginx or Apache and PHP-FPM, with `kinlore-api/public` as the web root. Use `docker/kinlore-api.conf` as an nginx example. Allow large uploads: `client_max_body_size 0;` in nginx, and `upload_max_filesize=512M`, `post_max_size=520M` in PHP (see `docker/zz-uploads.ini`).
6. Run the queue worker as a service (systemd example):
   ```ini
   [Service]
   WorkingDirectory=/srv/kinlore/kinlore-api
   ExecStart=/usr/bin/php artisan queue:work --queue=video-processing,default --tries=3 --timeout=1800
   Restart=always
   User=www-data
   ```
   With `QUEUE_CONNECTION=sync` no worker is needed, but uploads of long videos then wait for the conversion.

For a quick local test without a web server: `php artisan serve --host 0.0.0.0 --port 8000`, and run `php artisan queue:work` in a second terminal. Put the settings in `.env`: `artisan serve` does not pass most shell environment variables on to its workers.

Check any running instance with the end-to-end script (it creates test data, so use a test instance): `scripts/smoke-test.sh http://localhost:8000 <admin email> <admin password>`.

## First admin

Pick one:
- **Seeded admin (recommended).** Set `SEED_USER_NAME`, `SEED_USER_EMAIL` and `SEED_USER_PASSWORD` before the first start. Docker runs the seeder at every start; it creates the user only once.
- **Setup token.** Set `SETUP_TOKEN` to a long random value. The first person who registers in the app must type it. This stops a stranger from taking an instance that is online before you register.
- **Open first sign-up.** With neither set, the first person who registers becomes the admin. Use this only on a private network.

## Connect the app

In the app, enter the server address (`APP_URL`). The admin invites the family from Settings → Members. The app shares a link such as `https://photos.example.com/invite/<token>`. The page opens the app with the server address and the invitation already filled in.

## Backups

Back up three things: the database (`pg_dump`, or the SQLite file), the media (the SeaweedFS volumes, or `MEDIA_ROOT`), and `kinlore-api/.env` (it holds `APP_KEY`). The admin can also make a full ZIP export of all memories from the app (Settings → Export).
