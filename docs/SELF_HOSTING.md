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

1. Copy the settings and fill them in. Docker Compose reads the `.env` file **next to `docker-compose.yml`**:
   ```sh
   cp .env.example .env
   ```
   Set at least `APP_KEY` (`echo "base64:$(openssl rand -base64 32)"`), `APP_URL` (the address the phones use, for example `https://photos.example.com`) and a strong `DB_PASSWORD`. Compose stops with a clear message when `APP_KEY` or `DB_PASSWORD` is missing. The containers run with `APP_ENV=production` and `APP_DEBUG=false` unless you change that. `kinlore-api/.env` is optional in Docker; it can hold extra Laravel settings (mail, for example).
2. Start everything:
   ```sh
   docker compose up -d --build
   ```
3. Check it: `curl http://localhost/api/health` returns `{"status":"ok",...}`.
4. Open `APP_URL` in a browser and create the admin account (see "First admin").

Settings you can put in a `.env` file next to `docker-compose.yml`:

| Variable | Default | Meaning |
|---|---|---|
| `APP_PORT` / `APP_SSL_PORT` | `80` / `443` | Host ports of the API. |
| `DB_PORT` | `5432` | Host port of PostgreSQL. |
| `REDIS_FORWARD_PORT` | `6379` | Host port of Redis. |
| `SEAWEEDFS_S3_PORT` | `8333` | Host port of the SeaweedFS S3 API. |
| `API_ENV_FILE` | `./kinlore-api/.env` | Settings file for the API container. It may be missing. |
| `BIND_ADDRESS` | `127.0.0.1` | Host address for the PostgreSQL, Redis and S3 ports. They are reachable only from this machine by default. |
| `TRUSTED_PROXIES` | private networks | Reverse proxies whose `X-Forwarded-*` headers are trusted, so links use `https`. |

**HTTPS.** Phones need HTTPS for a server on the internet. The API container serves plain HTTP on port 80. Choose one:
- **Bundled Caddy proxy (simplest).** See "Reverse proxy" below.
- **Your own reverse proxy** (Caddy, nginx, Traefik, Nginx Proxy Manager). See "Reverse proxy" below.
- **Bundled certbot.** Get a certificate for your host name into the `letsencrypt` volume. When `/etc/letsencrypt/live/<host of APP_URL>/fullchain.pem` exists, the API container switches to HTTPS by itself at the next start. Set `SERVER_NAME` if the host differs from `APP_URL`.

**Security defaults.** PostgreSQL, Redis and the SeaweedFS S3 port listen only on `127.0.0.1`. Change the SeaweedFS keys in `kinlore-api/docker/s3.json` together with `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` before you set `BIND_ADDRESS=0.0.0.0`.

**Upgrading from PostgreSQL 17 or older.** The PostgreSQL 18 image keeps its data in `/var/lib/postgresql/18/...`. An old data volume does not start with it. Back up with `pg_dump`, start a fresh volume and restore. Do not delete the old volume before the restore works.

## Reverse proxy

Kinlore works behind any reverse proxy that terminates TLS. The proxy must:
- forward `Host`, `X-Forwarded-Proto`, `X-Forwarded-Host` and `X-Forwarded-For`;
- allow large request bodies (videos) and long timeouts (30 minutes is safe).

The API trusts these headers from private networks by default (`TRUSTED_PROXIES`). For a proxy on a public address, set `TRUSTED_PROXIES` to its IP. Without trusted headers, video and export links use `http`, and iPhones refuse them. Set `APP_URL` to the public `https` address.

### Bundled Caddy (Docker)

Caddy gets and renews a Let's Encrypt certificate by itself. Your domain must point at the server, and ports 80 and 443 must be open.

```ini
# .env next to docker-compose.yml
KINLORE_DOMAIN=photos.example.com
APP_URL=https://photos.example.com
APP_PORT=8080              # move the API off port 80, Caddy takes 80 and 443
APP_SSL_PORT=8443
APP_BIND_ADDRESS=127.0.0.1 # reach the API only through Caddy
```

```sh
docker compose --profile proxy up -d
```

For a local test, use `KINLORE_DOMAIN=localhost`; Caddy then uses its own certificate, which your browser and phone do not trust.

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name photos.example.com;
    # ssl_certificate ... ; ssl_certificate_key ... ;

    client_max_body_size 0;
    proxy_read_timeout 1800s;
    proxy_send_timeout 1800s;
    proxy_request_buffering off;

    location / {
        proxy_pass http://127.0.0.1:8080;   # APP_PORT of the API
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

### Traefik (Docker labels on the `api` service)

```yaml
labels:
  - traefik.enable=true
  - traefik.http.routers.kinlore.rule=Host(`photos.example.com`)
  - traefik.http.routers.kinlore.entrypoints=websecure
  - traefik.http.routers.kinlore.tls.certresolver=letsencrypt
  - traefik.http.services.kinlore.loadbalancer.server.port=80
```
Traefik must share a Docker network with the API. Raise its `respondingTimeouts` for long uploads.

### Bare metal

Run any of the proxies above in front of PHP-FPM or `php artisan serve`, with the same headers. Set `TRUSTED_PROXIES=127.0.0.1` when the proxy runs on the same machine.

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
4. Create the tables:
   ```sh
   php artisan migrate --force
   php artisan db:seed --force   # milestones; also the admin when SEED_USER_* is set
   ```
   Then open `APP_URL` in a browser to create the admin account (see "First admin").
5. Serve it with nginx or Apache and PHP-FPM, with `kinlore-api/public` as the web root. Use `docker/kinlore-api.conf` as an nginx example. Allow large uploads: `client_max_body_size 0;` in nginx, and `upload_max_filesize=512M`, `post_max_size=520M` in PHP (see `docker/zz-uploads.ini`).
6. Run the queue worker as a service (systemd example):
   ```ini
   [Service]
   WorkingDirectory=/srv/kinlore/kinlore-api
   ExecStart=/usr/bin/php artisan queue:work --queue=video-processing,default --tries=3 --timeout=1800
   Restart=always
   User=www-data
   ```
   One worker is enough. If you run more, keep `DB_QUEUE_RETRY_AFTER` (default 3700 seconds) longer than the longest job.
   With `QUEUE_CONNECTION=sync` no worker is needed, but uploads of long videos then wait for the conversion.

For a quick local test without a web server: `php artisan serve --host 0.0.0.0 --port 8000`, and run `php artisan queue:work` in a second terminal. Put the settings in `.env`: `artisan serve` does not pass most shell environment variables on to its workers.

Check any running instance with the end-to-end script (it creates test data, so use a test instance): `scripts/smoke-test.sh http://localhost:8000 <admin email> <admin password>`.

## First admin

The admin account is created **in a web browser**, not in the app (like Immich or Actual Budget):

1. Start the server (Docker or bare metal).
2. Open the server address (`APP_URL`, for example `https://photos.example.com`) in a browser. An empty instance sends you to `/setup`.
3. Enter your name, email and password. The page then shows the server address and email to use in the app.

Optional:
- **Setup token.** Set `SETUP_TOKEN` to a long random value. The setup page then asks for it, so a stranger cannot take an instance that is online before you finish setup. Recommended when the server is reachable from the internet during setup.
- **Seeded admin.** Set `SEED_USER_NAME`, `SEED_USER_EMAIL` and `SEED_USER_PASSWORD` before the first start, for automated installs. The web setup is then skipped. Docker runs the seeder at every start; it creates the user only once.

## Forgotten password

- **A member forgot it:** the admin opens Settings → Members → the member → "Set new password" in the app. The member is signed out on all devices and signs in with the new password.
- **The admin forgot it**, or you prefer the server: run the command below. It asks for the new password and signs that user out everywhere.
  ```sh
  docker compose exec api php artisan kinlore:reset-password ann@example.com   # Docker
  php artisan kinlore:reset-password ann@example.com                           # bare metal, in kinlore-api
  ```

## Connect the app

In the app, enter the server address (`APP_URL`), then the admin's email and password. The address may include a port, for example `192.168.1.20:8088` or `photos.example.com:8443`. Without `http://` or `https://`, the app tries HTTPS first and then HTTP. Plain HTTP is for the home network: iPhones allow it only for local addresses, Android allows it for any address. Use HTTPS for a server on the internet. Set `APP_URL` with the same port, because invite and video links are built from it. If the server is not set up yet, the app says so and offers to open the setup page in the browser. The admin invites the family from Settings → Members. The app shares a link such as `https://photos.example.com/invite/<token>`. The page opens the app with the server address and the invitation already filled in.

## Backups

Back up three things: the database (`pg_dump`, or the SQLite file), the media (the SeaweedFS volumes, or `MEDIA_ROOT`), and `kinlore-api/.env` (it holds `APP_KEY`). The admin can also make a full ZIP export of all memories from the app (Settings → Export).
