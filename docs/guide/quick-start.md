# Quick start

This page is the fastest way to a running Kinlore server: one Linux machine with built-in HTTPS, then the app signed in. For other setups, see [Install with Docker Compose](/self-hosting/docker).

**What you need:**

- A Linux machine with at least 2 GB of memory and `sudo` rights. See [Requirements](/self-hosting/requirements).
- A host name that points at the machine, for example `photos.example.com`. Ports 80 and 443 must be open to the internet.
- The Kinlore app on your phone. See [Install the app](/app/install).

## 1. Install Docker and get the files

Docker runs the server in containers. A container is a closed environment for one program.

```sh [Install Docker and get the files]
curl -fsSL https://get.docker.com | sudo sh
sudo apt install -y git openssl
git clone https://github.com/Kinlore/kinlore-server.git
cd kinlore-server
```

## 2. Make the settings file

```sh [Make the settings file with new secrets]
cp .env.example .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 32)|" .env
sed -i "s|^SEAWEEDFS_KEY=.*|SEAWEEDFS_KEY=$(openssl rand -hex 32)|" .env
sed -i "s|^SEAWEEDFS_SECRET=.*|SEAWEEDFS_SECRET=$(openssl rand -hex 32)|" .env
```

Open the file with `nano .env`. Set your host name, and the standard web ports 80 and 443 that the certificate needs:

```diff [.env]
-APP_URL=https://photos.example.com   # the address the phones use
+APP_URL=https://photos.example.com
-# KINLORE_DOMAIN=photos.example.com
+KINLORE_DOMAIN=photos.example.com
-# APP_PORT=80
+APP_PORT=80
-# APP_SSL_PORT=443
+APP_SSL_PORT=443
```

Save with `Ctrl+O`, then `Enter`, and close with `Ctrl+X`. Keep a copy of `.env` in a safe place. Without it, a backup cannot be used.

## 3. Start the server

```sh [Start the server]
sudo docker compose up -d --build
```

The first start builds the server image. This takes some minutes. Then check it:

```sh [Check the server]
curl -fsS https://photos.example.com/api/health
```

The command prints a line that starts with `{"status":"ok"`.

## 4. Create the admin account

Print the setup token. The token proves that you own the server:

```sh [Print the setup token]
sudo docker compose exec api php artisan kinlore:setup-token
```

1. Open `https://photos.example.com` in a web browser. The server sends you to the setup page.
2. Enter your name, your email address, a password and a family name, for example "Anna Example Family".
3. Paste the setup token and send the form.

## 5. Connect the app

1. Open the Kinlore app on your phone.
2. Enter the server address, for example `photos.example.com`.
3. Sign in with the email address and password of step 4.

The app shows your family name. The details are in [Connect to a server](/app/connect).

::: details It does not work
| What you see | What to do |
|---|---|
| `Set APP_KEY in the .env file` (or another name) | That line is empty in `.env`. Run step 2 again. |
| `curl` cannot connect, or the certificate is not trusted | The host name does not point at the machine, or port 80 or 443 is closed. Read `sudo docker compose logs api`. |
| The setup page says the token is wrong | Print it again and copy all of it. |
| The app cannot reach the server | See [App troubleshooting](/app/troubleshooting). |

More fixes are in [Troubleshooting](/self-hosting/troubleshooting).
:::

## Next steps

- [Back up the server](/self-hosting/backup-and-restore). Do this before the family adds many photos.
- [Invite your family](/tutorials/invite-your-family)
- [Upload your phone photos](/tutorials/upload-your-phone-photos)
