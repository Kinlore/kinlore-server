# Updating

This page shows how to update the Kinlore server to a new release.

## What you get

- The newest server code, with its fixes and features.
- A database with the newest structure. The update changes the structure by itself; your data stays.

## What you need

- A recent backup. See [Backup and restore](/self-hosting/backup-and-restore).
- The "Upgrade notes" of each release between your version and the new one, in [CHANGELOG.md](https://github.com/Kinlore/kinlore-server/blob/develop/CHANGELOG.md). The steps for each version are also below, in [Extra steps per version](#extra-steps-per-version).

Version numbers have the form `major.minor.patch`, for example `1.1.0`. A patch release has fixes only and needs no extra step. Update the app too: the server and the app change together.

## Docker

1. Make a backup.
2. In the folder with `docker-compose.yml`, run:
   ```sh
   git pull
   docker compose up -d --build
   ```

The API container applies the database changes when it starts. A video conversion or an export that is running gets up to one hour to finish before the old container stops.

## Host install (without Docker)

1. Make a backup.
2. Run:
   ```sh
   cd /srv/kinlore && sudo git pull
   cd kinlore-api
   sudo composer install --no-dev --optimize-autoloader --no-interaction
   sudo -u www-data php artisan migrate --force
   sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache && sudo -u www-data php artisan view:cache
   sudo systemctl restart kinlore-queue kinlore-queue-default
   sudo systemctl reload php8.5-fpm
   ```

## Check that it worked

```sh
curl -fsS https://photos.example.com/api/health
```

The answer starts with `{"status":"ok"`. Then open the app and check that the moments are there.

Without Docker, also check the workers: `systemctl is-active kinlore-queue kinlore-queue-default` prints `active` two times.

## When it does not work

- **Docker: the API container restarts again and again.** Read `docker compose logs api`. A missing required setting stops `docker compose` with a message that names it.
- **Host install: a page shows "500".** Read the server log (see [Maintenance](/self-hosting/maintenance#logs)), and check that you did the extra steps below.
- **You want to go back.** Restore the backup that you made before the update. A database that a newer version changed is not made for an older version.

## Extra steps per version

Do the steps of every version between your version and the new one, oldest first.

### From 1.1 to 1.2

**Docker**

- PostgreSQL and Redis have no port on the host. If a tool on the host uses them, add the ports in a `docker-compose.override.yml`. See [Reach a database or storage port from the host](/self-hosting/docker#reach-a-database-or-storage-port-from-the-host).
- The settings `DB_PORT`, `REDIS_FORWARD_PORT` and `BIND_ADDRESS` in the root `.env` have no effect. You can remove them.

**Host install**

Exports have their own worker. After `git pull`, copy both worker files and turn on the new one:

```sh
sudo cp /srv/kinlore/deploy/bare-metal/kinlore-queue.service /srv/kinlore/deploy/bare-metal/kinlore-queue-default.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable kinlore-queue-default
```

Then run the other update commands. `systemctl restart` starts the new worker too.

If you run the backup script from a copy outside `/srv/kinlore`, copy `deploy/bare-metal/kinlore-backup.sh` there again.

### From 1.0 to 1.1

**Docker**

Add the media storage keys to `.env` before you run `docker compose up -d --build`. Make each one with `openssl rand -hex 32`:

```ini
SEAWEEDFS_KEY=...
SEAWEEDFS_SECRET=...
```

The stored media stays as it is.

**Host install**

1. Run `cd /srv/kinlore && sudo git pull`.
2. Install the HEIC decoder and copy the PHP files again:
   ```sh
   sudo apt install -y libheif-examples
   cd /srv/kinlore/kinlore-api
   sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/fpm/conf.d/99-kinlore.ini
   sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/cli/conf.d/99-kinlore.ini
   sudo cp ../deploy/bare-metal/php-fpm-kinlore.conf /etc/php/8.5/fpm/pool.d/zz-kinlore.conf
   ```
3. Run the other update commands. In place of the last line, restart PHP-FPM, so the new pool settings apply: `sudo systemctl restart php8.5-fpm`.
4. **Caddy:** remove the lines `read_timeout 30m` and `write_timeout 30m` from `/etc/caddy/Caddyfile`, if they are there. Then run `sudo systemctl reload caddy`. These lines cut an upload or a download after 30 minutes.
5. **S3 storage:** if `kinlore-api/.env` has `SEAWEEDFS_DRIVER=s3`, it must also have `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET`.

## Move PostgreSQL to a new major version (Docker)

A new major version of PostgreSQL, for example 19, cannot read the data folder of an older one. Its image does not start on a volume with old data. Move the data with a dump and a restore. A dump is a file with all data of the database.

The example moves from 18 to 19. The same steps work for a data volume from PostgreSQL 17 or older.

1. Save everything while the old version runs. Keep this file until the new version works:
   ```sh
   docker compose exec -T postgres pg_dumpall -U kinlore_user > kinlore-db.sql
   docker compose down
   ```
2. In `docker-compose.yml`, change `postgres:18-alpine` to `postgres:19-alpine`. Rename the volume `postgres_data` to `postgres_data_19` in both places: the `postgres` service and the `volumes` list at the end. The old volume stays untouched.
3. Start the new, empty database and load the data:
   ```sh
   docker compose up -d postgres
   docker compose exec -T postgres psql -U kinlore_user -d postgres < kinlore-db.sql
   ```
   The restore prints `role "kinlore_user" already exists`. That is normal.
4. Start the rest and check:
   ```sh
   docker compose up -d
   curl -fsS http://localhost/api/health
   ```
5. Open the app and check that your moments are there. Only then remove the old volume. `docker volume ls` shows it, with a name that ends in `_postgres_data`.

Kinlore does not support PostgreSQL 19 yet. Move only to a version that [Requirements](/self-hosting/requirements) lists.
