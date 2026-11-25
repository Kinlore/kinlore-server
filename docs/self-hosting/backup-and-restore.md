# Backup and restore

A backup is a copy of your server data in a second place. Make one before the family adds many photos, and then on a regular schedule.

## What you get

- A copy of everything you need to bring back the server with all accounts, children and moments.
- Steps to restore that copy on a new machine.

## What to back up

Back up three things:

1. **The database.** It holds the accounts, the children, the moments, the likes and the comments.
2. **The media.** These are the photos and videos.
3. **The settings file.** It holds `APP_KEY`, and in Docker the database password and the storage keys. Without it, the backup cannot be used.

The admin can also make a ZIP export of all moments in the app. See [Export](/app/export). An export holds the photos, videos and texts, but not the accounts. Only a backup brings back the server.

## What you need

- A second disk or a second machine for the copy. A copy on the same disk does not help when the disk breaks.
- Enough space for all photos and videos.

## Docker

The settings file is `.env` next to `docker-compose.yml`. Save `kinlore-api/.env` too, if you made one. The media is in the three SeaweedFS volumes. The other volumes hold logs, caches, finished exports and certificates. The server makes them again.

::: warning
These commands are checked against `docker-compose.yml`, but no automatic test runs them. Test a restore on a second machine before you rely on them. The tutorial [Move or restore a server](/tutorials/move-or-restore-a-server) shows how.
:::

### Make a backup

Run the commands in the folder with `docker-compose.yml`:

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

The database is saved first, so the media copy is never older than the database. The media copy stops the stack for a few minutes. A copy while SeaweedFS runs can catch a file in the middle of a write, so the stop is the safe way.

Copy the `backup` folder to another disk or machine.

### Check the backup

```sh
ls -lh backup
```

The folder holds `kinlore-db.sql`, three `.tar.gz` files and `env`. None of them has a size of 0.

### Restore

Restore on a fresh install: a new copy of the code, on a machine where the Kinlore volumes do not exist yet.

1. Get the code: `git clone https://github.com/Kinlore/kinlore-server.git && cd kinlore-server`.
2. Put the `backup` folder next to `docker-compose.yml`.
3. Run:
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
4. Open the app and check that your moments and their photos are there.

## Host install (without Docker)

The script `deploy/bare-metal/kinlore-backup.sh` saves the SQLite database, the media folder and `kinlore-api/.env`. It keeps 30 days of history.

### Make a backup

```sh
sudo /srv/kinlore/deploy/bare-metal/kinlore-backup.sh /mnt/backup/kinlore
```

The script makes a consistent copy of the SQLite database while the server runs. It copies the media with `rsync` and saves `.env`. It stops with an error when the media folder is empty but the backup is not, for example when the media disk is not mounted.

Run it every night with cron. Open the cron table with `sudo crontab -e` and add this line:

```
30 3 * * * /srv/kinlore/deploy/bare-metal/kinlore-backup.sh /mnt/backup/kinlore
```

### What the backup folder holds

| Path | Content |
|---|---|
| `database.sqlite`, `media/`, `env` | The state of the last run |
| `old/<date>/` | What the run of that date replaced or removed: the previous `database.sqlite` and `env`, and each media file that was changed or deleted since the run before. The script removes `old/` folders older than 30 days. |

So a photo deleted by mistake stays in the backup for 30 days, and so does each daily database copy. Plan disk space for 30 copies of the database. If you run the script more than once a day, the last run of a day replaces the `old/` copies of that day.

### With PostgreSQL

The script saves SQLite. With PostgreSQL, make a copy of the script and replace its three lines with `database.sqlite` by these lines:

```sh
pg_dump -U kinlore kinlore > "$DEST/database.sql.tmp"
[ -f "$DEST/database.sql" ] && mv "$DEST/database.sql" "$OLD/database.sql"
mv "$DEST/database.sql.tmp" "$DEST/database.sql"
```

### Restore one deleted photo or video

1. List what the runs replaced or removed, newest dates last:
   ```sh
   sudo find /mnt/backup/kinlore/old -type f -path '*/media/*' | sort
   ```
   Each media file is in a folder `media/<child id>/<media id>/`.
2. Copy one folder back, with its path. Replace the date, `KID_ID` and `MEDIA_ID` with names from the list:
   ```sh
   cd /mnt/backup/kinlore/old/2026-11-20/media
   sudo cp -a --parents media/KID_ID/MEDIA_ID /var/lib/kinlore/media/
   sudo chown -R www-data:www-data /var/lib/kinlore/media
   ```

A file comes back only if the database still knows it. To go back to the database of an older date, use the full restore below with `old/<date>/database.sqlite` in place of `database.sqlite`.

### Full restore

Do steps 1 to 4 of [Install without Docker](/self-hosting/bare-metal) first. The database runs in WAL mode, so remove the files `database.sqlite-wal` and `database.sqlite-shm` before you copy the backup in. They belong to the old database.

```sh
sudo systemctl stop kinlore-queue kinlore-queue-default php8.5-fpm
sudo rm -f /var/lib/kinlore/database.sqlite-wal /var/lib/kinlore/database.sqlite-shm
sudo cp /mnt/backup/kinlore/database.sqlite /var/lib/kinlore/database.sqlite
sudo rsync -a /mnt/backup/kinlore/media/ /var/lib/kinlore/media/
sudo cp /mnt/backup/kinlore/env /srv/kinlore/kinlore-api/.env
sudo chown -R www-data:www-data /var/lib/kinlore
sudo chown root:www-data /srv/kinlore/kinlore-api/.env
cd /srv/kinlore/kinlore-api && sudo -u www-data php artisan migrate --force && sudo -u www-data php artisan config:cache
sudo systemctl start php8.5-fpm kinlore-queue kinlore-queue-default
```

Then do steps 6 and 7 of the install guide, if this is a new machine.

## Check that it worked

After a restore:

```sh
curl -fsS https://photos.example.com/api/health
```

Then open the app. Check that the children, the moments and their photos are there, and that a video plays.

## When it does not work

- **A video stays at "processing" after a restore.** It was being converted at the moment of the backup. After about one hour it shows as failed. Delete it in the app and upload it again from the phone.
- **The API cannot read the data, or signed links fail.** The settings file is not the one of the backup. Copy `env` from the backup again.
- **Docker: `psql` prints errors and stops.** The database was not empty. Start again on a machine without the Kinlore volumes.
