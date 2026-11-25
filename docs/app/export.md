# Export

This page tells the admin how to make a full export of the family's data, and what the export file holds.

An **export** is one ZIP file with all original photos and videos and all moments of the family. Only the admin can make and download an export.

## Start an export

1. Open the **Settings** tab.
2. Under **Family**, tap **Export**.
3. Tap **Start Export**.
4. The new export shows in the list with the status **Waiting**, then **Building**.
5. Pull the list down to see the newest status.
6. When the status is **Ready**, the list shows the size of the file.

The server builds the file. You can close the app while it works. A large library takes time.

## Download the export

1. On the **Export** screen, find an export with the status **Ready**.
2. Tap the download button next to it.
3. The phone opens the download in the web browser.

A download link is valid for 24 hours. When it is no longer valid, pull the list down or open the **Export** screen again. The app then gets a new link.

### Check that it worked

- The ZIP file is in the downloads of your phone or your browser.
- Open the file. It has one folder for each child, and the file `kinlore-export.json`.

## What the ZIP file holds

- One folder for each child, with the name of the child.
- In each child folder, one folder for each year.
- In each year folder, the original photo and video files. Each file name starts with the date, then the kind of moment, for example `2026-05-14_photo_...jpg`.
- In each child folder, the file `memories.json`. It holds every moment of the child: photos, videos, stories, milestones and growth records, with their dates, texts and comments.
- At the top, the file `kinlore-export.json`. It lists the children, with their birth date or due date, and the time of the export.

A JSON file is a text file with data in a fixed form. A computer program can read it. You can also open it with a text editor.

## Keep your exports

- The server keeps the 3 newest exports. It deletes older exports.
- Only one export can run at a time.
- An export is not a full backup of the server. For a backup, see [Backup and restore](../self-hosting/backup-and-restore.md).

## Problems

- **"An export is already running. Pull down to refresh its status."**: wait until the running export is ready.
- **The status is "Failed".** The list shows the reason under the export. Tell the server owner. One possible reason is too little free disk space on the server. See [Server troubleshooting](../self-hosting/troubleshooting.md).
- **"Could not open the download link."**: the phone could not open the link. Pull the list down to get a new link, then try again.
- **"Could not load exports."**: the app could not reach the server. Check the connection.
