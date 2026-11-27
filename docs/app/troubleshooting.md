# Troubleshooting

This page lists the messages that the app shows when something goes wrong, and what to do.
For problems on the server itself, see [Server troubleshooting](../self-hosting/troubleshooting.md). For general questions, see the [FAQ](../faq.md).

## Connect and sign in

| Message | What to do |
| --- | --- |
| "Enter the server address." | Type the server address in **Server address**. |
| "Enter a domain or IP, with an optional port, ..." | Type only a host name or an IP address, with an optional port, for example `photos.example.com` or `192.168.1.20:28461`. |
| "Could not connect to the server. Check the address and your internet connection, then try again." | Check the address and the port. Check that the phone has a network connection. For a server at home, check that the phone uses the home network. Ask the server owner if the server runs. |
| "This address answers, but not like a Kinlore server. ..." | The address is not a Kinlore server. Check the address with the server owner. |
| "This server is not set up yet. ..." | The server has no admin yet. The server owner creates the admin in a web browser. See [Connect to a server](connect.md#when-the-server-is-not-set-up-yet). |
| "Enter your email." or "Enter your password." | Type your email and your password. |
| "The provided credentials are incorrect." or "Sign-in failed. Check your email and password, then try again." | The email or the password is wrong. Type them again. If you forgot your password, ask the admin to set a new one. See [Family and permissions](family-and-permissions.md#set-a-new-password-for-a-member). |
| "Could not reach the server. Check your internet connection, then try again." | The server did not answer. Check the connection of the phone, then try again. |
| "Could not load your family" | The first sync failed. Check the connection to the server, then tap **Try again**. |

The server allows only a few sign-in tries per minute. After many wrong tries, wait one minute.

When the app shows the sign-in screen without a reason, your sign-in ended. For example, the admin set a new password for you, or removed you, or you tapped **Sign out everywhere** on another device. Sign in again, or ask the admin.

## Invitations

| Message | What to do |
| --- | --- |
| "This invitation is not valid. It is used, expired or unknown. Ask for a new invitation." | Ask the admin for a new invitation. |
| "This invitation has no server address. Paste the full invitation link." | Paste the full link. Or type the server address on the first screen, then tap **I have an invitation** and paste the code. |
| "This is not an invitation. Paste the invitation link or the token you received." | Paste the invitation link or the code exactly as you received it. |
| "You did not join this server. Paste an invitation link for a server you trust." | You closed "Join ...?" without **Join**. Paste a link again, and tap **Join** if you trust the server. |
| "The passwords do not match." | Type the same password in **Password** and **Repeat the password**. |
| "Use at least 8 characters." | Type a longer password. |
| "The email has already been taken." | An account with this email exists. Tap **Already have an account? Sign in**. |
| "A valid invitation is required to join this instance." | The invitation is not valid, or it is for another email address. Ask the admin for a new invitation. |
| "Invitation for another server" | You are signed in, and the link is for another family. In **Settings**, tap **Add another family** and add the server of the invitation. On its sign-in screen, tap **I have an invitation** and paste the link. See [Several families](multiple-servers.md#add-a-family). |
| "The server has no public address" (on the admin's phone, after **Create invitation**) | The links use the address of the admin's phone. People outside your home network may not open them. The server owner sets `APP_URL`. See [APP_URL placeholder](../self-hosting/configuration.md#app-url-placeholder). |

## Children

| Message | What to do |
| --- | --- |
| "Enter a name. Only an expected baby can be saved without one." | Type a name. For a baby that is not born yet, you can leave it empty after you turn on **Expecting a baby?**. |
| "Pick the birth date." or "Pick the due date." | Tap the date field and pick a date. |
| "The birth date must be today or earlier." | Pick today or an earlier date. |
| "No child yet" | You are a family member, and the family has no child. Ask the admin to add one, then tap **Check again**. |
| "Could not reach the server. Check your connection, then try again." on "No child yet" | Check the connection, then tap **Check again**. |
| "Could not save the child. Try again." or "Could not create the profile. Try again." | Try again. If the message comes back, you may not edit this child. |
| "Could not delete" with the name of the child | Check the connection and try again. You may delete only a child that you may edit. |

## Moments

| Message | What to do |
| --- | --- |
| "Allow access to your photos" | The app may not read your photos. Tap **Open Settings** and allow access to photos and videos. See [Install the app](install.md#permissions). |
| "Could not load your albums." or "Could not load the photos of this album." | Close the add screen and open it again. |
| "Could not add the photos to the upload queue. Try again." | Try again. If the message comes back, close the app and open it again. |
| "Could not save the milestone. Check the connection and try again." | A milestone needs the server. Check the connection and try again. |
| "No milestones yet" | The app has no list of milestones yet. Connect to the internet and open the **Milestone** tab again. |
| "Enter a number from ... to ..." under a growth field | Type a value in its range: height 1 to 250 cm, weight 0.1 to 200 kg, head 1 to 100 cm. |
| "Could not save the story. Try again." or "Could not save the measurement" | Try again. |
| "Could not post your comment. Try again." | Check the connection. If it is good, you may not comment. Ask the admin. |
| "Could not delete the comment. Try again." | Check the connection. You may delete only your own comments, unless you are the admin. |
| "Could not delete the moment." or "Could not save the change." | The server refused the change. You may only change your own moments, unless you are the admin or a partner. |
| "Not saved." under the caption | Tap the message to save again. |
| "Not saved on the server:" and a reason | The server did not accept a change from this phone. See below. |
| "This moment is still syncing to this phone" | You tapped a look-back moment that is not on the phone yet. Wait, and try again. |
| "Processing" on a video, or "Processing your video…" | The server still prepares the video. Wait and try again later. |
| "Could not process" or "This video could not be processed." | The server could not prepare the video. Tell the server owner. |
| "Could not play this video." | Check the connection. Close the video and open it again. |

### "Not saved on the server"

The details of a moment show this message when the server refused a change from this phone. The reason is one of these words:

- **forbidden**: you may not make this change. For example, you may not add moments, or you may not edit a moment of another member.
- **conflict**: the moment changed on the server since your last sync, for example on another phone. The app gets the server copy at the next sync.
- **invalid**: the data is not valid for the server.
- **deleted**: the moment or the child no longer exists on the server.

Pull the timeline down to sync. If the message stays, delete the moment on this phone and add it again, or ask the admin.

## Uploads

| Message or status | What to do |
| --- | --- |
| "2 uploads failed. Tap to see and retry." (with the number of failed items) | Tap the bar. On the **Uploads** screen, tap **Retry failed**. See [Offline use and uploads](offline-and-uploads.md#failed-uploads). |
| An item stays **Waiting** | The phone is offline, or the server does not answer. The queue tries again every 60 seconds. Check the connection. |
| An item is **Upload failed** again after a retry | The server refuses the file. See the list below. |

The server refuses a file in these cases:

- The file is larger than 500 MB.
- The file type is not a photo or video type that the server accepts. The server accepts JPEG, PNG, GIF, WebP, HEIC and HEIF photos, and MP4, MOV, AVI, WebM, MKV and M4V videos.
- You no longer have the permission **Add photos and moments**.
- The child was deleted.

You can remove failed items with **Remove failed**. The photos and videos stay on the phone.

## Families

| Message | What to do |
| --- | --- |
| "Server already configured" | The family is already on this phone. Find it on the **Families** screen. |
| "Test the connection first." | Tap **Test connection** before **Add family**. |
| "Server not reachable" | The server does not answer. Check the address, the port and the network. |
| "Server address not found" | The host name is wrong, or the phone cannot find it. Check the spelling. |
| "Could not open" with the name of a family | The app could not switch to the family. Check the connection and try again. |
| "Cannot remove this family" | You cannot remove the active family. Switch to another family first. |
| "Cannot remove this server yet" | The family has unsent changes on this phone. Switch to it and let them sync first. |
| "Name not saved. Check the connection and try again." | The server did not save the family name. Check the connection and try again. |

## Members, devices and export

| Message | What to do |
| --- | --- |
| "Could not load members." | Check the connection. Only the admin can open **Members**. |
| "Could not create the invitation." | Check the connection and try again. |
| "Could not revoke the invitation." | Check the connection and try again. |
| "Could not save the changes." | Check the connection and try again. |
| "Could not transfer the admin role." | Check the connection. Pull the **Members** list down, then try again. |
| "You cannot change your own role. Make another member admin instead." | Use **Make admin** on another member. |
| "Could not load your signed-in devices." | Check the connection, then tap **Try again**. |
| "Could not sign out that device." or "Could not sign out everywhere." | Check the connection and try again. |
| "An export is already running. Pull down to refresh its status." | Wait until the running export is ready. |
| "Could not open the download link." | Pull the **Export** list down to get a new link, then try again. |

## Settings

| Message | What to do |
| --- | --- |
| "Not reset" | The app deleted nothing, because some changes could not reach the server. Wait until the uploads finish, or retry or remove failed uploads. Then try again. |
| "Unsent changes will be lost" | Tap **Cancel** to keep the changes, and let them sync. Reset only if you accept that they are lost. |
| "Could not reset and sync. Please try again." | Check the connection and try again. |

## Still a problem?

- Pull the timeline down to start a sync.
- Close the app and open it again.
- In **Settings**, use **Reset and sync**. See [Settings](settings.md#reset-and-sync).
- Ask the server owner to check the server. See [Server troubleshooting](../self-hosting/troubleshooting.md).
