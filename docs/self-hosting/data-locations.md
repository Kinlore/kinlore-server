# Store the data in a folder of your choice

By default, Docker keeps the data of Kinlore in Docker volumes. Docker manages these volumes in its own folder. The file `deploy/docker/bind-mounts.yml` puts all data in one folder that you choose instead.

## What you get

- All data of the server in one folder, for example on a large second disk.
- One subfolder for each service, so you see where the space goes.
- Simple backups: stop the server and copy the folder.

The server works the same way with or without this file.

## The folder layout

The setting `DATA_LOCATION` in `.env` names the folder. The default is `/srv/data/kinlore`.

```text [Data folder tree]
/srv/data/kinlore/
├── postgres/            the database
├── seaweedfs/
│   ├── master/          media storage: the list of storage parts
│   ├── volume/          media storage: all photos and videos
│   └── filer/           media storage: the file names
├── redis/               cache and rate limits
├── api/
│   └── storage/         logs, sessions and finished exports
└── caddy/
    ├── data/            HTTPS certificates
    └── config/          web server state
```

Each service writes as its own user. Docker makes the missing folders at the first start, and each service sets the owner of its folder. You do not have to make the folders or change owners.

| Folder | Owner (user ID) |
|---|---|
| `postgres` | 70 (`postgres` in the container) |
| `redis` | 999 (`redis` in the container) |
| `api/storage` | 82 (`www-data` in the container) |
| All others | 0 (`root`) |

::: warning Use a local disk with a Linux file system
Use a file system such as ext4 or XFS. A network share (NFS, SMB) or a disk with FAT or NTFS often does not keep the owner of a file. Then the database cannot start.
:::

## Turn it on for a new install

Do this before the first start.

1. Add two lines to `.env` next to `docker-compose.yml`:
   ```diff [.env]
   +COMPOSE_FILE=docker-compose.yml:deploy/docker/bind-mounts.yml
   +DATA_LOCATION=/srv/data/kinlore
   ```
   Use a full path that starts with `/`. Docker Compose reads `COMPOSE_FILE` from `.env` by itself.
2. Start the server:
   ```sh [Start the server]
   docker compose up -d --build
   ```
3. Check that the data is in the folder:
   ```sh [Check the folder]
   sudo du -sh /srv/data/kinlore/*
   ```
   Each subfolder of the layout above is in the list.

::: tip Also behind a reverse proxy?
Put both files in one line, separated by colons: `COMPOSE_FILE=docker-compose.yml:deploy/docker/reverse-proxy.yml:deploy/docker/bind-mounts.yml`. See [HTTPS and reverse proxy](./https-and-reverse-proxy.md#behind-a-reverse-proxy-on-this-machine).
:::

## Move an existing install

These steps copy the data from the Docker volumes into the folder. The volumes stay as they are, so you can go back.

The server is offline during the copy. The copy needs as much free space as the volumes use. `docker system df -v` shows the size of each volume.

::: danger Never use `docker compose down -v`
The option `-v` deletes the volumes, and with them all photos and videos. The steps below use `docker compose down` without `-v`.
:::

1. Go to the folder with `docker-compose.yml`. Set two shell variables: the project name, and the new data folder.
   ```sh [Set the variables]
   PROJECT=$(docker compose config | sed -n 's/^name: //p')
   DATA_LOCATION=/srv/data/kinlore
   echo "$PROJECT"
   ls -A "$DATA_LOCATION"
   ```
   `echo` prints the project name, for example `kinlore-server`. `ls` must print nothing, or `No such file or directory`. If it lists files, pick an empty folder.
2. Stop the server:
   ```sh [Stop the server]
   docker compose down
   ```
3. Copy each volume into its subfolder. A small throwaway container does the copy. `cp -a` keeps the owner and the rights of each file.
   ```sh [Copy the volumes]
   for pair in postgres_data:postgres seaweedfs_master:seaweedfs/master \
       seaweedfs_volume:seaweedfs/volume seaweedfs_filer:seaweedfs/filer \
       redis_data:redis api_storage:api/storage caddy_data:caddy/data caddy_config:caddy/config; do
     vol="${PROJECT}_${pair%%:*}"; dir="$DATA_LOCATION/${pair#*:}"
     docker volume inspect "$vol" >/dev/null || { echo "Missing volume: $vol"; continue; }
     docker run --rm -v "$vol:/from:ro" -v "$dir:/to" alpine cp -a /from/. /to/ && echo "Copied $vol to $dir"
   done
   ```
   Each volume prints one `Copied` line. If a line says `Missing volume`, check the project name of step 1.
4. Turn the file on in `.env`. If you already use other files in `COMPOSE_FILE`, keep them and add the new one at the end.
   ```diff [.env]
   +COMPOSE_FILE=docker-compose.yml:deploy/docker/bind-mounts.yml
   +DATA_LOCATION=/srv/data/kinlore
   ```
5. Start the server:
   ```sh [Start the server]
   docker compose up -d
   ```
6. Check that the server uses the folder, and that your data is there:
   ```sh [Check the move]
   docker inspect kinlore-postgres --format '{{range .Mounts}}{{.Type}} {{.Source}}{{end}}'
   sudo du -sh "$DATA_LOCATION"/*
   docker compose exec api curl -fsS http://localhost:8080/api/health
   ```
   The first command prints `bind /srv/data/kinlore/postgres`. Then open the app and check that your moments and their photos are there.

::: details Remove the old volumes
Wait until you have used the server for some days and made a backup of the new folder. Then the old volumes only take space. This command deletes them for good:

```sh [Delete the old volumes]
for v in postgres_data seaweedfs_master seaweedfs_volume seaweedfs_filer redis_data api_storage caddy_data caddy_config; do
  docker volume rm "${PROJECT}_$v"
done
```

Set `PROJECT` again first, as in step 1, if you use a new shell.
:::

## Back up the folder

All data of the server is now in one folder. Stop the server, copy the folder with `cp -a`, and start the server again. Save `.env` with the copy, because it holds `COMPOSE_FILE` and `DATA_LOCATION`. The exact commands, and how to restore, are in [Backup and restore](./backup-and-restore.md).

## When it does not work

- **A log shows `permission denied` for a folder under `DATA_LOCATION`.** The file system does not keep owners, or a copy did not use `cp -a`. See the warning in [The folder layout](#the-folder-layout).
- **The server starts empty after the move.** `DATA_LOCATION` in `.env` and the folder of the copy differ. Compare them, then run `docker compose down` and `docker compose up -d`.
- **`docker run` says `includes invalid characters for a local volume name`.** `DATA_LOCATION` is not a full path. Use a path that starts with `/`.

More problems and fixes are in [Troubleshooting](./troubleshooting.md).

## Next steps

- [Backup and restore](./backup-and-restore.md)
- [HTTPS and reverse proxy](./https-and-reverse-proxy.md)
- [Update the server](./updating.md)
