# Self-hosting Kinlore

You can run the Kinlore server in two ways. Both use the same code and the same settings.

- **Docker (recommended):** this guide. One command starts the API, PostgreSQL, the media storage and Redis.
- **Directly on a Linux host, without Docker:** see **[BARE_METAL.md](BARE_METAL.md)**. It has every command and configuration file, and a script that tests the whole install.

## What the Docker setup contains

| Part | Container |
|---|---|
| API: Caddy (web server), PHP-FPM and two queue workers (one for videos, one for exports) | `kinlore-api` |
| Database | PostgreSQL 18 |
| Media storage (S3 API) | SeaweedFS (three containers) |
| Cache and rate limits | Redis |

## Option 1: Docker

1. Copy the settings and fill them in. Docker Compose reads the `.env` file **next to `docker-compose.yml`**:
   ```sh
   cp .env.example .env
   ```
   Set at least `APP_KEY` (`echo "base64:$(openssl rand -base64 32)"`), `APP_URL` (the address the phones use, for example `https://photos.example.com`) a strong `DB_PASSWORD`, and the media storage keys `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` (make each one with `openssl rand -hex 32`). Compose stops with a clear message when one of these is missing. The containers run with `APP_ENV=production` and `APP_DEBUG=false` unless you change that. `kinlore-api/.env` is optional in Docker; it can hold extra Laravel settings (mail, for example).
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
| `KINLORE_DOMAIN` | empty | Your public host name. When set, the API container serves HTTPS with its own certificate. |
| `APP_BIND_ADDRESS` | `0.0.0.0` | Host address of the API ports. `127.0.0.1` when a proxy on this machine is the only way in. |
| `API_ENV_FILE` | `./kinlore-api/.env` | Settings file for the API container. It may be missing. |
| `TRUSTED_PROXIES` | private networks | Reverse proxies whose `X-Forwarded-*` headers are trusted, so links use `https`. |
| `LOG_STACK` | `daily` | Log files of the API in the `api_storage` volume: one file per day, kept 14 days. |

**HTTPS.** Phones need HTTPS for a server on the internet. Choose one:
- **Built in (simplest).** Set `KINLORE_DOMAIN` to your host name. Caddy inside the API container then gets and renews a certificate by itself. See "HTTPS and reverse proxies" below.
- **Your own reverse proxy** on the host or in another container (Caddy, nginx, Traefik, Nginx Proxy Manager). Leave `KINLORE_DOMAIN` empty; the API container then serves plain HTTP. See "HTTPS and reverse proxies" below.

**Security defaults.** PostgreSQL, Redis and the SeaweedFS S3 port are not published on the host; only the API container uses them. So the stack also starts on a host that runs its own PostgreSQL or Redis. To reach one of them from the host (for example, a database tool, or a bare-metal API that uses this SeaweedFS), add a `docker-compose.override.yml` next to `docker-compose.yml`. Docker Compose reads it by itself. Keep only the services you need, and pick a free host port:

```yaml
services:
  seaweedfs-filer:
    ports:
      - "127.0.0.1:8333:8333"
  postgres:
    ports:
      - "127.0.0.1:5432:5432"   # use 5433:5432 if the host runs its own PostgreSQL
  redis:
    ports:
      - "127.0.0.1:6379:6379"
```

Then run `docker compose up -d`. `127.0.0.1` keeps the ports reachable only from this machine.

**Logs.** Docker keeps at most three log files of 10 MB for each container (`docker compose logs api`). The API also writes its own log files in the `api_storage` volume, one per day, kept 14 days.

**Small servers (1 to 2 GB of memory).** PHP-FPM runs up to 20 workers, and each may use up to 1 GB while it makes the previews of a large photo. On a small server this can use more memory than the machine has. The symptom: during a large photo upload, the kernel stops a process (`dmesg` on the host shows "Out of memory: Killed process"), and the upload fails or the API restarts. Do one or both:
- Lower the number of PHP-FPM workers. Make a file `fpm-small.conf` next to `docker-compose.yml` with the two lines `[www]` and `pm.max_children = 4`. Add it to `docker-compose.override.yml`, then run `docker compose up -d`. Fewer uploads can then run at the same time.
  ```yaml
  services:
    api:
      volumes:
        - ./fpm-small.conf:/usr/local/etc/php-fpm.d/zz-kinlore-fpm.conf:ro
  ```
- Add swap on the host: `sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile`, and add the line `/swapfile none swap sw 0 0` to `/etc/fstab` so it stays after a restart.

## Update

Make a backup first (see "Backups"). Read the "Upgrade notes" of each release in [CHANGELOG.md](../CHANGELOG.md) before you update.

```sh
git pull
docker compose up -d --build
```

The API container applies the database changes when it starts. A video conversion or an export that is running gets up to one hour to finish before the old container stops.

**From 1.0.0 to 1.1.0.** Version 1.0.0 used the fixed media storage keys `admin`/`admin`. Add `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` to `.env` (make each one with `openssl rand -hex 32`) before you run `docker compose up -d --build`. Compose stops with a clear message when one is missing. The stored media stays as it is; only the keys change.

## Versions

| Part | Version | Notes |
|---|---|---|
| Laravel | 13 | bug fixes until the third quarter of 2027, security fixes until March 2028 |
| PHP | 8.5 | in the image; supported until the end of 2029 |
| PostgreSQL | 18 | newest patch at each `docker compose pull`; supported until November 2030 |
| SeaweedFS | 4.55 | pinned, because it holds all media; read its release notes before you raise it |
| Redis | 8.10 | cache and rate limits only; safe to raise, never to lower (an older Redis cannot read the data of a newer one) |
| Caddy | 2.11 | comes with the image |

Kinlore does not support PHP 8.6 or PostgreSQL 19 yet. The test workflow also runs the unit and feature tests on PHP 8.6, as a look ahead.

### Move PostgreSQL to a new major version

A new major version cannot read the data folder of an old one, and its image refuses to start on a volume that holds old data. Move the data with a dump and a restore. The example moves from 18 to 19; the steps are tested.

```sh
# 1. Save everything while the old version runs. Keep this file until the new version works.
docker compose exec -T postgres pg_dumpall -U kinlore_user > kinlore-db.sql
docker compose down
```

2. In `docker-compose.yml`, change `postgres:18-alpine` to `postgres:19-alpine`, and rename the volume `postgres_data` to `postgres_data_19` in both places (the `postgres` service and the `volumes` list at the end). The old volume stays untouched.

```sh
# 3. Start the new, empty database and load the data
docker compose up -d postgres
docker compose exec -T postgres psql -U kinlore_user -d postgres < kinlore-db.sql
# 4. Start the rest and check
docker compose up -d
curl -fsS http://localhost/api/health
```

The restore prints `role "kinlore_user" already exists`; that is normal. Open the app and check that your memories are there. Only then remove the old volume: `docker volume ls` shows it, with a name that ends in `_postgres_data`.

The same steps apply to a data volume from PostgreSQL 17 or older.

## HTTPS and reverse proxies

The web server inside the API container is Caddy. It has two modes, chosen by `KINLORE_DOMAIN`.

### Built-in HTTPS (no extra proxy)

Caddy gets and renews a Let's Encrypt certificate by itself. Your host name must point at the server, and ports 80 and 443 must be open. Leave `APP_PORT` and `APP_SSL_PORT` at 80 and 443.

```ini
# .env next to docker-compose.yml
KINLORE_DOMAIN=photos.example.com
APP_URL=https://photos.example.com
```

```sh
docker compose up -d
```

The certificate is kept in the `caddy_data` volume. For a local test, use `KINLORE_DOMAIN=localhost`; Caddy then makes its own certificate, which your browser and phone do not trust.

### Your own reverse proxy

Leave `KINLORE_DOMAIN` empty. The API container then serves plain HTTP, and your proxy adds HTTPS. Move the API off ports 80 and 443 and make it reachable only from this machine:

```ini
# .env next to docker-compose.yml
APP_URL=https://photos.example.com
APP_PORT=8080
APP_SSL_PORT=8443          # not used in this mode; moved so 443 stays free for your proxy
APP_BIND_ADDRESS=127.0.0.1
```

Your proxy must:
- forward `Host`, `X-Forwarded-Proto`, `X-Forwarded-Host` and `X-Forwarded-For`;
- allow large request bodies (videos) and long timeouts: no limit for the whole request, or at least several hours, because uploads of large videos and export downloads can take that long on a slow connection. A limit on the time between two reads (for example nginx `proxy_read_timeout`) can stay at 30 minutes.

The API trusts these headers from private networks by default (`TRUSTED_PROXIES`), so a device on your home network could also send them. To close that, set `TRUSTED_PROXIES` to the address your proxy connects from (for a proxy on the Docker host, the Docker gateway, for example `172.18.0.1`; find it with `docker network inspect kinlore_kinlore-network`). For a proxy on a public address, set `TRUSTED_PROXIES` to its IP. On some hosts the API container sees every client as the Docker gateway; with the default, a client there can then set its own address with a forged `X-Forwarded-For` header. So set `TRUSTED_PROXIES` to the address of your proxy whenever you know it. Without trusted headers, video and export links use `http`, and iPhones refuse them.

### Caddy on the host

```
photos.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

Caddy sends the needed headers by itself and has no body size limit.

### nginx on the host

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

## Without Docker

The full guide for an install directly on a Linux host is **[BARE_METAL.md](BARE_METAL.md)**. Check any running instance, Docker or not, with the end-to-end script. It creates test data, so use it before the family starts: `scripts/smoke-test.sh https://photos.example.com <admin email> <admin password>`.

## First admin

The admin account is created **in a web browser**, not in the app (like Immich or Actual Budget):

1. Start the server (Docker, or the host install of BARE_METAL.md).
2. Open the server address (`APP_URL`, for example `https://photos.example.com`) in a browser. An empty instance sends you to `/setup`. The form asks for the setup token (see below). The form also asks for a family name. It is optional, and the admin can change it later in the app.
3. Enter your name, email and password. The page then shows the server address and email to use in the app.

Settings for this step:
- **Setup token.** The setup page always asks for it, so a stranger cannot take an instance that is online before you finish setup. When `SETUP_TOKEN` is set, its value is the token. When it is empty, the server makes a random token. Docker prints it in the log at start: `docker compose logs api | grep "Setup token"`. You can also print it with a command:
  ```sh
  docker compose exec api php artisan kinlore:setup-token   # Docker
  sudo -u www-data php artisan kinlore:setup-token          # host install, in kinlore-api
  ```
  The generated token is deleted when the admin account exists.
- **Seeded admin.** Set `SEED_USER_NAME`, `SEED_USER_EMAIL` and `SEED_USER_PASSWORD` before the first start, for automated installs. The web setup is then skipped. Docker runs the seeder at every start; it creates the admin only while the server has no user.

## Forgotten password

- **A member forgot it:** the admin opens Settings → Members → the member → "Set new password" in the app. The member is signed out on all devices and signs in with the new password.
- **The admin forgot it**, or you prefer the server: run the command below. It asks for the new password and signs that user out everywhere.
  ```sh
  docker compose exec api php artisan kinlore:reset-password ann@example.com   # Docker
  sudo -u www-data php artisan kinlore:reset-password ann@example.com          # host install, in kinlore-api
  ```

## Connect the app

In the app, enter the server address (`APP_URL`), then the admin's email and password. The address may include a port, for example `192.168.1.20:8088` or `photos.example.com:8443`. Without `http://` or `https://`, the app tries HTTPS first and then HTTP. Plain HTTP is for the home network: iPhones allow it only for local addresses, Android allows it for any address. Use HTTPS for a server on the internet. Set `APP_URL` with the same port, because invite and video links are built from it. If the server is not set up yet, the app says so and offers to open the setup page in the browser. The admin invites the family from Settings → Members. The app shares a link such as `https://photos.example.com/invite/<token>`. The page opens the app with the server address and the invitation already filled in.

## Backups

Back up three things: the database, the media, and the settings file. The admin can also make a full ZIP export of all memories in the app (Settings → Export), but only a backup brings back the server with its accounts.

**Docker.** The settings file is the `.env` **next to `docker-compose.yml`**: it holds `APP_KEY`, `DB_PASSWORD` and the SeaweedFS keys. Without it, the backup cannot be used. Save `kinlore-api/.env` too, if you made one. The media is in the three SeaweedFS volumes. The other volumes hold logs, caches, finished exports and certificates, which the server makes again.

> These commands were checked against `docker-compose.yml` by reading only. They were not run. Test a restore on a second machine before you rely on them.

Make a backup. Run the commands in the folder with `docker-compose.yml`:

```sh
mkdir -m 700 -p backup
# 1. Database, while the stack runs
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > backup/kinlore-db.sql
# 2. Media: stop the stack, so SeaweedFS writes nothing during the copy
docker compose stop
for c in seaweedfs-master seaweedfs-volume seaweedfs-filer; do
  docker run --rm --volumes-from "kinlore-$c" -v "$PWD/backup:/backup" alpine tar czf "/backup/$c.tar.gz" -C /data .
done
docker compose start
# 3. Settings
cp .env backup/env
```

The database is saved first, so the media copy is never older than the database. Copy the `backup` folder to another disk or machine.

The media copy stops the stack for a few minutes. A copy of the volumes while SeaweedFS runs can catch a file in the middle of a write, so the stop is the safe way.

Restore on a fresh install: a new clone of this repository, on a machine where these volumes do not exist yet. Put the `backup` folder next to `docker-compose.yml`, then:

```sh
cp backup/env .env
# Make the containers and their empty volumes; nothing starts
docker compose create --build
for c in seaweedfs-master seaweedfs-volume seaweedfs-filer; do
  docker run --rm --volumes-from "kinlore-$c" -v "$PWD/backup:/backup" alpine tar xzf "/backup/$c.tar.gz" -C /data
done
docker compose up -d --wait postgres
docker compose exec -T postgres sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < backup/kinlore-db.sql
docker compose up -d
curl -fsS http://localhost/api/health
```

Open the app and check that your memories and their photos are there.

**Host install.** `deploy/bare-metal/kinlore-backup.sh` saves the SQLite database, the media folder (`MEDIA_ROOT`) and `kinlore-api/.env`, and keeps 30 days of history. See "Backups" in [BARE_METAL.md](BARE_METAL.md).
