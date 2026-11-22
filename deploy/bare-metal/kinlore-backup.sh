#!/bin/sh
# Kinlore backup for a host install with SQLite and local media (docs/BARE_METAL.md, "Backups").
# Usage: sudo kinlore-backup.sh /path/to/backup/folder
# Keeps a consistent database copy, the media folder and the settings file.
set -eu
DEST="${1:?backup folder}"
APP=/srv/kinlore/kinlore-api
DATA=/var/lib/kinlore
mkdir -p "$DEST/media"
# .backup gives a consistent copy even while the server runs
sqlite3 "$DATA/database.sqlite" ".backup '$DEST/database.sqlite'"
rsync -a --delete "$DATA/media/" "$DEST/media/"
install -m 600 "$APP/.env" "$DEST/env"   # holds APP_KEY
echo "Backup written to $DEST at $(date -Is)"
