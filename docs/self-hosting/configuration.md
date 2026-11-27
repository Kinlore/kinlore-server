# Environment variables

The server reads its settings from environment variables. An environment variable is a name with a value, for example `APP_URL=https://photos.example.com`. This page lists every variable, with its meaning and its default.

::: warning After a change, recreate the containers
Run `docker compose up -d` in the folder of `docker-compose.yml`. Docker Compose then makes the changed containers again with the new values. `docker compose restart` is not enough: a restarted container keeps its old values.

On a host install, run `sudo -u www-data php artisan config:cache` in `/srv/kinlore/kinlore-api`, then `sudo systemctl restart kinlore-queue kinlore-queue-default`.
:::

## Where the variables are

| Install | File | Start from |
|---|---|---|
| Docker | `.env` in the folder of `docker-compose.yml` | `.env.example` |
| Host install | `kinlore-api/.env` | `deploy/bare-metal/env.example` |

::: details How Docker passes the variables
`docker-compose.yml` passes a fixed list of names from `.env` to the API container. This page lists these names in the groups from [Required](#required) to [Advanced](#advanced). A name that is not in that list has no effect in `.env`.

For the other names, use a second file: `kinlore-api/.env`. The API container reads it when it exists. Set `API_ENV_FILE` to use another path. A value from `.env` wins over the same name in `kinlore-api/.env`.
:::

::: danger Keep the settings file safe
The settings file holds `APP_KEY` and the passwords. Without it, a backup cannot be used. Save it with every backup. Never share it.
:::

## Required

`docker compose` stops with a message such as `Set APP_KEY in the .env file next to docker-compose.yml` when one of these is empty.

| Variable | Description | Default |
|---|---|---|
| `APP_KEY` | The secret key of the server. It protects sessions, signed links and encrypted values. Make it with `echo "base64:$(openssl rand -base64 32)"`. | none |
| `APP_URL` | The public address that the phones and invited people use, for example `https://kinlore.yourfamily.net`. Include a port if it is not 80 or 443. Invitation links, the invite page and the setup address are built from it. Replace the example value: see [APP_URL placeholder](#app-url-placeholder). | `http://localhost:28461` |
| `DB_PASSWORD` | The password of the database user. PostgreSQL gets it at its first start. Make it with `openssl rand -hex 32`. | none |
| `SEAWEEDFS_KEY` | The access key of the media storage. Make it with `openssl rand -hex 32`. | none |
| `SEAWEEDFS_SECRET` | The secret key of the media storage. Make it with `openssl rand -hex 32`. | none |

::: danger Do not change these after the first start
- A new `APP_KEY` stops every signed link and every encrypted value.
- PostgreSQL keeps the first `DB_PASSWORD`. A new value in `.env` does not change the database password, and the API can then not connect.
:::

### APP_URL placeholder {#app-url-placeholder}

The server treats `APP_URL` as a placeholder when it is empty, `localhost`, `127.0.0.1`, `::1`, `0.0.0.0`, or a host under `example.com`, `example.org` or `example.net`, such as `photos.example.com` from the example file. Then it builds the links from the address of the request. That is the address that the admin's phone used. This is only a fallback: on a phone that uses a local address, the links do not work for other people. The request address comes from the `Host` header, which the client sends. So with a placeholder, the public invite page and setup pages show back the host name of whoever asks. A shared cache in front of the server must therefore keep these pages apart by host name, or not store them. Set a real `APP_URL`, and none of this applies.

To see the problem, look at `checks.public_url` in `GET /api/health/detailed`, or at the warning of `php artisan kinlore:setup-token`. The app can also warn the admin when an invitation is made.

To fix it, set `APP_URL` to the public address, then run `docker compose up -d` (host install: `sudo -u www-data php artisan config:cache`). Invitations made before the change keep their old link. Revoke them and make new ones.

## Address and HTTPS

| Variable | Description | Default |
|---|---|---|
| `KINLORE_DOMAIN` | Your public host name. When it is set, Caddy in the API container gets a certificate and serves HTTPS. Empty: plain HTTP on `APP_PORT`. `deploy/docker/reverse-proxy.yml` always uses plain HTTP, because your proxy does HTTPS. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy). | empty |
| `APP_PORT` | The host port for HTTP. Set `80` for built-in HTTPS. | `28461` |
| `APP_SSL_PORT` | The host port for HTTPS, TCP and UDP. Used only with `KINLORE_DOMAIN`. Not used with `deploy/docker/reverse-proxy.yml`. Set `443` for built-in HTTPS. | `28462` |
| `APP_BIND_ADDRESS` | The host address of the API ports. `127.0.0.1` makes the ports reachable only from this machine. | `0.0.0.0` (all addresses); `127.0.0.1` with `deploy/docker/reverse-proxy.yml` |
| `APP_NAME` | The name on public pages, in the answer of `/api/auth/setup-status`, and in the export file. It is not the family name: the admin sets that in the app. | `Kinlore` |

## Reverse proxy and network

A reverse proxy is a web server in front of Kinlore, for example Caddy or nginx on the host. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).

| Variable | Description | Default |
|---|---|---|
| `COMPOSE_FILE` | The Compose files to use, separated by `:`. Docker Compose reads this name itself. Add `deploy/docker/reverse-proxy.yml` for a reverse proxy on this machine, and `deploy/docker/bind-mounts.yml` for data in a folder of your choice. Example: `docker-compose.yml:deploy/docker/reverse-proxy.yml`. | `docker-compose.yml` (and `docker-compose.override.yml` if it exists) |
| `TRUSTED_PROXIES` | The addresses of reverse proxies whose `X-Forwarded-*` headers the server trusts. A comma list of addresses and networks. `*` trusts every address. See [Trusted proxies](/self-hosting/https-and-reverse-proxy#trusted-proxies). | `127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16` (all private networks); the value of `KINLORE_GATEWAY` with `deploy/docker/reverse-proxy.yml` |
| `KINLORE_SUBNET` | With `deploy/docker/reverse-proxy.yml`: the fixed network of the containers. Change it only when it overlaps a network that you already use. | `172.31.250.0/24` |
| `KINLORE_GATEWAY` | With `deploy/docker/reverse-proxy.yml`: the address of the host in that network. A proxy on the host reaches the server from this address, so `TRUSTED_PROXIES` takes this value when it is not set. | `172.31.250.1` |

## Data locations

| Variable | Description | Default |
|---|---|---|
| `DATA_LOCATION` | With `deploy/docker/bind-mounts.yml`: the folder for all data, with one subfolder per service. Docker makes missing folders at the first start. Set it before the first start. See [Data locations](/self-hosting/data-locations). | `/srv/data/kinlore` |
| `API_ENV_FILE` | The path of an extra settings file for the API container. The file may be missing. | `./kinlore-api/.env` |

Without `deploy/docker/bind-mounts.yml`, the data is in Docker volumes.

## First admin

See [Create the first admin](/self-hosting/first-admin).

| Variable | Description | Default |
|---|---|---|
| `SETUP_TOKEN` | The token that the setup page asks for. Empty: the server makes a random token. | empty |
| `SEED_USER_NAME` | The name of an admin that the server creates by itself, without the setup page. | `Admin` |
| `SEED_USER_EMAIL` | The email address of that admin. With `SEED_USER_PASSWORD`, it turns this on. | empty |
| `SEED_USER_PASSWORD` | The password of that admin. | empty |

The server creates this admin only while it has no user.

## Database

Set these only before the first start. PostgreSQL makes the database and the user once.

| Variable | Description | Default |
|---|---|---|
| `DB_DATABASE` | The database name. | `kinlore_db` |
| `DB_USERNAME` | The database user. | `kinlore_user` |
| `DB_CONNECTION` | The database type. Keep `pgsql` (PostgreSQL). | `pgsql` |

## Storage

All photos and videos go through one storage. In Docker, this is SeaweedFS, a storage with an S3 interface. S3 is a common way to store files over the network.

| Variable | Description | Default |
|---|---|---|
| `FILESYSTEM_DISK` | The storage name in the code. Keep `seaweedfs`. | `seaweedfs` |
| `SEAWEEDFS_ENDPOINT` | The address of the S3 storage. | `http://kinlore-seaweedfs-filer:8333` |
| `SEAWEEDFS_BUCKET` | The bucket name. A bucket is a top-level folder of S3 storage. | `kinlore` |
| `SEAWEEDFS_URL` | A base address of the storage. The app does not use it; `php artisan seaweedfs:test` prints it. | `http://localhost:8333` |

## Logs

The log files are in `storage/logs` of the API. See [Maintenance](/self-hosting/maintenance#logs).

| Variable | Description | Default |
|---|---|---|
| `LOG_CHANNEL` | The log setup. Keep `stack`. | `stack` |
| `LOG_STACK` | `daily` writes one file per day. `single` writes one file that grows. | `daily` |
| `LOG_LEVEL` | The lowest level that is written. `debug` writes the most; `error` the least. | `warning` |

## Advanced

You do not need to change these.

| Variable | Description | Default |
|---|---|---|
| `APP_ENV` | The mode of the application. Keep `production`. | `production` |
| `APP_DEBUG` | Shows error details in answers. Keep `false`: details can show private data. | `false` |
| `CACHE_STORE` | Where the cache and the rate limits are kept: `redis` or `database`. | `redis` |
| `SESSION_DRIVER` | Where browser sessions of the setup page are kept. | `file` |
| `QUEUE_CONNECTION` | Where background jobs wait for a worker. Keep `database`: the workers read from it. | `database` |
| `SANCTUM_STATEFUL_DOMAINS` | Browser addresses that may sign in with a cookie. The app uses tokens, not cookies. | `localhost,localhost:8081` |

## Host install only

A host install reads all variables above from `kinlore-api/.env`, except the Docker-only ones (`KINLORE_DOMAIN`, `APP_PORT`, `APP_SSL_PORT`, `APP_BIND_ADDRESS`, `COMPOSE_FILE`, `KINLORE_SUBNET`, `KINLORE_GATEWAY`, `DATA_LOCATION`, `API_ENV_FILE`). Some defaults differ, and these variables come in addition. See [Host install without Docker](/self-hosting/bare-metal).

| Variable | Description | Default |
|---|---|---|
| `DB_CONNECTION` | `sqlite` (one file) or `pgsql` (PostgreSQL). | `sqlite` |
| `DB_DATABASE` | For SQLite, the path of the database file. For PostgreSQL, the database name. | `/var/lib/kinlore/database.sqlite` in `env.example` |
| `DB_HOST` | PostgreSQL only: the database server. | `127.0.0.1` |
| `DB_PORT` | PostgreSQL only: the port of the database server. | `5432` |
| `DB_USERNAME` | PostgreSQL only: the database user. | `root` |
| `DB_BUSY_TIMEOUT` | SQLite only: how long a request waits for a locked database, in milliseconds. | `5000` |
| `DB_JOURNAL_MODE` | SQLite only: the journal mode. Keep `wal`. | `wal` |
| `SEAWEEDFS_DRIVER` | `local` for a folder on the disk, `s3` for S3 storage. With `s3`, also set `SEAWEEDFS_ENDPOINT`, `SEAWEEDFS_KEY`, `SEAWEEDFS_SECRET` and `SEAWEEDFS_BUCKET`. | `s3`; `local` in `env.example` |
| `MEDIA_ROOT` | With `local`: the folder for photos and videos. | `storage/app/media`; `/var/lib/kinlore/media` in `env.example` |
| `SEAWEEDFS_REGION` | With `s3`: the region of the storage. | `us-east-1` |
| `SEAWEEDFS_USE_PATH_STYLE_ENDPOINT` | With `s3`: put the bucket name in the path of the address. Most self-hosted S3 storage needs `true`. In Docker, it is always `true`. | `true` |
| `CACHE_STORE` | `database` needs no Redis. | `database` |
| `SESSION_DRIVER` | Where browser sessions are kept. | `database` |
| `TRUSTED_PROXIES` | The web server on the same machine. | `127.0.0.1` in `env.example` |
| `LOG_STACK` | See [Logs](#logs). | `single`; `daily` in `env.example` |
| `LOG_LEVEL` | See [Logs](#logs). | `debug`; `warning` in `env.example` |
| `LOG_DAILY_DAYS` | With `daily`: how many days of log files are kept. Also works in Docker through `kinlore-api/.env`. | `14` |
| `DB_QUEUE_RETRY_AFTER` | Seconds after which a job that has not finished goes to a worker again. Keep it longer than the longest job (an export can take one hour). | `3700` |
| `VIDEO_QUALITY_VARIANTS` | The heights of the video stream, a comma list. Fewer heights make the conversion faster and use less disk. | `720,480,240` |
| `FFMPEG_BINARIES` | The path of the `ffmpeg` command. | `ffmpeg`; `/usr/bin/ffmpeg` in `env.example` |
| `FFPROBE_BINARIES` | The path of the `ffprobe` command. | `ffprobe`; `/usr/bin/ffprobe` in `env.example` |
| `FFMPEG_TEMPORARY_FILES_ROOT` | A folder for temporary files during a conversion. Change it when `/tmp` is small. | the system temporary folder |

`kinlore-api/.env.example` is the file for developers. Its other names are standard Laravel settings, for example for mail. Kinlore sends no email, so you do not need them.

## Next steps

- [Create the first admin](/self-hosting/first-admin)
- [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy)
- [Security](/self-hosting/security)
