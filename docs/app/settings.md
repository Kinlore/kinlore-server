# Settings

This page explains every part of the **Settings** tab and of the **Server** screen: the servers on this phone, your account, the family screens, your signed-in devices, the server name, storage, uploads, **Reset and sync**, **Remove from this phone** and **Reset this phone**.

To open the screen, tap the **Settings** tab at the bottom of the app.

In Kinlore, one server is one family. The name of a server is the family name that the server reports, for example "Smith family". The server that the app shows now is the server **in use**.

**Settings** has two groups:

- **Servers**: one row for each server on this phone, and **Add a server**.
- **This phone**: settings that are not about one server.

## Servers

Each row in **Servers** shows:

- The name of the server, for example "Anna Example Family". Until the first sync, the app shows the server address in its place.
- The server address, for example `photos.example.com`. Two servers can have the same name, so the address always shows.
- Your name and your role on that server, for example "Ben, Family", or "Not signed in".
- A status dot: green when the server answers, red when it does not. The server's own screen shows the status in words, for example **Online**.
- **In use** on the server that the app shows now. This server is always first.

When **Settings** opens, the app checks the status of every server again.

Tap a row to open the **Server** screen of that server. See [The Server screen](#the-server-screen).

Tap **Add a server** to connect to another Kinlore server. See [Several servers](multiple-servers.md#add-a-server).

## The Server screen

The header shows the name of the server, with its address under it.

### Status

The top of the screen shows the status of the server, for example **Online** or **Offline**. To check the connection again, tap the refresh button ("Test connection").

- On the server in use, the app shows **In use**.
- On another server, the app shows **Switch to this server** when you are signed in there, or **Sign in to this server** when you are not. See [Several servers](multiple-servers.md#switch-to-another-server).

For a server that is not in use, the app shows "Switch to this server to manage its children, members and account." The groups **Family**, **Signed-in devices** and **Sign out** show only on the server in use.

### Your account

This group shows only when you are signed in on this server.

- The first row shows your name, your email and your role on this server. You cannot tap this row.
- **Signed-in devices**: see and sign out the phones and tablets that use your account on this server. See [Signed-in devices](#signed-in-devices).
- **Sign out**: sign out of this server on this phone.

#### Sign out

1. Tap **Sign out**.
2. The app asks "Sign out of" with the server name. Tap **Sign out**.

Only this phone signs out of this server. The server and its data stay on this phone. Your other servers stay signed in.

To sign in again, type your email and your password on the sign-in screen.

#### Signed-in devices

The **Signed-in devices** screen shows every sign-in of your account on this server.

- **This phone**: the phone that you hold, with the date of the sign-in.
- **Other devices**: the other phones and tablets. Each one shows its device name, for example "iPhone", "iPad" or "Pixel 8", with the date of the sign-in and the time of the last use. A sign-in from an older app, or to an older server, shows as "Another device".

When no other device is signed in, the screen shows "Only this phone". Pull the screen down to load the list again.

To sign out one other device:

1. Tap the sign-out button next to the device.
2. The app asks "Sign out this device?". Tap **Sign out device**.

That device needs the email and the password to sign in again.

To sign out all devices:

1. Tap **Sign out everywhere**.
2. The app asks "Sign out everywhere?". Tap **Sign out everywhere**.

Every device signs out of this server, this phone too. The app then shows the sign-in screen.

### Family

This group shows only on the server in use. Each row works on that server only.

- **Children**: add, edit and delete children. The row shows the number of children. See [Children](children.md).
- **Members**: only for the admin. Manage members, their roles and their permissions, and invite people. See [Family and permissions](family-and-permissions.md).
- **Export**: only for the admin. Make a ZIP file with all photos, videos and moments. See [Export](export.md).

The screens that open from these rows show the server name in their header. So you always know which server you change.

### Server

- **Rename**: tap it to change the name. For the admin on the server in use, the new name is the family name for everyone ("Everyone on this server sees the new name"). For everyone else, the name is only on this phone ("Only on this phone"). See [Change the server name](multiple-servers.md#change-the-server-name).
- **Storage**: only on the server in use. See [Storage](#storage).
- **Reset and sync**: only on the server in use. See [Reset and sync](#reset-and-sync).

#### Storage

**Storage** shows how much space the family's photos and videos use on the server, and how much free space the server disk has. The bar shows the used part of the server disk, for example "12% used".

The bar changes color above 75% and turns red above 90%. Tell the server owner when the disk is almost full.

#### Reset and sync

**Reset and sync** deletes the copy of the data on this phone for the server in use, and gets everything again from the server. Use it when the timeline shows wrong data.

1. Tap **Reset and sync**.
2. Read the message "Reset local data?". Tap **Reset and sync**.
3. Wait. The app shows "Local data has been reset and synced from the cloud." when it is done.

Before the reset, the app sends the unsent changes of this phone. If it cannot send them all, it stops and deletes nothing. The data on the server does not change.

### Remove from this phone

**Remove from this phone** deletes this server and its data from this phone. Nothing on the server is deleted. You cannot remove the server in use. See [Remove a server from this phone](multiple-servers.md#remove-a-server-from-this-phone).

## This phone

### Uploads

The **Uploads** row shows the progress of the upload queue: for example "10 / 400", "Waiting for Wi-Fi", "3 failed" or "Up to date". It changes with every uploaded file. Tap it to open the **Uploads** screen. See [Offline use and uploads](./offline-and-uploads.md#the-upload-queue).

### Upload over mobile data

When this switch is off, photos and videos upload only on Wi-Fi. On mobile data, they wait. The switch is off at first. Turn it on to upload on any connection. See [Mobile data](./offline-and-uploads.md#mobile-data).

### Reset this phone

**Reset this phone** removes every server and sign-in from this phone and signs you out. Every member may use it. It does not delete anything on the server.

The reset deletes these things on the phone:

- All servers on this phone.
- All sign-ins.
- The copy of the data of every server.
- The upload queue, and the settings of the app, for example the active child.

After the reset, the app starts at "Welcome to Kinlore". To get everything back, add the server again and sign in.

1. Tap **Reset this phone**.
2. Read the message "Reset this phone?". Tap **Reset phone**.
3. If the server in use has unsent changes, the app shows "Unsent changes will be lost" with the number of changes. To keep them, tap **Cancel**, stay online and let them sync. To delete them, tap the button "Reset and lose" with the number.

The app can count the unsent changes of the server in use only. Stories, changes or uploads that have not reached your other servers yet are lost.

#### Check that it worked

The app shows "Welcome to Kinlore".

## App version

The bottom of the screen shows the version of the app, for example "Kinlore 1.2.0".

## Problems

- **"Not reset"**: the app could not send all unsent changes, or it could not reach the server. So it deleted nothing. Wait until the uploads finish, or retry or remove failed uploads. Then try again. See [Offline use and uploads](offline-and-uploads.md).
- **"Could not reset this phone completely. Please try again."**: tap **Reset this phone** again.
- **"Could not load your signed-in devices."**: the app could not reach the server. Check the connection, then tap **Try again**.
- **"Name not saved. Check the connection and try again."**: the app could not reach the server. Check the connection and try again.
