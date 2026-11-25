# Install with Docker Compose (recommended)

This page installs the Kinlore server with Docker Compose. One command starts the API, the database and the media storage.

**What you need:** a Linux machine with Docker and Docker Compose 2.24.4 or newer, `git` and `openssl`. See [Requirements](/self-hosting/requirements).

## Step 1: Get the files

::: tip Permission denied?
The commands on this page run `docker` without `sudo`. Add your user to the `docker` group once with `sudo usermod -aG docker $USER`, then sign out and in again. Or put `sudo` before each `docker` command.
:::

```sh [Get the newest release]
git clone https://github.com/Kinlore/kinlore-server.git
cd kinlore-server
```

The `main` branch always holds the newest release. Run all other commands on this page in the folder `kinlore-server`.

## Step 2: Fill in `.env`

The file `.env` holds your settings. Docker Compose reads it from the folder of `docker-compose.yml`. These commands copy the example file and write a new random secret into each required line:

```sh [Make the secrets]
cp .env.example .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 32)|" .env
sed -i "s|^SEAWEEDFS_KEY=.*|SEAWEEDFS_KEY=$(openssl rand -hex 32)|" .env
sed -i "s|^SEAWEEDFS_SECRET=.*|SEAWEEDFS_SECRET=$(openssl rand -hex 32)|" .env
```

Then open the file with `nano .env` and set `APP_URL`. This is the address that the phones use. The required lines now look like this:

```ini [.env]
APP_KEY=base64:...            # the secret key of the server
APP_URL=https://photos.example.com
DB_PASSWORD=...               # the database password
SEAWEEDFS_KEY=...             # the two keys of the media storage
SEAWEEDFS_SECRET=...
```

::: danger Keep `.env` safe, and keep these values
Save a copy of `.env` in a safe place. Without it, a backup cannot be used. Do not change `APP_KEY` or `DB_PASSWORD` after the first start.
:::

All other variables are optional. See [Environment variables](/self-hosting/configuration).

## Step 3: Choose your setup

Add the line of your setup to `.env`. A reverse proxy is a web server in front of Kinlore that handles HTTPS.

| Setup | Line in `.env` | Details |
|---|---|---|
| Built-in HTTPS on this machine. Ports 80 and 443 must be free and open. | `KINLORE_DOMAIN=photos.example.com`<br>`APP_PORT=80`<br>`APP_SSL_PORT=443` | [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy) |
| Behind your own reverse proxy on this machine. The server listens on `127.0.0.1:28461` and trusts the proxy by itself. | `COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml` | [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy#behind-a-reverse-proxy-on-this-machine) |
| Data in a folder of your choice. Set it before the first start. | `COMPOSE_FILE=docker-compose.yml:deploy/docker/bind-mounts.yml`<br>`DATA_LOCATION=/srv/data/kinlore` | [Data locations](/self-hosting/data-locations) |

To use both files, write `COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml:deploy/docker/bind-mounts.yml`.

::: tip Only for your home network?
Add no line. Set `APP_URL` to the address of the machine, for example `http://192.168.1.20:28461`. The server then answers plain HTTP on port 28461.
:::

## Step 4: Start the server

```sh [Start the server]
docker compose up -d --build
```

The first start builds the server image. This takes some minutes. Watch the API come up with `docker compose logs -f api`. The line `Initialization complete` means that the API runs. Stop the log with `Ctrl+C`. Then check the health of all containers:

```sh [Check the server]
docker compose ps
docker compose exec api curl -fsS http://localhost:8080/api/health
```

All containers show `running`, and the API shows `healthy`. The second command prints a line that starts with `{"status":"ok"`.

::: details It does not start
- **`Set APP_KEY in the .env file next to docker-compose.yml`**, or the same message for another name: that line is empty in `.env`.
- **`address already in use`**: another program uses the port in `APP_PORT` or `APP_SSL_PORT` (by default 28461 and 28462).
- **The API restarts again and again:** read `docker compose logs api`. A wrong `DB_PASSWORD` is a common cause.

More fixes are in [Troubleshooting](/self-hosting/troubleshooting).
:::

## Step 5: Create the admin account

The setup page asks for a setup token. The token proves that you own the server. Print it:

```sh [Print the setup token]
docker compose exec api php artisan kinlore:setup-token
```

The API also writes the token in its log at each start: `docker compose logs api | grep "Setup token"`.

Open `APP_URL` in a web browser, for example `https://photos.example.com`. Fill in the form and paste the token. The details are in [Create the first admin](/self-hosting/first-admin).

## Next steps

- [Connect the app](/app/connect)
- [Invite your family](/tutorials/invite-your-family)
- [Back up the server](/self-hosting/backup-and-restore)
- [Updating](/self-hosting/updating)

## Reach a database or storage port from the host

PostgreSQL, Redis and the S3 port of SeaweedFS have no port on the host. To reach one of them, for example with a database tool, make a file `docker-compose.override.yml` next to `docker-compose.yml`. Keep only the services you need, and pick a free host port:

```yaml [docker-compose.override.yml]
services:
  seaweedfs-filer:
    ports:
      - "127.0.0.1:8333:8333"
  postgres:
    ports:
      - "127.0.0.1:5432:5432"   # use 5433:5432 if the host runs its own PostgreSQL
  redis:
    ports:
      - "127.0.0.1:6379:6379"
```

Then run `docker compose up -d`. The address `127.0.0.1` keeps the ports reachable only from this machine. With `COMPOSE_FILE` in `.env`, add `:docker-compose.override.yml` to the end of that line: Docker Compose then does not read the file by itself.
