# Settings

This page explains every part of the **Settings** tab: the family card, the family screens, your account, your signed-in devices, the families on this phone, storage, uploads, **Reset and sync** and **Reset this phone**.

To open the screen, tap the **Settings** tab at the bottom of the app.

In Kinlore, one server is one family. The **Settings** tab always shows the family that the app shows now: the **active family**.

## The family card

The card at the top shows:

- The family name, for example "Anna Example Family". Until the first sync, the app shows the server address in its place.
- The server address, for example `photos.example.com`.
- Your name and your role in this family, for example "You: Ben, Family".

When the phone has more than one family, the card also has the button **Switch family**. It opens "Choose a family". See [Several families](multiple-servers.md#switch-to-another-family).

## The family section

The title of this section has the family name, for example "Smith family". Each row works on the active family only.

- **Children**: add, edit and delete children. See [Children](children.md).
- **Members**: only for the admin. Manage members, their roles and their permissions. See [Family and permissions](family-and-permissions.md).
- **Invite someone**: only for the admin. Make an invitation link. See [Invite your family](../tutorials/invite-your-family.md).
- **Export**: only for the admin. Make a ZIP file with all photos, videos and moments. See [Export](export.md).

The screens that open from these rows show the family name in their header. So you always know which family you change.

## Your account

- The first row shows your name, your email and your role in this family. You cannot tap this row.
- **Signed-in devices**: see and sign out the phones and tablets that use your account in this family. See [Signed-in devices](#signed-in-devices).
- **Sign out**: sign out of this family on this phone.

### Sign out

1. Tap **Sign out**.
2. The app asks "Sign out of" with the family name. Tap **Sign out**.

The app removes only the sign-in of this family. The family stays on the phone, with its data. Your other families stay signed in.

To sign in again, type your email and your password on the sign-in screen.

### Signed-in devices

The **Signed-in devices** screen shows every sign-in of your account in this family.

- **This phone**: the phone that you hold, with the date of the sign-in.
- **Other devices**: the other phones and tablets. Each one shows as "Another device", with the date of the sign-in and the time of the last use.

When no other device is signed in, the screen shows "Only this phone". Pull the screen down to load the list again.

To sign out one other device:

1. Tap the sign-out button next to the device.
2. The app asks "Sign out this device?". Tap **Sign out device**.

That device needs the email and the password to sign in again.

To sign out all devices:

1. Tap **Sign out everywhere**.
2. The app asks "Sign out everywhere?". Tap **Sign out everywhere**.

Every device signs out of this family, this phone too. The app then shows the sign-in screen.

## Families on this phone

- **Add another family**: connect to a second Kinlore server. See [Several families](multiple-servers.md#add-a-family).
- **Families**: shows only when the phone has more than one family. It opens the list of families. See [Several families](multiple-servers.md).
- **Server details**: shows only when the phone has one family. It opens "Family details". See [Several families](multiple-servers.md#see-the-family-details).

## Storage

**Server storage** shows how much space the family's photos and videos use on the server, and how much free space the server disk has. The bar shows the used part of the server disk, for example "12% used".

The bar changes color above 75% and turns red above 90%. Tell the server owner when the disk is almost full.

## Data

### Uploads

The **Uploads** row shows the progress of the upload queue: for example "10 / 400", "Waiting for Wi-Fi", "3 failed" or "Up to date". It changes with every uploaded file. Tap it to open the **Uploads** screen. See [Offline use and uploads](./offline-and-uploads.md#the-upload-queue).

### Upload over mobile data

When this switch is off, photos and videos upload only on Wi-Fi. On mobile data, they wait. The switch is off at first. Turn it on to upload on any connection. See [Mobile data](./offline-and-uploads.md#mobile-data).

### Reset and sync

**Reset and sync** deletes the copy of the data on this phone for the active family, and gets everything again from the server. Use it when the timeline shows wrong data.

1. Tap **Reset and sync**.
2. Read the message "Reset local data?". Tap **Reset and sync**.
3. Wait. The app shows "Local data has been reset and synced from the cloud." when it is done.

Before the reset, the app sends the unsent changes of this phone. If it cannot send them all, it stops and deletes nothing. The data on the server does not change.

### Reset this phone

**Reset this phone** removes all Kinlore data from this phone and signs you out. Every member may use it. It does not delete anything on the server.

The reset deletes these things on the phone:

- All families on this phone.
- All sign-ins.
- The copy of the data of every family.
- The upload queue, and the settings of the app, for example the active child and the default family.

After the reset, the app starts at "Welcome to Kinlore". To get everything back, add the family again and sign in.

1. Tap **Reset this phone**.
2. Read the message "Reset this phone?". Tap **Reset phone**.
3. If the active family has unsent changes, the app shows "Unsent changes will be lost" with the number of changes. To keep them, tap **Cancel**, stay online and let them sync. To delete them, tap the button "Reset and lose" with the number.

The app can count the unsent changes of the active family only. Stories, changes or uploads that have not reached your other families yet are lost.

### Check that it worked

The app shows "Welcome to Kinlore".

## App version

The bottom of the screen shows the version of the app, for example "Kinlore 1.2.0".

## Problems

- **"Not reset"**: the app could not send all unsent changes, or it could not reach the server. So it deleted nothing. Wait until the uploads finish, or retry or remove failed uploads. Then try again. See [Offline use and uploads](offline-and-uploads.md).
- **"Could not reset this phone completely. Please try again."**: tap **Reset this phone** again.
- **"Could not load your signed-in devices."**: the app could not reach the server. Check the connection, then tap **Try again**.
