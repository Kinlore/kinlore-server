# Install with Docker

This guide installs the Kinlore server with Docker Compose. Docker Compose starts several containers from one file, `docker-compose.yml`.

## What you get

| Part | Container |
|---|---|
| API: Caddy (web server), PHP-FPM and two queue workers (one for videos, one for exports) | `kinlore-api` |
| Database | PostgreSQL 18 (`kinlore-postgres`) |
| Media storage with an S3 interface | SeaweedFS (`kinlore-seaweedfs-master`, `kinlore-seaweedfs-volume`, `kinlore-seaweedfs-filer`) |
| Cache and rate limits | Redis (`kinlore-redis`) |

A queue worker is a background process. It converts videos and builds exports, so the app does not have to wait.

Only the API container is reachable from outside. PostgreSQL, Redis and SeaweedFS have no port on the host.

## What you need

- A Linux machine with Docker and Docker Compose. See [Requirements](/self-hosting/requirements).
- `git` and `openssl`.
- For use on the internet: a host name that points at the machine.

## Steps

1. Get the code:
   ```sh
   git clone https://github.com/Kinlore/kinlore-server.git
   cd kinlore-server
   ```
2. Copy the settings file. Docker Compose reads the file `.env` **next to `docker-compose.yml`**:
   ```sh
   cp .env.example .env
   ```
3. Make the secret values. Each command prints one value:
   ```sh
   echo "base64:$(openssl rand -base64 32)"   # APP_KEY
   openssl rand -hex 32                       # DB_PASSWORD
   openssl rand -hex 32                       # SEAWEEDFS_KEY
   openssl rand -hex 32                       # SEAWEEDFS_SECRET
   ```
4. Open `.env` (for example with `nano .env`) and set the five required values:
   ```ini
   APP_KEY=base64:...
   APP_URL=https://photos.example.com
   DB_PASSWORD=...
   SEAWEEDFS_KEY=...
   SEAWEEDFS_SECRET=...
   ```
   `APP_URL` is the address that the phones use. Invitation and video links are built from it.
5. Choose how the server gets HTTPS:
   - **Built in:** set `KINLORE_DOMAIN=photos.example.com`. Ports 80 and 443 must be free and open.
   - **Your own reverse proxy:** leave `KINLORE_DOMAIN` empty. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).
   - **Home network only:** leave `KINLORE_DOMAIN` empty and set `APP_URL` to the local address, for example `http://192.168.1.20`.
6. Start the stack:
   ```sh
   docker compose up -d --build
   ```
   The API container prepares the database at each start. Then it starts the web server and the workers.
7. Create the admin account. See [Create the first admin](/self-hosting/first-admin).

Every other setting is optional. They are listed in [Configuration](/self-hosting/configuration).

## Check that it worked

```sh
docker compose ps
curl -fsS http://localhost/api/health
```

- All containers show `running`. The API and the database show `healthy` after a short time.
- `curl` prints a line that starts with `{"status":"ok"`.
- With `KINLORE_DOMAIN` set, `curl -fsS https://photos.example.com/api/health` gives the same result.

To test every feature, run the end-to-end test. It creates test data, so run it before the family starts. See [Commands](/reference/commands#end-to-end-test).

## When it does not work

- `docker compose up` stops with `Set APP_KEY in the .env file next to docker-compose.yml`, or the same message for another name: that value is empty in `.env`.
- The API container restarts again and again: read its log with `docker compose logs api`. After about two minutes without a database, it stops and prints the database error. A wrong `DB_PASSWORD` is a common cause.
- More problems and fixes are in [Troubleshooting](/self-hosting/troubleshooting).

## Reach a database or storage port from the host

The database, Redis and the S3 port of SeaweedFS have no port on the host. So the stack also starts on a host that runs its own PostgreSQL or Redis.

To reach one of them from the host, for example with a database tool, make a file `docker-compose.override.yml` next to `docker-compose.yml`. Docker Compose reads it by itself. Keep only the services you need, and pick a free host port:

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

Then run `docker compose up -d`. The address `127.0.0.1` keeps the ports reachable only from this machine.

## Next steps

- [Create the first admin](/self-hosting/first-admin)
- [Backup and restore](/self-hosting/backup-and-restore)
- [Maintenance](/self-hosting/maintenance)
