# Create the first admin

The admin account is created in a web browser, on the setup page of the server. The app never creates the admin. Everyone else joins later with an invitation.

## What you get

- The admin account of your server.
- The family name of the server.

## What you need

- A running server. See [Install with Docker](/self-hosting/docker) or [Install without Docker](/self-hosting/bare-metal).
- A web browser that can reach the server address (`APP_URL`).
- A terminal on the server, to print the setup token.

## The setup token

The setup page always asks for a setup token. So a stranger cannot take over a server that is online before you finish the setup.

- When `SETUP_TOKEN` is set in the settings, its value is the token.
- When `SETUP_TOKEN` is empty, the server makes a random token. The server deletes it when the admin account exists.

## Steps

1. Print the setup token.

   Docker:
   ```sh
   docker compose exec api php artisan kinlore:setup-token
   ```
   The Docker container also prints the token in its log at each start: `docker compose logs api | grep "Setup token"`.

   Without Docker, in `/srv/kinlore/kinlore-api`:
   ```sh
   sudo -u www-data php artisan kinlore:setup-token
   ```
   The command prints `Setup token:` and the token. If `SETUP_TOKEN` is set, it tells you to use that value.
2. Open the server address in a web browser, for example `https://photos.example.com`. An empty server sends you to `/setup`.
3. Enter your name, your email address and a password two times. The password needs at least 8 characters.
4. Enter a family name, or leave the field empty. Empty gives your full name and "Family", for example "Anna Example Family". The name can have up to 60 characters. You can change it later in the app.
5. Paste the setup token and send the form.

The page shows "Your admin account is ready", with the server address and the email address to use in the app.

## Check that it worked

```sh
curl -fsS https://photos.example.com/api/auth/setup-status
```

The answer contains `"needs_setup":false`. The address `https://photos.example.com` now shows "Kinlore is running".

Then sign in with the app. See [Connect to a server](/app/connect).

## When it does not work

- **The form says the token is wrong.** Print it again and copy all of it. If `SETUP_TOKEN` is set, enter that value.
- **The browser shows "429" or "Too Many Requests".** The form accepts 5 tries per minute. Wait one minute.
- **The browser does not show the setup page, but "Kinlore is running".** The server already has a user. Use the existing admin account, or reset its password. See [Maintenance](/self-hosting/maintenance#forgotten-password).
- **The pages use `http://` behind a reverse proxy.** The server does not trust the headers of your proxy. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).

## Create the admin without a browser

For automated installs, set these three values before the first start:

```ini
SEED_USER_NAME=Anna Example
SEED_USER_EMAIL=anna@example.com
SEED_USER_PASSWORD=a-long-password
```

The seeder then creates the admin, and the web setup is skipped. The seeder is the part of the server that fills the database with first data.

- **Docker:** put the lines in `.env` next to `docker-compose.yml`. The container runs the seeder at every start. It creates the admin only while the server has no user.
- **Without Docker:** put the lines in `kinlore-api/.env` before step 5 of the install. Remove them afterwards.

## Next steps

- [Invite your family](/tutorials/invite-your-family)
- [Backup and restore](/self-hosting/backup-and-restore)
