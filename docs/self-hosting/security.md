# Security

Your server holds private photos of children. This page lists what Kinlore does to protect them, and what you must do as the owner.

## What Kinlore does

- **Setup token.** The setup page always asks for a setup token. A stranger cannot take over a new server before you create the admin.
- **Invitations only.** Nobody can sign up without an invitation from the admin.
- **The family name stays private.** Public pages and endpoints show `APP_NAME`, not the family name. A stranger who finds the address does not learn whose server it is.
- **Sign-in tokens expire.** A sign-in on a phone stops working after 90 days without use.
- **Rate limits.** The server limits sign-in tries, setup tries and invitation checks per network address. For example, sign-in allows 5 tries per minute.
- **Checked file access.** Every photo goes through the API with a sign-in check. Video and export links are signed and expire: video links after 6 hours, export links after 24 hours.
- **Permissions.** The admin can turn off downloads of original files for each family member.
- **Closed ports in Docker.** PostgreSQL, Redis and the media storage have no port on the host. Only the API container uses them.
- **No root for videos.** The worker that runs FFmpeg on uploaded videos runs as the user `www-data`, not as root.
- **Hidden files.** The web server never serves hidden files such as `.env`.

## What you must do

1. **Use HTTPS** for a server on the internet. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).
2. **Keep the secret values secret.** `APP_KEY`, `DB_PASSWORD`, `SEAWEEDFS_KEY` and `SEAWEEDFS_SECRET` must be long random values. Make them with `openssl`, as the install guides show. On a host install, `.env` belongs to `root:www-data` with the mode `640`.
3. **Keep `APP_DEBUG=false`** on a real server. Error details can show private data.
4. **Set `TRUSTED_PROXIES`** to the address of your reverse proxy, if you use one. The default trusts all private networks.
5. **Finish the setup soon** after the first start, and keep the setup token to yourself.
6. **Update** when a new release comes out. Only the newest minor version gets security fixes. See [Updating](/self-hosting/updating).
7. **Make backups**, and keep them in a second place. A backup holds the same private data as the server. Protect it the same way. See [Backup and restore](/self-hosting/backup-and-restore).
8. **Open only the needed ports** in your firewall: 80 and 443, or the port of your proxy.
9. **Give the admin role** only to a person you trust. The admin sees all members and can export everything.

## Check your server

```sh
curl -s -o /dev/null -w '%{http_code}\n' https://photos.example.com/.env   # must print 404
```

On a host install, also run `sudo -u www-data php artisan about --only=environment`. It must show `Environment: production` and `Debug Mode: OFF`.

## Report a security problem

Do not open a public issue. Follow [SECURITY.md](https://github.com/Kinlore/kinlore-server/blob/develop/SECURITY.md). It explains how to report a problem in private.
