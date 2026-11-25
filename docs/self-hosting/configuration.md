# Configuration

The server reads its settings from environment variables. An environment variable is a name with a value, such as `APP_URL=https://photos.example.com`. You write them in a settings file. This page lists every setting that Kinlore uses, with its meaning, its default, and when to change it.

## Where the settings are

| Install | Settings file |
|---|---|
| Docker | `.env` **next to `docker-compose.yml`**. Start from `.env.example`. |
| Without Docker | `kinlore-api/.env`. Start from `deploy/bare-metal/env.example`. |

After a change:

- **Docker:** run `docker compose up -d`. The API container starts again with the new values.
- **Without Docker:** run `sudo -u www-data php artisan config:cache` in `/srv/kinlore/kinlore-api`, then `sudo systemctl restart kinlore-queue kinlore-queue-default`.

::: warning Keep the settings file safe
The settings file holds `APP_KEY` and the passwords. Without it, a backup cannot be used. Save it with every backup. Never share it.
:::

### How Docker passes the settings

`docker-compose.yml` passes a fixed list of names from `.env` to the API container. The tables below mark these names with **Docker: yes**. A name that is not in that list has no effect in the root `.env`.

For the other names, use a second file: `kinlore-api/.env`. The API container reads it when it exists. Set `API_ENV_FILE` to use another path. A value from the root `.env` wins over the same name in `kinlore-api/.env`.

## Required settings

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `APP_KEY` | The secret key of the server. It protects sessions, signed links and encrypted values. Make it with `echo "base64:$(openssl rand -base64 32)"` (Docker) or `php artisan key:generate` (without Docker). | none | yes, required |
| `APP_URL` | The address that the phones use, for example `https://photos.example.com`. Include a port if it is not 80 or 443. Invitation links, video links and the setup page are built from it. | `http://localhost` | yes |
| `DB_PASSWORD` | The password of the database user. In Docker, PostgreSQL gets this password at its first start. | none | yes, required |
| `SEAWEEDFS_KEY` | The access key of the media storage. Make it with `openssl rand -hex 32`. | none | yes, required |
| `SEAWEEDFS_SECRET` | The secret key of the media storage. Make it with `openssl rand -hex 32`. | none | yes, required |

In Docker, `docker compose` stops with a message such as `Set APP_KEY in the .env file next to docker-compose.yml` when a required value is missing. Without Docker, `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` are needed only with `SEAWEEDFS_DRIVER=s3`.

::: danger Do not change these after the first start
- A new `APP_KEY` stops every signed link and every encrypted value.
- In Docker, PostgreSQL keeps the first `DB_PASSWORD`. A new value in `.env` does not change the database password, and the API can then not connect.
:::

## First admin

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `SETUP_TOKEN` | The token that the setup page asks for. Empty: the server makes a random token. See [Create the first admin](/self-hosting/first-admin). | empty | yes |
| `SEED_USER_NAME` | Name of an admin that the server creates by itself, with no setup page. | `Admin` in Docker, `Admin User` without Docker | yes |
| `SEED_USER_EMAIL` | Email address of that admin. With `SEED_USER_PASSWORD`, it turns this function on. | empty | yes |
| `SEED_USER_PASSWORD` | Password of that admin. | empty | yes |

The server creates the seeded admin only while it has no user.

## Name and mode

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `APP_NAME` | The name on public pages, in the answer of `/api/auth/setup-status`, and in the export file. It is not the family name. Members see the family name, which the admin sets in the app. | `Kinlore` | yes |
| `APP_ENV` | The mode of the application. Keep `production`. | `production` | yes |
| `APP_DEBUG` | Shows error details in answers. Keep `false` on a real server: details can show private data. | `false` | yes |

## Network and HTTPS (Docker)

These settings exist only in Docker. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).

| Name | Meaning | Default |
|---|---|---|
| `KINLORE_DOMAIN` | Your public host name. When it is set, Caddy in the API container gets a certificate and serves HTTPS. Empty: plain HTTP on port 80. | empty |
| `APP_PORT` | Host port for HTTP. Change it when a reverse proxy on the host uses port 80, for example to `8080`. | `80` |
| `APP_SSL_PORT` | Host port for HTTPS, TCP and UDP. Used only with `KINLORE_DOMAIN`. Move it when your own proxy uses port 443. | `443` |
| `APP_BIND_ADDRESS` | The host address of the API ports. Set `127.0.0.1` when a proxy on the same machine is the only way in. | `0.0.0.0` (all addresses) |
| `API_ENV_FILE` | Path of the extra settings file for the API container. The file may be missing. | `./kinlore-api/.env` |

## Reverse proxy

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `TRUSTED_PROXIES` | Addresses of reverse proxies whose `X-Forwarded-*` headers the server trusts. A comma list of addresses and networks. `*` trusts every address. Set it to the address of your proxy. | `127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16` (private networks); `127.0.0.1` in `deploy/bare-metal/env.example` | yes |

## Database

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `DB_CONNECTION` | The database type: `pgsql` (PostgreSQL) or `sqlite`. | `pgsql` in Docker, `sqlite` without Docker | yes |
| `DB_DATABASE` | The database name. For SQLite, the path of the database file. | `kinlore_db` in Docker, `/var/lib/kinlore/database.sqlite` in `env.example` | yes |
| `DB_USERNAME` | The database user. | `kinlore_user` in Docker | yes |
| `DB_HOST` | The database server, for PostgreSQL without Docker. | `127.0.0.1` | no (fixed to the PostgreSQL container) |
| `DB_PORT` | The port of the database server. | `5432` | no (fixed) |
| `DB_BUSY_TIMEOUT` | SQLite only: how long a request waits for a locked database, in milliseconds. | `5000` | no |
| `DB_JOURNAL_MODE` | SQLite only: the journal mode. Keep `wal`. | `wal` | no |

In Docker, set `DB_DATABASE` and `DB_USERNAME` only before the first start. PostgreSQL makes them once.

## Media storage

All photos and videos go through one storage. Its settings start with `SEAWEEDFS_` because Docker uses SeaweedFS. Without Docker, the same names select a folder on the disk or any S3 storage. S3 is a common way to store files over the network.

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `FILESYSTEM_DISK` | The storage name in the code. Keep `seaweedfs`. | `seaweedfs` | yes |
| `SEAWEEDFS_DRIVER` | `s3` for S3 storage, `local` for a folder on the disk. | `s3`; `local` in `env.example` | no (Docker uses `s3`) |
| `MEDIA_ROOT` | With `local`: the folder for photos and videos. | `storage/app/media` in `kinlore-api`; `/var/lib/kinlore/media` in `env.example` | no |
| `SEAWEEDFS_ENDPOINT` | With `s3`: the address of the S3 storage. | `http://kinlore-seaweedfs-filer:8333` | yes |
| `SEAWEEDFS_BUCKET` | With `s3`: the bucket name. A bucket is a top-level folder of S3 storage. | `kinlore` | yes |
| `SEAWEEDFS_REGION` | With `s3`: the region of the storage. | `us-east-1` | no |
| `SEAWEEDFS_USE_PATH_STYLE_ENDPOINT` | With `s3`: put the bucket name in the path of the address. Most self-hosted S3 storage needs `true`. | `true` | fixed to `true` |
| `SEAWEEDFS_URL` | A base address of the storage. The app never uses it; `php artisan seaweedfs:test` prints it. | `http://localhost:8333` | yes |

## Cache, sessions and queue

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `CACHE_STORE` | Where the cache and the rate limits are kept: `redis` or `database`. | `redis` in Docker, `database` without Docker | yes |
| `SESSION_DRIVER` | Where browser sessions of the setup page are kept. | `file` in Docker, `database` without Docker | yes |
| `QUEUE_CONNECTION` | Where background jobs wait for a worker. Keep `database`: the workers read from it. | `database` | yes |
| `DB_QUEUE_RETRY_AFTER` | Seconds after which a job that has not finished is given to a worker again. Keep it longer than the longest job (an export can take one hour). | `3700` | no |
| `SANCTUM_STATEFUL_DOMAINS` | Browser addresses that may sign in with a cookie. The app uses tokens, not cookies. You do not need to change it. | `localhost,localhost:8081` in Docker | yes |

## Videos

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `VIDEO_QUALITY_VARIANTS` | The heights of the video stream, a comma list. Fewer heights make the conversion faster and use less disk. | `720,480,240` | no |
| `FFMPEG_BINARIES` | Path of the `ffmpeg` command. | `ffmpeg`; `/usr/bin/ffmpeg` in `env.example` | no |
| `FFPROBE_BINARIES` | Path of the `ffprobe` command. | `ffprobe`; `/usr/bin/ffprobe` in `env.example` | no |
| `FFMPEG_TEMPORARY_FILES_ROOT` | Folder for temporary files during a conversion. Change it when `/tmp` is small. | the system temporary folder | no |

## Logs

| Name | Meaning | Default | Docker |
|---|---|---|---|
| `LOG_CHANNEL` | The log setup. Keep `stack`. | `stack` | yes |
| `LOG_STACK` | Where the log goes. `daily` writes one file per day. `single` writes one file that grows. | `daily` in Docker and `env.example`; `single` otherwise | yes |
| `LOG_LEVEL` | The lowest level that is written. `debug` writes the most; `error` the least. | `warning` in Docker and `env.example` | yes |
| `LOG_DAILY_DAYS` | With `daily`: how many days of log files are kept. | `14` | no |

The log files are in `kinlore-api/storage/logs`. See [Maintenance](/self-hosting/maintenance#logs).

## Other settings

The server is a Laravel application. Laravel reads more settings, for example for mail. Kinlore does not send email, so you do not need them.
