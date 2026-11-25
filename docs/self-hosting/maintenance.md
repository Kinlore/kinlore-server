# Maintenance

This page lists the everyday tasks of a server owner: read logs, look after the workers, reset a password, get the setup token, and manage exports.

Each command is shown two times:

- **Docker:** run it in the folder with `docker-compose.yml`.
- **Host install:** run it in `/srv/kinlore/kinlore-api`.

A list of all commands is in [Commands](/reference/commands).

## Logs

A log is a file in which the server writes what it does and which errors happen.

### Docker

```sh
docker compose logs api --tail 100           # web server, PHP and workers
docker compose logs -f api                   # follow new lines; stop with Ctrl+C
docker compose exec api sh -c 'tail -n 50 storage/logs/laravel-$(date +%F).log'   # application log of today
```

Docker keeps at most three log files of 10 MB for each container. The application also writes its own log files in the volume `api_storage`, one per day, kept 14 days.

### Host install

```sh
sudo tail -n 50 storage/logs/laravel-$(date +%F).log         # application log of today
journalctl -u kinlore-queue -u kinlore-queue-default -n 50   # workers
journalctl -u caddy -n 50                                    # Caddy; for nginx: /var/log/nginx/error.log
```

The log settings are in [Configuration](/self-hosting/configuration#logs).

::: warning
Logs can hold email addresses and other private data. Remove them before you share a log.
:::

## Queue workers

A queue worker is a background process. Kinlore has two:

| Worker | Job | Docker | Host install |
|---|---|---|---|
| Video worker | Converts each new video into a stream | `queue-video-processing` in the API container | `kinlore-queue` |
| Export worker | Builds the ZIP file of an export | `queue-default` in the API container | `kinlore-queue-default` |

In Docker, the API container starts and restarts both workers by itself. Without Docker, `systemd` does this.

Check the workers on a host install:

```sh
systemctl status kinlore-queue kinlore-queue-default --no-pager
sudo systemctl restart kinlore-queue kinlore-queue-default
```

A job that fails is stored as a failed job. The server does not try it again by itself. Show the failed jobs, and try them again:

```sh
docker compose exec api php artisan queue:failed            # Docker
docker compose exec api php artisan queue:retry all
sudo -u www-data php artisan queue:failed                   # host install
sudo -u www-data php artisan queue:retry all
```

A video conversion may take up to 30 minutes. An export may take up to one hour. After that, the job stops and the video or export shows as failed.

## Forgotten password

- **A member forgot it:** the admin opens Settings, then Members, then the member, then "Set new password" in the app. The member is signed out on all devices and signs in with the new password.
- **The admin forgot it**, or you prefer the server: run the command below. It asks for the new password and signs that user out on all devices.

```sh
docker compose exec api php artisan kinlore:reset-password anna@example.com   # Docker
sudo -u www-data php artisan kinlore:reset-password anna@example.com          # host install
```

The password needs at least 8 characters. The command prints `Password changed for anna@example.com. They are signed out on all devices.`

## Setup token

The setup page asks for a setup token while the server has no user. Print it:

```sh
docker compose exec api php artisan kinlore:setup-token   # Docker
sudo -u www-data php artisan kinlore:setup-token          # host install
```

When the server has a user, the command prints `This server is already set up, so it has no setup token.` See [Create the first admin](/self-hosting/first-admin).

## Exports

The admin starts an export in the app. See [Export](/app/export). The server then builds a ZIP file with all original photos and videos, and all moments as JSON.

- Only one export can run at a time.
- The ZIP files are on the disk of the API server, in `storage/app/exports`. In Docker, this is in the volume `api_storage`.
- The download link is valid for 24 hours.
- The server keeps the 3 newest exports and deletes older ZIP files.
- An export fails when an original file cannot be read. So a finished export always holds every file.

An export needs free disk space for a copy of all originals. Check the space before you start one:

```sh
df -h
```

## Storage check

This command writes, reads and deletes a test file in the media storage:

```sh
docker compose exec api php artisan seaweedfs:test   # Docker
sudo -u www-data php artisan seaweedfs:test          # host install
```

The output ends with `All SeaweedFS tests passed!`. The command works for local and S3 storage.

## Disk space

Check the space from time to time. Photos and videos fill the disk slowly; exports fill it fast.

```sh
df -h
docker system df          # Docker: space of images and volumes
```
