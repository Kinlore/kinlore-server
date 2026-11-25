# Troubleshooting

This page lists common problems of the server, their causes and their fixes. Problems in the app are in [App troubleshooting](/app/troubleshooting).

## First, read the log

Most problems show in the log.

```sh
docker compose logs api --tail 100                              # Docker
sudo tail -n 50 /srv/kinlore/kinlore-api/storage/logs/laravel-$(date +%F).log   # host install
```

More log commands are in [Maintenance](/self-hosting/maintenance#logs).

## Start and setup

| What you see | Cause and fix |
|---|---|
| `docker compose up` stops with `Set APP_KEY in the .env file next to docker-compose.yml` (or another name) | A required value is empty in `.env` next to `docker-compose.yml`. Fill it in. See [Configuration](/self-hosting/configuration#required-settings). |
| The API container restarts again and again, and the log says `Database not reachable` | The API cannot reach PostgreSQL. Often `DB_PASSWORD` changed after the first start. PostgreSQL keeps the first password. Put the first value back. |
| A port is already in use | Another program uses port 80 or 443. Stop it, or move the API to other ports with `APP_PORT` and `APP_SSL_PORT`. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy#your-own-reverse-proxy-docker). |
| The setup page does not accept the token | Print the token again with `php artisan kinlore:setup-token`. If `SETUP_TOKEN` is set, enter that value. See [Create the first admin](/self-hosting/first-admin). |
| A changed setting has no effect | Docker: run `docker compose up -d`. Host install: run `sudo -u www-data php artisan config:cache`. In Docker, check that the name is passed to the container. See [How Docker passes the settings](/self-hosting/configuration#how-docker-passes-the-settings). |

## Web server and HTTPS

| What you see | Cause and fix |
|---|---|
| The browser shows "502" or "Bad Gateway" (host install) | PHP-FPM is not running, or the web server cannot reach its socket. Run `systemctl status php8.5-fpm`. For Caddy: `sudo usermod -aG www-data caddy`, then restart Caddy. |
| A page shows "500" | Read the log. On a host install, often `storage` or `/var/lib/kinlore` is not owned by `www-data`. |
| No certificate | The host name does not point at the server, or port 80 or 443 is closed. |
| Links start with `http://`, and videos do not play on an iPhone | `APP_URL` must be the `https://` address. Behind a proxy, its address must be in `TRUSTED_PROXIES`. See [Trusted proxies](/self-hosting/https-and-reverse-proxy#trusted-proxies). |

## Uploads and videos

| What you see | Cause and fix |
|---|---|
| A large upload fails with "413" or "POST data is too large" | Host install: the PHP files of step 4 are missing, or PHP-FPM was not restarted. A proxy in front can also limit the size. One file can be up to 500 MB. |
| A long upload stops after some time | A reverse proxy in front has a timeout. See [What your proxy must do](/self-hosting/https-and-reverse-proxy#what-your-proxy-must-do). |
| A video stays at "processing" | The video worker does not run. Docker: read `docker compose logs api`. Host install: `systemctl status kinlore-queue`, and FFmpeg must be at `/usr/bin/ffmpeg`. |
| A video shows as failed | Read the log for the error. Delete the moment in the app and upload the video again. |
| An HEIC photo from an iPhone has no preview | Host install: the HEIC decoder is missing. Run `sudo apt install -y libheif-examples`. |

### Large photo uploads fail on a small server

PHP-FPM runs up to 20 workers. Each may use up to 1 GB of memory while it makes the previews of a large photo. On a machine with 1 or 2 GB of memory, this can use more memory than the machine has.

The symptom: during a large photo upload, the system stops a process. `dmesg` shows `Out of memory: Killed process`. The upload fails, or the API restarts.

Do one or both:

- **Lower the number of PHP-FPM workers.** Fewer uploads can then run at the same time.
  - Docker: make a file `fpm-small.conf` next to `docker-compose.yml` with these two lines:
    ```ini
    [www]
    pm.max_children = 4
    ```
    Add it to `docker-compose.override.yml`, then run `docker compose up -d`:
    ```yaml
    services:
      api:
        volumes:
          - ./fpm-small.conf:/usr/local/etc/php-fpm.d/zz-kinlore-fpm.conf:ro
    ```
  - Host install: set `pm.max_children = 4` in `/etc/php/8.5/fpm/pool.d/zz-kinlore.conf`, then run `sudo systemctl restart php8.5-fpm`.
- **Add swap.** Swap is disk space that the system uses as extra memory.
  ```sh
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
