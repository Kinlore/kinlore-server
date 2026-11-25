# Offline use and uploads

This page explains what the app can do without a connection to the server, how the app syncs with the server, and how the upload queue sends photos and videos.

## The copy on the phone

The app keeps a copy of the family's data on the phone. Each server has its own copy.
The timeline reads from this copy. So you can open the app and see the timeline when the phone is offline.

A **sync** is the exchange of changes between the phone and the server:

1. The app sends the changes that you made on this phone.
2. The app gets the changes that other members made.

## When the app syncs

The app syncs at these times:

- When the app starts.
- After you sign in.
- When you switch to another server.
- When you pull the timeline down and release it.
- After you save a child, a story or a growth record.

The app does not sync by itself when the network comes back. When you are online again, pull the timeline down to sync.

## What works offline

| Task | Offline |
| --- | --- |
| See the timeline | Yes, from the copy on the phone. |
| Add a story or a growth record | Yes. The app sends it at the next sync. |
| Add, edit or delete a child | Yes. The app sends the change at the next sync. |
| Change the date or the description of a moment | Yes. The app sends the change at the next sync. |
| Delete a moment | Yes. The app sends the deletion at the next sync. |
| Pick photos and videos for upload | Yes. The upload queue waits for the network. |
| Add a milestone | No. |
| Like or comment | No. |
| Look-back cards | No. The cards do not show. |
| Members, invitations, export, family name | No. |
| Sign in or join with an invitation | No. |

## The upload queue

The **upload queue** is the list of photos and videos that wait to go to the server. When you tap **Upload** in the photo picker, the app adds each item to the queue.

- The queue sends one item at a time.
- The queue sends the original file.
- On iOS, an upload continues while the app is in the background.
- When the app starts again, the queue continues with the items that are not sent yet. It starts after the first sync.
- Each server has its own queue. When you switch to another server, the items of the first server wait. They continue when you switch back.

### The upload bar

When the queue has work, a bar shows at the bottom of the timeline:

- "Uploading 3/10..." while an item goes to the server.
- "4 pending" while items wait.
- "2 uploads failed. Tap to see and retry." when items failed.

Tap the bar to open the **Upload Queue** screen.

### The Upload Queue screen

Each item shows its status:

- **Pending**: the item waits.
- **Uploading...**: the item goes to the server now.
- **Uploaded**: the server has the item. The item leaves the list after a few seconds.
- **Failed**: the queue stopped trying. See [Failed uploads](#failed-uploads).

The buttons at the top:

- **Retry Failed**: try all failed items again.
- **Clear Failed**: remove all failed items from the queue. The photos and videos stay on the phone. You can pick them again later.
- **Clear Done**: remove the uploaded items from the list.

When the queue is empty, the screen shows "All Caught Up!".

## Retries

The queue tries again by itself in these cases:

- **The phone is offline.** This does not count as a try. The item stays **Pending**. The queue tries again every 60 seconds until the network is back.
- **The upload stopped, or the server had a problem.** The queue waits 10 seconds and tries again. After three tries, the item is **Failed**.
- **Your sign-in ended.** The queue stops. Sign in again. The queue then continues.

The queue does not send an item twice. Before an upload, it asks the server which items it already has for the child. An item that the server has gets the status **Uploaded**.

## Failed uploads

**Failed** means that the queue stopped trying to send this item. The item is still on the phone. It is not on the server.

An item fails at once when the server refuses it. For example:

- The file is too large or of a type that the server does not accept.
- You may no longer add photos and moments.
- The child was deleted.

An item also fails after three tries with a server problem.

### Retry a failed upload

1. Tap the upload bar at the bottom of the timeline. With failed items, it shows for example "2 uploads failed. Tap to see and retry."
2. On the **Upload Queue** screen, tap the retry button next to the item ("Retry upload"). Or tap **Retry Failed** to retry all failed items.
3. Wait until the item shows **Uploaded**.

### Check that it worked

- The upload bar is gone.
- The photo or the video is on the timeline of the child.

If the item fails again, the server refuses it. See [Troubleshooting](troubleshooting.md#uploads). You can remove it with **Clear Failed**.

## Unsent changes

Changes that did not reach the server yet are only on this phone.
These actions would delete them, so the app checks first:

- **Reset & Sync** in **Settings** sends the changes first. It stops if some changes cannot be sent.
- **Reset this phone** in **Settings** warns you with the number of unsent changes.
- **Remove Server** refuses to remove the active server's data while it has unsent changes.

See [Settings](settings.md) and [Multiple servers](multiple-servers.md).
