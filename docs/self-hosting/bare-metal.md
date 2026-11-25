# Install without Docker

This guide installs the Kinlore server directly on a Linux machine, without Docker. You install PHP, the database, the web server with HTTPS, and the two workers that convert videos and build exports.

The script `scripts/test-bare-metal.sh` runs the commands of this guide on a clean Debian 13, with both web servers, on each change of the code. See [Test this guide](#test-this-guide).

## What you get

- The code in `/srv/kinlore`.
- The data in `/var/lib/kinlore`: the database file and all photos and videos.
- PHP-FPM, which runs PHP as the user `www-data`.
- Caddy or nginx in front, with HTTPS.
- Two `systemd` services for the workers: `kinlore-queue` converts videos, and `kinlore-queue-default` builds exports.

## What you need

- Debian 13, or Ubuntu 24.04 or later, with `sudo` rights.
- For HTTPS: a host name that points at the machine, and ports 80 and 443 open.

The ready-made configuration files are in the folder [`deploy/bare-metal/`](https://github.com/Kinlore/kinlore-server/tree/develop/deploy/bare-metal):

| File | Goes to | Purpose |
|---|---|---|
| `env.example` | `kinlore-api/.env` | Settings: SQLite, local media, no Redis |
| `php-kinlore.ini` | `/etc/php/8.5/fpm/conf.d/99-kinlore.ini` and `/etc/php/8.5/cli/conf.d/99-kinlore.ini` | Upload size and memory limits |
| `php-fpm-kinlore.conf` | `/etc/php/8.5/fpm/pool.d/zz-kinlore.conf` | 20 PHP workers, so long uploads do not block the API |
| `Caddyfile` | `/etc/caddy/Caddyfile` | Web server with automatic HTTPS (recommended) |
| `nginx.conf` | `/etc/nginx/sites-available/kinlore` | Web server, if you prefer nginx |
| `kinlore-queue.service` | `/etc/systemd/system/` | Worker for videos |
| `kinlore-queue-default.service` | `/etc/systemd/system/` | Worker for exports |
| `kinlore-backup.sh` | stays in the code folder | Backup of database, media and settings |

## 1. Install the packages

Kinlore needs PHP 8.5, Composer, FFmpeg and a web server. Debian 13 and Ubuntu 24.04 do not ship PHP 8.5. So first add a PHP package source.

On Debian:

```sh
sudo apt update && sudo apt install -y curl ca-certificates lsb-release
sudo curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
```

On Ubuntu:

```sh
sudo add-apt-repository ppa:ondrej/php
```

Then, on both:

```sh
sudo apt update
sudo apt install -y php8.5-fpm php8.5-cli php8.5-sqlite3 php8.5-pgsql php8.5-gd php8.5-zip \
  php8.5-intl php8.5-mbstring php8.5-bcmath php8.5-xml php8.5-curl \
  ffmpeg libheif-examples composer git unzip curl sqlite3 rsync
```

`libheif-examples` gives the `heif-dec` command. The server needs it for HEIC and HEIF photos from iPhones.

Install one web server:

```sh
sudo apt install -y caddy                                   # Caddy, or:
sudo apt install -y nginx certbot python3-certbot-nginx     # nginx
```

## 2. Get the code and make the data folder

```sh
sudo git clone https://github.com/Kinlore/kinlore-server.git /srv/kinlore
sudo mkdir -p /var/lib/kinlore/media
sudo touch /var/lib/kinlore/database.sqlite
sudo chown -R www-data:www-data /var/lib/kinlore
```

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

The settings file uses SQLite and the local disk. So you need no database server, no Redis and no S3 storage. The file explains each value. Two other choices are in it as comments:

- **PostgreSQL instead of SQLite**, for example when several services share one database server:
  ```sh
  sudo apt install -y postgresql
  sudo -u postgres psql -c "CREATE USER kinlore WITH PASSWORD 'a-long-random-password';"
  sudo -u postgres psql -c "CREATE DATABASE kinlore OWNER kinlore;"
  ```
  Then set `DB_CONNECTION=pgsql` and the `DB_*` values in `.env`.
- **S3 storage instead of the local disk:** set `SEAWEEDFS_DRIVER=s3` and the `SEAWEEDFS_*` values. See [Configuration](/self-hosting/configuration#media-storage).

## 4. Allow large uploads in PHP

```sh
sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/fpm/conf.d/99-kinlore.ini
sudo cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/cli/conf.d/99-kinlore.ini
sudo cp ../deploy/bare-metal/php-fpm-kinlore.conf /etc/php/8.5/fpm/pool.d/zz-kinlore.conf
sudo systemctl restart php8.5-fpm
```

One photo or video can be up to 500 MB. The memory limit is 1 GB, because PHP decodes a large photo in memory to make its previews. Each upload holds one PHP-FPM worker until it ends, so the pool file gives PHP-FPM 20 workers. On a machine with 1 or 2 GB of memory, read [Troubleshooting](/self-hosting/troubleshooting#large-photo-uploads-fail-on-a-small-server).

## 5. Create the database

```sh
sudo -u www-data php artisan migrate --force --seed
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

`--seed` adds the list of milestones. Run the three `cache` commands again after every change of `.env`.

## 6. Set up the web server

Choose one. Both serve the folder `kinlore-api/public` and pass PHP requests to PHP-FPM.

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

If you use `ufw`, open the web ports:

```sh
sudo ufw allow 80,443/tcp
sudo ufw allow 443/udp      # HTTP/3, Caddy only
```

## 7. Start the workers for videos and exports

The server converts videos for streaming in the background, and builds exports there too. Two workers do this. An export can take up to an hour. With its own worker, it does not hold up new videos.

```sh
sudo cp ../deploy/bare-metal/kinlore-queue.service ../deploy/bare-metal/kinlore-queue-default.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now kinlore-queue kinlore-queue-default
systemctl status kinlore-queue kinlore-queue-default --no-pager
```

Both show `active (running)`. Two workers are enough for a family.

## 8. Create the admin account

Print the setup token:

```sh
sudo -u www-data php artisan kinlore:setup-token
```

Then open your address (`APP_URL`) in a web browser and fill in the setup page. The details are in [Create the first admin](/self-hosting/first-admin).

## Check that it worked

```sh
curl -fsS https://photos.example.com/api/health              # {"status":"ok",...}
curl -fsS https://photos.example.com/api/auth/setup-status   # "needs_setup":false after step 8
curl -s -o /dev/null -w '%{http_code}\n' https://photos.example.com/.env   # must print 404
sudo -u www-data php artisan about --only=environment        # Environment: production, Debug Mode: OFF
systemctl is-active php8.5-fpm kinlore-queue kinlore-queue-default caddy   # or nginx
```

For a test of every feature, run the end-to-end test. It creates test data, so run it before the family starts. See [Commands](/reference/commands#end-to-end-test).

## When it does not work

| What you see | Cause and fix |
|---|---|
| The browser shows "502" or "Bad Gateway" | PHP-FPM is not running, or the web server cannot reach its socket. Run `systemctl status php8.5-fpm`. For Caddy, check step 6: `sudo usermod -aG www-data caddy`, then restart Caddy. |
| A page shows "500" | Read the server log (see [Maintenance](/self-hosting/maintenance#logs)). Often `storage` or `/var/lib/kinlore` is not owned by `www-data`. |
| A changed setting has no effect | Run `sudo -u www-data php artisan config:cache`. |
| A large upload fails with "413" or "POST data is too large" | Step 4 is missing, or PHP-FPM was not restarted. |
| A video stays at "processing" | The video worker is not running: `systemctl status kinlore-queue`. FFmpeg must be at `/usr/bin/ffmpeg`. |
| Videos do not play on an iPhone, or links start with `http://` | `APP_URL` must be the public `https://` address. Run `config:cache` after the change. |
| Caddy gets no certificate | The host name does not point at this machine, or port 80 or 443 is closed. Read `journalctl -u caddy -n 50`. |

More is in [Troubleshooting](/self-hosting/troubleshooting).

## Another reverse proxy in front

If this machine already runs a reverse proxy for other sites, see [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy#host-install-behind-another-proxy).

## Test this guide

```sh
scripts/test-bare-metal.sh
```

The script needs only Docker. It starts a clean Debian 13 container and follows this guide there, with Caddy on port 80 and nginx on port 8081. The GitHub workflow runs it on every push.

- **Covered:** the packages, the install and settings, the PHP limits, the database, both web server files, both workers, the admin account on the setup page with the token of `kinlore:setup-token`, the checks above, the backup script, a restore from that backup, the update commands, the password reset, and the storage and failed-job commands. It also uploads and downloads a 300 MB video through each web server and runs the end-to-end test against each.
- **Not covered:** a container has no `systemd`, no public host name and no firewall. So the script does not run the `systemctl`, `certbot`, `ufw`, `cron` and `git` commands, and it gets no real HTTPS certificate.

## Next steps

- [Backup and restore](/self-hosting/backup-and-restore)
- [Updating](/self-hosting/updating)
- [Maintenance](/self-hosting/maintenance)
