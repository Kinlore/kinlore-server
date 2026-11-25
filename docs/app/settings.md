# Settings

This page explains every row of the **Settings** tab, including sign-out, **Reset & Sync** and **Reset this phone**.

To open the screen, tap the **Settings** tab at the bottom of the app.

## Server

The row shows the name of the active server, and the number of servers that the app knows, for example "2 servers configured".

Tap the row to open the server list. See [Multiple servers](multiple-servers.md).

## Account

- The first row shows your name, your email and your role, for example "Role: Family". You cannot tap this row.
- **Sign Out** signs you out of the active server.

### Sign out

1. Tap **Sign Out**.
2. The app asks "Are you sure you want to sign out?". Tap **Sign Out**.

The app removes only the sign-in of the active server. The server stays in the list. The data of the server stays on the phone. Your other servers keep their sign-ins.

To sign in again, type your email and your password on the sign-in screen.

## Family

- **Children**: add, edit and delete children. See [Children](children.md).
- **Members**: only for the admin. Invite people, and manage members and their permissions. See [Family and permissions](family-and-permissions.md).
- **Export**: only for the admin. Make a ZIP file with all photos, videos and moments. See [Export](export.md).

## Storage

**Server storage** shows how much space the family's photos and videos use on the server, and how much free space the server disk has. The bar shows the used part of the server disk, for example "12% used".

The bar changes color above 75% and turns red above 90%. Tell the server owner when the disk is almost full.

## Data

### Reset & Sync

**Reset & Sync** deletes the copy of the data on this phone for the active server, and gets everything again from the server. Use it when the timeline shows wrong data.

1. Tap **Reset & Sync**.
2. Read the message "Reset Local Data". Tap **Reset & Sync**.
3. Wait. The app shows "Success" when it is done.

Before the reset, the app sends the unsent changes of this phone. If it cannot send them all, it stops and deletes nothing. The data on the server does not change.

### Reset this phone

**Reset this phone** removes all Kinlore data from this phone and signs you out. Every member may use it. It does not delete anything on the server.

The reset deletes these things on the phone:

- All servers in the list.
- All sign-ins.
- The copy of the data of every server.
- The upload queue, and the settings of the app, for example the active child and the default server.

After the reset, the app starts at "Welcome to Kinlore". To get everything back, add the server and sign in again.

1. Tap **Reset this phone**.
2. Read the message "Reset this phone". Tap **Reset phone**.
3. If the active server has unsent changes, the app shows "Unsent changes will be lost" with the number of changes. To keep them, tap **Cancel**, stay online and let them sync. To delete them, tap the button "Reset and lose" with the number.

The app can count the unsent changes of the active server only. Stories, changes or uploads that have not reached your other servers yet are lost.

### Check that it worked

The app shows "Welcome to Kinlore".

## App version

The bottom of the screen shows the version of the app, for example "Kinlore 1.2.0".

## Problems

- **"Not reset"**: the app could not send all unsent changes, so it deleted nothing. Wait until the uploads finish, or retry or remove failed uploads. Then try again. See [Offline use and uploads](offline-and-uploads.md).
- **"Could not reset this phone completely. Please try again."**: tap **Reset this phone** again.
