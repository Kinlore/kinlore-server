# Move or restore a server

This tutorial moves a Kinlore server with Docker to a new machine. The same steps restore a server from a backup after a broken disk. You also use them to test your backup.

## What you get

- The same server on a new machine, with all accounts, children, moments, photos and videos.
- The phones keep working, if the server address stays the same.

## What you need

- The old server, or a backup of it. See [Backup and restore](/self-hosting/backup-and-restore).
- A new machine with Docker. See [Requirements](/self-hosting/requirements).
- A way to copy files between the machines, for example `scp` or a USB disk.
- Some time in which the family does not use the server. The old server is offline while you make the backup.

For a host install without Docker, follow [Full restore](/self-hosting/backup-and-restore#full-restore) on the new machine instead.

## 1. Prepare the new machine

Install Docker and get the code:

```sh
curl -fsSL https://get.docker.com | sudo sh
sudo apt install -y git
git clone https://github.com/Kinlore/kinlore-server.git
cd kinlore-server
```

Use the same release as the old server. On the old server, `git describe --tags` shows it. On the new machine, run `git checkout` with that tag, for example `git checkout v1.1.0`.

## 2. Make a fresh backup on the old server

Skip this step when the old server is gone and you restore an existing backup.

On the old server, in the folder with `docker-compose.yml`, run the commands of [Make a backup](/self-hosting/backup-and-restore#make-a-backup). Tell the family that the server is offline for some minutes.

Do not add new moments on the old server after this step. They are not in the backup.

## 3. Copy the backup to the new machine

Put the folder `backup` next to `docker-compose.yml` on the new machine. For example, from the old server:

```sh
scp -r backup user@new-machine.example.com:kinlore-server/
```

Check that the folder holds `kinlore-db.sql`, three `.tar.gz` files and `env`.

## 4. Restore on the new machine

In the folder `kinlore-server` on the new machine, run the commands of [Restore](/self-hosting/backup-and-restore#restore), starting at `cp backup/env .env`.

The last command prints a line that starts with `{"status":"ok"`.

## 5. Point the address at the new machine

- **Same host name:** change the DNS record of your host name, for example `photos.example.com`, to the address of the new machine. DNS is the system that turns host names into addresses. A change can take some time to reach every phone. With `KINLORE_DOMAIN` set, the new server gets its own certificate after the change.
- **New address:** set `APP_URL` (and `KINLORE_DOMAIN`, if you use it) in `.env` to the new address, and run `docker compose up -d`. Each member must then add the new address in the app. See [Connect to a server](/app/connect).

## 6. Stop the old server

When the new server works, stop the old one, so nobody adds moments there:

```sh
docker compose down
```

Do not delete the old volumes yet. Keep them for some days, until you are sure that all data is on the new machine.

## Check that it worked

1. Open the server address in a browser. It shows "Kinlore is running".
2. Open the app and sign in. The children and the moments are there.
3. Open some photos, and play a video.
4. As the admin, check the members in the app.

## When it does not work

- **The app shows no photos, but the moments are there.** The media copy is missing or empty. Check the three `.tar.gz` files, and run the restore again on a machine without the Kinlore volumes.
- **Videos do not play, or the log shows errors about decryption.** The `.env` file is not the one of the backup, so `APP_KEY` does not match. Copy `backup/env` to `.env` again, and run `docker compose up -d`.
- **`docker compose create` says that the containers already exist.** This machine has an old Kinlore install. Restore on a clean machine, or remove the old containers and volumes first. Removing volumes deletes their data, so be sure that you do not need it.
- More fixes are in [Troubleshooting](/self-hosting/troubleshooting).
