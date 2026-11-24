#!/bin/sh
# Kinlore backup for a host install with SQLite and local media (docs/BARE_METAL.md, "Backups").
# Usage: sudo kinlore-backup.sh /path/to/backup/folder
# Keeps a consistent database copy, the media folder and the settings file.
set -eu
umask 077   # the database copy and media hold private family data
DEST="${1:?backup folder}"
APP=/srv/kinlore/kinlore-api
DATA=/var/lib/kinlore
# An empty source with a full backup means the media disk is not mounted; rsync --delete would empty the backup.
if [ -z "$(ls -A "$DATA/media" 2>/dev/null)" ] && [ -n "$(ls -A "$DEST/media" 2>/dev/null)" ]; then
  echo "Error: $DATA/media is empty but $DEST/media is not. Is the media disk mounted?" >&2
  exit 1
fi
mkdir -p "$DEST/media"
# .backup gives a consistent copy even while the server runs
sqlite3 "$DATA/database.sqlite" ".backup '$DEST/database.sqlite'"
rsync -a --delete "$DATA/media/" "$DEST/media/"
install -m 600 "$APP/.env" "$DEST/env"   # holds APP_KEY
echo "Backup written to $DEST at $(date -Is)"
