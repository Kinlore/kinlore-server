# Quick start

This tutorial takes you from an empty Linux machine to a running Kinlore server. You then create the admin account and sign in with the app.

## What you get

- The Kinlore server in Docker, with its database and media storage.
- HTTPS with a free certificate, made and renewed by the server.
- An admin account, and the app signed in to your server.

## What you need

- A Linux machine with at least 2 GB of memory and enough disk space for your photos and videos. See [Requirements](/self-hosting/requirements).
- A host name that points at the machine, for example `photos.example.com`. Ports 80 and 443 must be open to the internet.
- A terminal on the machine, with `sudo` rights.
- The Kinlore app on your phone. See [Install the app](/app/install).

::: tip Only for your home network?
You can skip the host name and HTTPS. Leave `KINLORE_DOMAIN` empty in step 4, and use the address of the machine, for example `http://192.168.1.20`. The app accepts plain HTTP on a home network. See [Connect to a server](/app/connect).
:::

## 1. Install Docker

Docker runs the server in containers. A container is a small, closed environment for one program. Install Docker with its official script:

```sh
curl -fsSL https://get.docker.com | sudo sh
```

Check it:

```sh
sudo docker compose version
```

The command prints a version number. If it prints an error, follow the install guide on the Docker website for your system.

## 2. Get the server code

```sh
sudo apt install -y git openssl
git clone https://github.com/Kinlore/kinlore-server.git
cd kinlore-server
```

## 3. Create the settings file

```sh
cp .env.example .env
```

Make the secret values. Each command prints a random value:

```sh
echo "base64:$(openssl rand -base64 32)"   # for APP_KEY
openssl rand -hex 32                       # for DB_PASSWORD
openssl rand -hex 32                       # for SEAWEEDFS_KEY
openssl rand -hex 32                       # for SEAWEEDFS_SECRET
```

## 4. Fill in the settings

Open the file:

```sh
nano .env
```

Fill in these lines. Use your own values and your own host name:

```ini
APP_KEY=base64:the-value-from-step-3
APP_URL=https://photos.example.com
DB_PASSWORD=the-value-from-step-3
SEAWEEDFS_KEY=the-value-from-step-3
SEAWEEDFS_SECRET=the-value-from-step-3
KINLORE_DOMAIN=photos.example.com
```

`KINLORE_DOMAIN` is commented out in the file. Remove the `#` at the start of its line. Save with `Ctrl+O`, then `Enter`, and close with `Ctrl+X`.

Keep a copy of `.env` in a safe place. Without it, a backup cannot be used. Every setting is explained in [Configuration](/self-hosting/configuration).

## 5. Start the server

```sh
sudo docker compose up -d --build
```

The first start builds the server image. This takes some minutes.

## 6. Check that it runs

```sh
sudo docker compose ps
curl -fsS https://photos.example.com/api/health
```

All containers show `running` or `healthy`. The `curl` command prints a line that starts with `{"status":"ok"`.

## 7. Get the setup token

The setup page asks for a setup token. The token proves that you are the owner of the server. Print it:

```sh
sudo docker compose exec api php artisan kinlore:setup-token
```

The command prints `Setup token:` and the token. Copy the token.

## 8. Create the admin account

1. Open `https://photos.example.com` in a web browser. The server sends you to the setup page.
2. Enter your name, your email address and a password.
3. Enter a family name, for example "Anna Example Family". You can leave it empty and change it later in the app.
4. Paste the setup token.
5. Send the form. The page shows the server address and the email address to use in the app.

## 9. Connect the app

1. Open the Kinlore app on your phone.
2. Enter the server address, for example `photos.example.com`.
3. Sign in with the email address and password of step 8.

The app shows your family name. You can now add a child and the first moments. The details are in [Connect to a server](/app/connect).

## Check that it worked

- The browser shows "Kinlore is running" at `https://photos.example.com`.
- The app is signed in and shows your family name.

## When it does not work

| What you see | What to do |
|---|---|
| `docker compose up` stops with `Set APP_KEY in the .env file` (or another name) | That value is empty in `.env`. Fill it in and run step 5 again. |
| `curl` cannot connect, or the certificate is not trusted | The host name does not point at the machine, or port 80 or 443 is closed. Check the log: `sudo docker compose logs api`. |
| The setup page says the token is wrong | Run step 7 again and copy the full token. |
| The app cannot reach the server | Check the address and the network of the phone. See [App troubleshooting](/app/troubleshooting). |

More causes and fixes are in [Troubleshooting](/self-hosting/troubleshooting).

## Next steps

- [Back up the server](/self-hosting/backup-and-restore). Do this before the family adds many photos.
- [Invite your family](/tutorials/invite-your-family).
- [Upload your phone photos](/tutorials/upload-your-phone-photos).
