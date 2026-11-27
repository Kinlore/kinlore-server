# Commands

This page lists the commands that a server owner uses. Artisan is the command tool of the Laravel application. Every Artisan command starts with `php artisan`.

## How to run an Artisan command

- **Docker:** in the folder with `docker-compose.yml`, put `docker compose exec api` in front:
  ```sh
  docker compose exec api php artisan kinlore:setup-token
  ```
- **Host install:** in `/srv/kinlore/kinlore-api`, run it as the user `www-data`:
  ```sh
  sudo -u www-data php artisan kinlore:setup-token
  ```

## Kinlore commands

| Command | What it does |
|---|---|
| `php artisan kinlore:setup-token` | Prints the token that the setup page asks for, while the server has no user. With `SETUP_TOKEN` set, it tells you to use that value. See [Create the first admin](/self-hosting/first-admin). |
| `php artisan kinlore:reset-password <email>` | Sets a new password for a user and signs them out on all devices. It asks for the password. `--password=<new password>` gives it on the command line instead; the password then shows in the shell history. The password needs at least 8 characters. |
| `php artisan kinlore:media-dimensions` | Fills the width and height of older photos and videos, so the app can lay out the timeline before the images load. Run it once after the update to 1.6. A video gets the size that its conversion saved. A photo gets the size of its preview: the real size up to 1200 pixels wide, else the same ratio at 1200 pixels wide. It works in groups of 200 and changes only moments without a size, so you can run it again at any time. A file that it cannot read is skipped, counted in the result and written to the log. `--dry-run` shows the counts and changes nothing. Phones get the new sizes at their next sync. |
| `php artisan seaweedfs:test` | Writes, reads, lists and deletes a test file in the media storage, then prints the storage settings. It works for S3 storage and the local disk. |

## Laravel commands

| Command | What it does |
|---|---|
| `php artisan migrate --force` | Applies the database changes of a new release. The Docker container runs it at each start. |
| `php artisan migrate --force --seed` | The same, and loads the list of milestones. Used once at a host install. |
| `php artisan config:cache` | Reads `.env` again and caches the settings. Run it after every change of `.env` on a host install. |
| `php artisan route:cache` and `php artisan view:cache` | Cache the routes and the pages. Run them after an update on a host install. |
| `php artisan queue:failed` | Lists the failed video and export jobs. |
| `php artisan queue:retry all` | Tries all failed jobs again. |
| `php artisan about --only=environment` | Shows the mode of the application. A real server shows `Environment: production` and `Debug Mode: OFF`. |
| `php artisan key:generate --force` | Makes a new `APP_KEY` in `kinlore-api/.env`. Use it only at a new install: a new key stops all signed links and encrypted values. |

## Scripts

The scripts are in the code folder.

| Script | What it does |
|---|---|
| `deploy/bare-metal/kinlore-backup.sh <folder>` | Backup of a host install: SQLite database, media folder and `.env`, with 30 days of history. Run it with `sudo`. See [Backup and restore](/self-hosting/backup-and-restore#host-install-without-docker). |
| `scripts/smoke-test.sh <address> <admin email> <admin password>` | The end-to-end test. See below. |
| `scripts/test-bare-metal.sh` | Runs the guide [Install without Docker](/self-hosting/bare-metal) in a clean Debian 13 container. It needs only Docker and takes several minutes. For developers. |

### End-to-end test

The end-to-end test signs in as the admin and tries every feature over HTTP: children, uploads, video conversion and playback, likes and comments, members and permissions, sync, look-back and export.

::: warning It creates test data
The test adds children named "Smoke Mia", "Bump" and "Offline kid", and a test member. Run it before the family starts, or delete that data afterwards in the app.
:::

It needs `curl`, `jq` and `uuidgen`. On Debian and Ubuntu, install them with `sudo apt install -y curl jq uuid-runtime`. The video checks need FFmpeg, or a test video in `SMOKE_VIDEO=/path/to/file.mp4`.

```sh
scripts/smoke-test.sh https://photos.example.com anna@example.com 'your-admin-password'
```

The last line must say `Failed: 0`. When the server already holds many large videos, the export step needs more time. Put `SMOKE_EXPORT_WAIT=600` in front of the command.

## Docker Compose commands

| Command | What it does |
|---|---|
| `docker compose up -d --build` | Builds the API image and starts the stack. Use it at the first start and after an update. |
| `docker compose up -d` | Starts the stack, or applies a changed `.env`. |
| `docker compose ps` | Shows the containers and their state. |
| `docker compose logs api` | Shows the log of the API container. |
| `docker compose stop` and `docker compose start` | Stop and start the containers. The data stays. |
| `docker compose down` | Removes the containers. The volumes with the data stay. |
