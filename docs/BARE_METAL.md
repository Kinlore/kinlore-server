# Kinlore on a host without Docker

This guide installs the full Kinlore server directly on a Linux machine: PHP, the database, the web server with HTTPS, and the worker that converts videos. For Docker, see [SELF_HOSTING.md](SELF_HOSTING.md).

The commands of this guide are run by `scripts/test-bare-metal.sh` on a clean Debian 13, with both web servers, on each change of the code. "Test this guide" at the end lists what the script covers and what it cannot.

- **System:** Debian 13 or Ubuntu 24.04 or later. The commands need `sudo`.
- **Versions:** PHP 8.5 and Laravel 13. PostgreSQL is optional; any supported version from 15 works, and SQLite needs no server.
- **What you get:** the code in `/srv/kinlore`, the data in `/var/lib/kinlore`, PHP-FPM as user `www-data`, Caddy or nginx in front, a `systemd` service for the worker.
- **Ready-made files:** all configuration files are in [`deploy/bare-metal/`](../deploy/bare-metal).

| File | Goes to | Purpose |
|---|---|---|
| `env.example` | `kinlore-api/.env` | Settings: SQLite, local media, no Redis |
| `php-kinlore.ini` | `/etc/php/8.5/{fpm,cli}/conf.d/99-kinlore.ini` | Upload size and memory limits |
| `Caddyfile` | `/etc/caddy/Caddyfile` | Web server with automatic HTTPS (recommended) |
| `nginx.conf` | `/etc/nginx/sites-available/kinlore` | Web server, if you prefer nginx |
| `kinlore-queue.service` | `/etc/systemd/system/` | Worker for videos and exports |
| `kinlore-backup.sh` | anywhere, for example `/usr/local/bin/` | Backup of database, media and settings |

## 1. Install the packages

Kinlore needs PHP 8.5, Composer, FFmpeg and a web server. Debian 13 and Ubuntu 24.04 do not ship PHP 8.5, so add the PHP package source first.

Debian:

```sh
sudo apt update && sudo apt install -y curl ca-certificates lsb-release
sudo curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
```

Ubuntu: `sudo add-apt-repository ppa:ondrej/php`

Then, on both:

```sh
sudo apt update
sudo apt install -y php8.5-fpm php8.5-cli php8.5-sqlite3 php8.5-pgsql php8.5-gd php8.5-zip \
  php8.5-intl php8.5-mbstring php8.5-bcmath php8.5-xml php8.5-curl \
  ffmpeg composer git unzip curl sqlite3 rsync
```

- **Web server:** install one of them: `sudo apt install -y caddy` or `sudo apt install -y nginx certbot python3-certbot-nginx`.

## 2. Get the code and make the data folder

```sh
sudo git clone https://github.com/elyess/kinlore-server.git /srv/kinlore
sudo mkdir -p /var/lib/kinlore/media
sudo touch /var/lib/kinlore/database.sqlite
sudo chown -R www-data:www-data /var/lib/kinlore
```

`/var/lib/kinlore` holds everything that matters: the database file and all photos and videos.

## 3. Install and configure

```sh
cd /srv/kinlore/kinlore-api
sudo composer install --no-dev --optimize-autoloader --no-interaction
sudo cp ../deploy/bare-metal/env.example .env
sudo php artisan key:generate --force
sudo nano .env        # set APP_URL to the address the phones use, for example https://photos.example.com
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chown root:www-data .env && sudo chmod 640 .env
```

The settings file uses SQLite and the local disk, so no database server, Redis or S3 storage is needed. The file explains each value. Two choices are in it as comments:

- **PostgreSQL** instead of SQLite, for example when several services share one database server:
  ```sh
  sudo apt install -y postgresql
  sudo -u postgres psql -c "CREATE USER kinlore WITH PASSWORD 'a-long-random-password';"
  sudo -u postgres psql -c "CREATE DATABASE kinlore OWNER kinlore;"
  ```
  Then set `DB_CONNECTION=pgsql` and the `DB_*` values in `.env`.
- **S3 storage** instead of the local disk: set `SEAWEEDFS_DRIVER=s3` and the `SEAWEEDFS_*` values.

## 4. Allow large uploads in PHP

```sh
sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/fpm/conf.d/99-kinlore.ini
sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/cli/conf.d/99-kinlore.ini
sudo systemctl restart php8.5-fpm
```

One photo or video can be up to 512 MB. Files are read as streams, so the memory limit of 256 MB is enough.

## 5. Create the database

```sh
sudo -u www-data php artisan migrate --force --seed
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

`--seed` adds the list of milestones. Run the three `cache` commands again after every change of `.env`.

## 6. Web server

Choose one. Both serve `kinlore-api/public` and pass PHP requests to PHP-FPM.

### Caddy (recommended)

Caddy gets and renews the HTTPS certificate by itself. Your host name must point at this machine, and ports 80 and 443 must be open.

```sh
sudo usermod -aG www-data caddy          # lets Caddy reach the PHP-FPM socket
sudo cp ../deploy/bare-metal/Caddyfile /etc/caddy/Caddyfile
sudo nano /etc/caddy/Caddyfile           # replace photos.example.com with your host name
sudo systemctl restart caddy
```

For a home network without a host name, write `:80` in place of the host name. The server then answers plain HTTP, for example at `http://192.168.1.20`.

### nginx

```sh
sudo cp ../deploy/bare-metal/nginx.conf /etc/nginx/sites-available/kinlore
sudo nano /etc/nginx/sites-available/kinlore     # set server_name
sudo ln -s /etc/nginx/sites-available/kinlore /etc/nginx/sites-enabled/kinlore
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d photos.example.com       # adds HTTPS and renews it
```

### Firewall

```sh
sudo ufw allow 80,443/tcp
sudo ufw allow 443/udp      # HTTP/3, Caddy only
```

## 7. Worker for videos and exports

Videos are converted for streaming in the background, and exports are built there too.

```sh
sudo cp ../deploy/bare-metal/kinlore-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now kinlore-queue
systemctl status kinlore-queue --no-pager
```

One worker is enough for a family. If you run more, keep `DB_QUEUE_RETRY_AFTER` (default 3700 seconds) longer than the longest job.

## 8. Create the admin account

Open your address (`APP_URL`) in a web browser. An empty server sends you to `/setup`. Enter your name, email and password. The app never creates the admin.

- **Setup token:** when the server is reachable from the internet before you finish this step, set `SETUP_TOKEN` in `.env` to a long random value first (then run `php artisan config:cache` again). The setup page asks for it.
- **Without a browser:** add `SEED_USER_NAME`, `SEED_USER_EMAIL` and `SEED_USER_PASSWORD` to `.env` before step 5. The seeder then creates the admin. Remove the three lines afterwards.

Then install the app, enter the server address, and sign in. Invite the family from Settings → Members.

## 9. Check the install

Quick checks:

```sh
curl -fsS https://photos.example.com/api/health              # {"status":"ok",...}
curl -fsS https://photos.example.com/api/auth/setup-status   # needs_setup is false after step 8
curl -s -o /dev/null -w '%{http_code}\n' https://photos.example.com/.env   # must be 404
sudo -u www-data php artisan about --only=environment        # Environment: production, Debug Mode: OFF
systemctl is-active php8.5-fpm kinlore-queue caddy           # or nginx
```

Full end-to-end test. It signs in as the admin and tries every feature: children, uploads, video conversion and playback, likes and comments, members and permissions, sync, look-back and export. **It creates test data** (children named "Smoke Mia", "Bump" and "Offline kid", and a test member), so run it before the family starts, or delete that data afterwards in the app.

```sh
sudo apt install -y jq uuid-runtime
/srv/kinlore/scripts/smoke-test.sh https://photos.example.com you@example.com 'your-admin-password'
```

The last line must say `Failed: 0`. When the server already holds many large videos, the export step needs longer: put `SMOKE_EXPORT_WAIT=600` in front of the command.

## 10. Update

```sh
cd /srv/kinlore && sudo git pull
cd kinlore-api
sudo composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache && sudo -u www-data php artisan view:cache
sudo systemctl restart kinlore-queue
sudo systemctl reload php8.5-fpm
```

Make a backup first.

## 11. Backups

Three things must be saved: the database, the media folder and `.env` (it holds `APP_KEY`).

```sh
sudo /srv/kinlore/deploy/bare-metal/kinlore-backup.sh /mnt/backup/kinlore
```

The script makes a consistent copy of the SQLite database while the server runs, copies the media with `rsync`, and saves `.env`. Run it every night with cron (`sudo crontab -e`):

```
30 3 * * * /srv/kinlore/deploy/bare-metal/kinlore-backup.sh /mnt/backup/kinlore
```

With PostgreSQL, replace the database line by `pg_dump -U kinlore kinlore > "$DEST/database.sql"`.

**Restore** on a fresh install (steps 1 to 4 done):

```sh
sudo systemctl stop kinlore-queue php8.5-fpm
sudo cp /mnt/backup/kinlore/database.sqlite /var/lib/kinlore/database.sqlite
sudo rsync -a /mnt/backup/kinlore/media/ /var/lib/kinlore/media/
sudo cp /mnt/backup/kinlore/env /srv/kinlore/kinlore-api/.env
sudo chown -R www-data:www-data /var/lib/kinlore
sudo chown root:www-data /srv/kinlore/kinlore-api/.env
cd /srv/kinlore/kinlore-api && sudo -u www-data php artisan migrate --force && sudo -u www-data php artisan config:cache
sudo systemctl start php8.5-fpm kinlore-queue
```

A video that was being converted at the moment of the backup stays at "processing" for about an hour after a restore and then shows as failed. Delete it in the app and upload it again; the original file is not lost.

The admin can also make a full ZIP export of all memories in the app (Settings → Export).

## 12. Everyday commands

Run them in `/srv/kinlore/kinlore-api`.

| Task | Command |
|---|---|
| Someone forgot a password | `sudo -u www-data php artisan kinlore:reset-password ann@example.com` |
| See the worker's log | `journalctl -u kinlore-queue -n 50` |
| See the server's log | `sudo tail -n 50 storage/logs/laravel-$(date +%F).log` |
| See failed video or export jobs | `sudo -u www-data php artisan queue:failed` |
| Try failed jobs again | `sudo -u www-data php artisan queue:retry all` |
| Check storage | `sudo -u www-data php artisan seaweedfs:test` |

## 13. Problems and causes

| What you see | Cause and fix |
|---|---|
| The browser shows "502" or "Bad Gateway" | PHP-FPM is not running, or the web server cannot reach its socket. Run `systemctl status php8.5-fpm`. For Caddy, check step 6: `usermod -aG www-data caddy`, then restart Caddy. |
| A page shows "500" | Read the server's log (table above). Often `storage` or `/var/lib/kinlore` is not owned by `www-data`. |
| A changed setting has no effect | Run `sudo -u www-data php artisan config:cache`. |
| A large upload fails with "413" or "POST data is too large" | Step 4 is missing, or PHP-FPM was not restarted. The limit is 512 MB per file. |
| A video stays at "processing" | The worker is not running: `systemctl status kinlore-queue`. FFmpeg must be at `/usr/bin/ffmpeg`. |
| Videos do not play on an iPhone, or links start with `http://` | `APP_URL` must be the public `https://` address. Run `config:cache` after the change. |
| Caddy gets no certificate | The host name does not point at this machine, or port 80 or 443 is closed. Read `journalctl -u caddy -n 50`. |

## 14. Another reverse proxy in front

If this machine already runs a reverse proxy for other sites, add Kinlore to it and let it pass requests to the web server of step 6 on another port, or serve Kinlore from that proxy directly with the files of step 6. The proxy must forward `Host`, `X-Forwarded-Proto`, `X-Forwarded-Host` and `X-Forwarded-For`. `TRUSTED_PROXIES=127.0.0.1` in `.env` makes Kinlore trust these headers from this machine. For a proxy on another machine, put its address there.

## Test this guide

```sh
scripts/test-bare-metal.sh
```

The script needs only Docker. It starts a clean Debian 13 container and follows this guide there, with Caddy on port 80 and nginx on port 8081. The GitHub workflow runs it on every push.

- **Covered:** the packages, the install and settings, the PHP limits, the database, both web server files, the worker (started with the exact command and folder from `kinlore-queue.service`), the quick checks, the backup script, a restore from that backup, the update commands, the password reset, and the storage and failed-job commands. It also uploads and downloads a 300 MB video through each web server and runs the end-to-end test against each.
- **Not covered:** a container has no `systemd`, no public host name and no firewall. So the script does not run the `systemctl`, `certbot`, `ufw`, `cron` and `git` commands, and it does not get a real HTTPS certificate. It starts PHP-FPM, the web servers and the worker by hand, uses `:80` as the site address, and copies the code in place of `git clone` and `git pull`.

Developers can also run the unit and feature tests of the API. They use an in-memory database and touch no data. The setup is in [CONTRIBUTING.md](../CONTRIBUTING.md).
