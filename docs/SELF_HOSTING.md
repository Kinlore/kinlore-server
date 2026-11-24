# Self-hosting Kinlore

You can run the Kinlore server in two ways. Both use the same code and the same settings.

- **Docker (recommended):** this guide. One command starts the API, PostgreSQL, the media storage and Redis.
- **Directly on a Linux host, without Docker:** see **[BARE_METAL.md](BARE_METAL.md)**. It has every command and configuration file, and a script that tests the whole install.

## What the Docker setup contains

| Part | Container |
|---|---|
| API: Caddy (web server), PHP-FPM and the queue worker for videos and exports | `kinlore-api` |
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
| `DB_PORT` | `5432` | Host port of PostgreSQL. |
| `REDIS_FORWARD_PORT` | `6379` | Host port of Redis. |
| `KINLORE_DOMAIN` | empty | Your public host name. When set, the API container serves HTTPS with its own certificate. |
| `APP_BIND_ADDRESS` | `0.0.0.0` | Host address of the API ports. `127.0.0.1` when a proxy on this machine is the only way in. |
| `API_ENV_FILE` | `./kinlore-api/.env` | Settings file for the API container. It may be missing. |
| `BIND_ADDRESS` | `127.0.0.1` | Host address for the PostgreSQL and Redis ports. They are reachable only from this machine by default. |
| `TRUSTED_PROXIES` | private networks | Reverse proxies whose `X-Forwarded-*` headers are trusted, so links use `https`. |

**HTTPS.** Phones need HTTPS for a server on the internet. Choose one:
- **Built in (simplest).** Set `KINLORE_DOMAIN` to your host name. Caddy inside the API container then gets and renews a certificate by itself. See "HTTPS and reverse proxies" below.
- **Your own reverse proxy** on the host or in another container (Caddy, nginx, Traefik, Nginx Proxy Manager). Leave `KINLORE_DOMAIN` empty; the API container then serves plain HTTP. See "HTTPS and reverse proxies" below.

**Security defaults.** PostgreSQL and Redis listen only on `127.0.0.1`. The SeaweedFS S3 port is not published on the host; only the API container uses it. To reach it from the host (for example, for a bare-metal API that uses this SeaweedFS), add a `docker-compose.override.yml` next to `docker-compose.yml`:

```yaml
services:
  seaweedfs-filer:
    ports:
      - "127.0.0.1:8333:8333"
```

**Upgrade from a version before the storage keys.** Older versions used the fixed keys `admin`/`admin`. Add `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` to `.env`, then run `docker compose up -d`. The stored media stays as it is; only the keys change.

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

The API trusts these headers from private networks by default (`TRUSTED_PROXIES`), so a device on your home network could also send them. To close that, set `TRUSTED_PROXIES` to the address your proxy connects from (for a proxy on the Docker host, the Docker gateway, for example `172.18.0.1`; find it with `docker network inspect kinlore_kinlore-network`). For a proxy on a public address, set `TRUSTED_PROXIES` to its IP. Without trusted headers, video and export links use `http`, and iPhones refuse them.

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
2. Open the server address (`APP_URL`, for example `https://photos.example.com`) in a browser. An empty instance sends you to `/setup`.
3. Enter your name, email and password. The page then shows the server address and email to use in the app.

Optional:
- **Setup token.** Set `SETUP_TOKEN` to a long random value. The setup page then asks for it, so a stranger cannot take an instance that is online before you finish setup. Recommended when the server is reachable from the internet during setup.
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

Back up three things: the database (`pg_dump`, or the SQLite file), the media (the SeaweedFS volumes, or `MEDIA_ROOT`), and `kinlore-api/.env` (it holds `APP_KEY`). The admin can also make a full ZIP export of all memories from the app (Settings → Export).
