# Create the first admin

This page creates the admin account and the family name of your server. You do this in a web browser, on the setup page. The app never creates the admin. Everyone else joins later with an invitation.

**What you need:**

- A running server. See [Install with Docker Compose](/self-hosting/docker) or [Host install without Docker](/self-hosting/bare-metal).
- A web browser that can reach the server address (`APP_URL`).
- A terminal on the server, to print the setup token.

## The setup token

The setup page always asks for a setup token. So a stranger cannot take over a new server before you finish the setup.

- When `SETUP_TOKEN` is set in the settings, its value is the token.
- When `SETUP_TOKEN` is empty, the server makes a random token. The server deletes it when the admin account exists.

## Steps

1. Print the setup token.

   ::: code-group
   ```sh [Docker]
   docker compose exec api php artisan kinlore:setup-token
   ```
   ```sh [Host install]
   cd /srv/kinlore/kinlore-api
   sudo -u www-data php artisan kinlore:setup-token
   ```
   :::

   The command prints `Setup token:` and the token. If `SETUP_TOKEN` is set, it tells you to use that value. In Docker, the token is also in the log of each start: `docker compose logs api | grep "Setup token"`.
2. Open the server address in a web browser, for example `https://photos.example.com`. An empty server sends you to `/setup`.
3. Enter your **First name** and **Last name**.
4. Check the **Friendly name**. It fills in from your name as you type, for example "Anna Example Family". Change it to anything you like, for example "The Smiths": after you type in it, it no longer follows your name. Your family sees this name in the app and on invitations, and the app shows it as the name of the server. It can have up to 60 characters, and you can change it later in the app. An empty field gives the same suggestion.
5. Enter your email address and a password two times. The password needs at least 8 characters.
6. Paste the setup token and send the form.

The page shows "Your admin account is ready", with the server address and the email address to use in the app.

## Check that it worked

```sh [Check the setup]
curl -fsS https://photos.example.com/api/auth/setup-status
```

The answer contains `"needs_setup":false`. The address `https://photos.example.com` now shows "Kinlore is running".

::: details It does not work
- **The form says the token is wrong.** Print it again and copy all of it. If `SETUP_TOKEN` is set, enter that value.
- **The browser shows "429" or "Too Many Requests".** The form accepts 5 tries per minute. Wait one minute.
- **The browser shows "Kinlore is running", not the setup page.** The server already has a user. Use the existing admin account, or reset its password. See [Forgotten password](/self-hosting/maintenance#forgotten-password).
- **The pages use `http://` behind a reverse proxy.** The server does not trust the headers of your proxy. See [Trusted proxies](/self-hosting/https-and-reverse-proxy#trusted-proxies).
:::

## Create the admin without a browser

For automated installs, set these three values before the first start:

```ini [.env]
SEED_USER_NAME=Anna Example
SEED_USER_EMAIL=anna@example.com
SEED_USER_PASSWORD=a-long-password
```

The seeder then creates the admin, and you skip the setup page. The seeder is the part of the server that fills the database with first data.

- **Docker:** put the lines in `.env`. The container runs the seeder at every start. It creates the admin only while the server has no user.
- **Host install:** put the lines in `kinlore-api/.env` before step 5 of the install. Remove them afterwards.

## Next steps

- [Connect the app](/app/connect)
- [Invite your family](/tutorials/invite-your-family)
- [Backup and restore](/self-hosting/backup-and-restore)
