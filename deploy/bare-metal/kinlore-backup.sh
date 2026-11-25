#!/bin/sh
# Kinlore backup for a host install with SQLite and local media (docs/self-hosting/backup-and-restore.md).
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
OLD="$DEST/old/$(date +%F)"   # what this run deletes or overwrites goes here, kept 30 days
mkdir -p "$DEST/media" "$OLD"
# .backup gives a consistent copy even while the server runs (also in WAL mode).
# Write to a temp file first: a failed run keeps the last good copy.
sqlite3 "$DATA/database.sqlite" ".backup '$DEST/database.sqlite.tmp'"
# Copy the old one aside, then replace it in one step: the main path always holds a full copy
[ -f "$DEST/database.sqlite" ] && cp -p "$DEST/database.sqlite" "$OLD/database.sqlite"
mv "$DEST/database.sqlite.tmp" "$DEST/database.sqlite"
rsync -a --delete --backup --backup-dir="$OLD/media" "$DATA/media/" "$DEST/media/"
[ -f "$DEST/env" ] && mv "$DEST/env" "$OLD/env"
install -m 600 "$APP/.env" "$DEST/env"   # holds APP_KEY
find "$DEST/old" -mindepth 1 -maxdepth 1 -type d -mtime +30 -exec rm -rf {} +
rmdir "$OLD" 2>/dev/null || true   # nothing changed today
echo "Backup written to $DEST at $(date -Is)"
