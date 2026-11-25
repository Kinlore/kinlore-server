# FAQ

## Where do my photos go?

To your own server only. The app sends them to the server address that you enter. Kinlore has no central service.

## Is the app in the App Store or Google Play?

See [Install the app](/app/install).

## Can one server hold several families?

No. One server holds one family. A second family runs a second server. The app can hold several servers. See [Several servers](/app/multiple-servers).

## Do I need a host name and HTTPS?

Only for use on the internet. iPhones need HTTPS for an address on the internet. At home, the local address of the machine is enough, for example `http://192.168.1.20:28461` with Docker. See [HTTPS and reverse proxy](/self-hosting/https-and-reverse-proxy).

## Can I keep the data in a folder of my choice?

Yes. With Docker, one line in `.env` puts all data in one folder, for example on a second disk. See [Data locations](/self-hosting/data-locations).

## Docker or not?

Choose Docker when you are not sure. One command starts every part. See [Install with Docker Compose](/self-hosting/docker). Without Docker, you install PHP and a web server yourself, but you need less: SQLite and a folder on the disk are enough. See [Requirements](/self-hosting/requirements).

## Does the server change my photos?

No. The server keeps each original file as it was uploaded, also HEIC photos from iPhones. It makes extra preview images and, for videos, a stream for playback.

## How large can a file be?

One photo or video can be up to 500 MB.

## How do I create the admin account?

In a web browser, on the setup page of the server. The app never creates the admin. See [Create the first admin](/self-hosting/first-admin).

## How do family members join?

The admin invites them in the app. Each member gets an invitation link. See [Invite your family](/tutorials/invite-your-family).

## Someone forgot a password. What now?

The admin sets a new password in the app. The server owner can also reset any password with a command. See [Forgotten password](/self-hosting/maintenance#forgotten-password).

## Can I take all my data out?

Yes. The admin can download a ZIP file with all original photos and videos and all moments as JSON. See [Export](/app/export). For the full server with its accounts, make a backup. See [Backup and restore](/self-hosting/backup-and-restore).

## Is the export a backup?

No. An export holds the photos, videos and texts, but not the accounts and settings. A backup brings back the whole server.

## Does Kinlore send email or push notifications?

No. Notifications are planned. See [Roadmap](/project/roadmap).

## What does Kinlore cost?

Kinlore is free software under the GNU Affero General Public License, version 3. You pay only for your own machine or hosting. See [License](/project/license).

## Where do I report a bug?

See [Contributing](/project/contributing). Report security problems in private, as [SECURITY.md](https://github.com/Kinlore/kinlore-server/blob/develop/SECURITY.md) explains.

## Next steps

- [Requirements](/self-hosting/requirements)
- [Updating](/self-hosting/updating)
- [Troubleshooting](/self-hosting/troubleshooting)
