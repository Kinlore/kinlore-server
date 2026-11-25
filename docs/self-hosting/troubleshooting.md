# Troubleshooting

This page lists common problems of the server, their causes and their fixes. Problems in the app are in [App troubleshooting](/app/troubleshooting).

## First, read the log

Most problems show in the log.

::: code-group
```sh [Docker]
docker compose logs api --tail 100
```
```sh [Host install]
sudo tail -n 50 /srv/kinlore/kinlore-api/storage/logs/laravel-$(date +%F).log
```
:::

More log commands are in [Maintenance](/self-hosting/maintenance#logs).

## Start and setup

| What you see | Cause and fix |
|---|---|
| `docker compose up` stops with `Set APP_KEY in the .env file next to docker-compose.yml` (or another name) | A required value is empty in `.env` next to `docker-compose.yml`. Fill it in. See [Required variables](/self-hosting/configuration#required). |
| The API container restarts again and again, and the log says `Database not reachable` | The API cannot reach PostgreSQL. Often `DB_PASSWORD` changed after the first start. PostgreSQL keeps the first password. Put the first value back. |
| `docker compose up` stops with `address already in use` or `port is already allocated` | Another program uses the port in `APP_PORT` or `APP_SSL_PORT` (by default 28461 and 28462; 80 and 443 for built-in HTTPS), often a web server on the host. `sudo ss -ltnp 'sport = :80'` names it (use the port from the error). Stop that program, or keep it and put Kinlore behind it with `deploy/docker/reverse-proxy.yml`. See [Choose your setup](/self-hosting/docker#step-3-choose-your-setup). |
| `docker compose` stops with an error about `!override` | Docker Compose is older than 2.24.4. `deploy/docker/reverse-proxy.yml` needs a newer version. Check with `docker compose version`, then update Docker. See [Requirements](/self-hosting/requirements#docker-and-docker-compose). |
| A log shows `permission denied` for a folder under `DATA_LOCATION` | With `deploy/docker/bind-mounts.yml`, each service writes as its own user into its subfolder. Keep the data folder on a local Linux file system such as ext4. Network shares and disks with FAT or NTFS often do not keep the owner of a file. After a copy, check that you used `cp -a`. See [Data locations](/self-hosting/data-locations). |
| The setup page does not accept the token | Print the token again with `php artisan kinlore:setup-token`. If `SETUP_TOKEN` is set, enter that value. See [Create the first admin](/self-hosting/first-admin). |
| A changed variable has no effect | Docker: run `docker compose up -d`; `docker compose restart` is not enough. Host install: run `sudo -u www-data php artisan config:cache`. In Docker, check that the name is passed to the container. See [Where the variables are](/self-hosting/configuration#where-the-variables-are). |

## Web server and HTTPS

| What you see | Cause and fix |
|---|---|
| The browser shows "502" or "Bad Gateway" (host install) | PHP-FPM is not running, or the web server cannot reach its socket. Run `systemctl status php8.5-fpm`. For Caddy: `sudo usermod -aG www-data caddy`, then restart Caddy. |
| A page shows "500" | Read the log. On a host install, often `storage` or `/var/lib/kinlore` is not owned by `www-data`. |
| No certificate | The host name does not point at the server, or port 80 or 443 is closed. |
| Links start with `http://`, and videos do not play on an iPhone | `APP_URL` must be the `https://` address. Behind a proxy, the address of the proxy must be in `TRUSTED_PROXIES`. With `deploy/docker/reverse-proxy.yml`, that is `172.31.250.1`, and the file sets it by itself; remove any other `TRUSTED_PROXIES` line from `.env`. See [Trusted proxies](/self-hosting/https-and-reverse-proxy#trusted-proxies). |
| All members hit the sign-in limit at the same time | The server sees the proxy as the client of every request, because `TRUSTED_PROXIES` does not hold the address of the proxy. The rate limits then count all members as one. Set `TRUSTED_PROXIES` to the address of the proxy, then run `docker compose up -d`. |

## Uploads and videos

| What you see | Cause and fix |
|---|---|
| A large upload fails with "413" or "POST data is too large" | Host install: the PHP files of step 4 are missing, or PHP-FPM was not restarted. A proxy in front can also limit the size. One file can be up to 500 MB. |
| A long upload stops after some time | A reverse proxy in front has a timeout. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy). |
| A video stays at "processing" | The video worker does not run. Docker: read `docker compose logs api`. Host install: `systemctl status kinlore-queue`, and FFmpeg must be at `/usr/bin/ffmpeg`. |
| A video shows as failed | Read the log for the error. Delete the moment in the app and upload the video again. |
| An HEIC photo from an iPhone has no preview | Host install: the HEIC decoder is missing. Run `sudo apt install -y libheif-examples`. |

### Large photo uploads fail on a small server

PHP-FPM runs up to 20 workers. Each may use up to 1 GB of memory while it makes the previews of a large photo. On a machine with 1 or 2 GB of memory, this can use more memory than the machine has.

The symptom: during a large photo upload, the system stops a process. `dmesg` shows `Out of memory: Killed process`. The upload fails, or the API restarts.

Do one or both:

- **Lower the number of PHP-FPM workers.** Fewer uploads can then run at the same time.
  - Docker: make a file `fpm-small.conf` next to `docker-compose.yml` with these two lines:
    ```ini [Limit PHP-FPM workers]
    [www]
    pm.max_children = 4
    ```
    Add it to `docker-compose.override.yml`, then run `docker compose up -d`. With `COMPOSE_FILE` in `.env`, also add `:docker-compose.override.yml` to the end of that line:
    ```yaml [docker-compose.override.yml]
    services:
      api:
        volumes:
          - ./fpm-small.conf:/usr/local/etc/php-fpm.d/zz-kinlore-fpm.conf:ro
    ```
  - Host install: set `pm.max_children = 4` in `/etc/php/8.5/fpm/pool.d/zz-kinlore.conf`, then run `sudo systemctl restart php8.5-fpm`.
- **Add swap.** Swap is disk space that the system uses as extra memory.
  ```sh [Add swap space]
  sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  ```
  The second line keeps the swap after a restart.

## Exports

| What you see | Cause and fix |
|---|---|
| A new export does not start | Only one export can run at a time. Wait until the running export ends. |
| An export fails | An original file cannot be read, or the disk is full. Read the log, and check the space with `df -h`. Check the storage with `php artisan seaweedfs:test`. |
| An export stays at "pending" | The export worker does not run. Docker: read `docker compose logs api`. Host install: `systemctl status kinlore-queue-default`. |

## Test the whole server

The end-to-end test tries every feature against a running server and shows what fails. It creates test data, so use it on a new or a test server. See [Commands](/reference/commands#end-to-end-test).

## Ask for help

If the problem stays, open an issue. See [Contributing](/project/contributing).

## Next steps

- [Maintenance](/self-hosting/maintenance)
- [Data locations](/self-hosting/data-locations)
- [Updating](/self-hosting/updating)
