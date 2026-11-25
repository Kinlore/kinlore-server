# Troubleshooting

This page lists the messages that the app shows when something goes wrong, and what to do.
For problems on the server itself, see [Server troubleshooting](../self-hosting/troubleshooting.md). For general questions, see the [FAQ](../faq.md).

## Connect and sign in

| Message | What to do |
| --- | --- |
| "Please enter a server URL" | Type the server address in **Server URL**. |
| "Invalid server address" | Type only a host name or an IP address, with an optional port, for example `photos.example.com` or `192.168.1.20:8088`. |
| "Connection Failed" with "Could not connect to the server. Please check the URL and try again." | Check the address and the port. Check that the phone has a network connection. For a server at home, check that the phone uses the home network. Ask the server owner if the server runs. |
| "Server is not responding correctly" | The address answers, but it is not a Kinlore server. Check the address with the server owner. |
| "This server is not set up yet. ..." | The server has no admin yet. The server owner creates the admin in a web browser. See [Connect to a server](connect.md#when-the-server-is-not-set-up-yet). |
| "Please fill in all fields" | Type your email and your password. |
| "The provided credentials are incorrect." | The email or the password is wrong. Type them again. If you forgot your password, ask the admin to set a new one. See [Family and permissions](family-and-permissions.md#set-a-new-password-for-a-member). |
| "Authentication failed. Please try again." | Try again. If the message comes back, check the connection. |
| "Could not load your family" | The first sync failed. Check the connection to the server, then tap **Try again**. |

The server allows only a few sign-in tries per minute. After many wrong tries, wait one minute.

When the app shows the sign-in screen without a reason, your sign-in ended. For example, the admin set a new password for you, or removed you. Sign in again, or ask the admin.

## Invitations

| Message | What to do |
| --- | --- |
| "This invitation is not valid. It is used, expired or unknown. Ask for a new invitation." | Ask the admin for a new invitation. |
| "This invitation has no server address. Paste the full invitation link." | Paste the full link. Or type the server address on the first screen, then tap **I have an invitation** and paste the code. |
| "Invalid invitation" | Paste the invitation link or the code exactly as you received it. |
| "You did not join this server. Paste an invitation link for a server you trust." | You tapped **Cancel** in "Join ...?". Paste a link again, and tap **Join** if you trust the server. |
| "Passwords do not match" | Type the same password in **Password** and **Confirm Password**. |
| "Password must be at least 8 characters" | Type a longer password. |
| "The email has already been taken." | An account with this email exists. Tap **Already have an account? Sign in**. |
| "A valid invitation is required to join this instance." | The invitation is not valid, or it is for another email address. Ask the admin for a new invitation. |
| "Invitation for another server" | You are signed in, and the link is for another server. Open **Settings**, tap your server under **Server**, add the other server, then switch to it and tap **I have an invitation**. See [Multiple servers](multiple-servers.md). |

## Children

| Message | What to do |
| --- | --- |
| "Cannot Delete" with "You must have at least one child profile." | Add another child before you delete this one. |
| "Please enter a name" | Type a name. For a baby that is not born yet, you can leave it empty after you turn on **Expecting a baby?**. |
| "Please select a birth date" or "Please select a due date" | Tap the date field and pick a date. |
| "Birth date cannot be in the future" | Pick today or an earlier date. |
| "No child yet" | You are a family member, and the family has no child. Ask the admin to add one, then tap **Check again**. |
| "Could not check" | The app could not reach the server. Check the connection, then tap **Check again**. |
| "Failed to save child. Please try again." | Try again. If the message comes back, you may not edit this child. |

## Moments

| Message | What to do |
| --- | --- |
| "Permission Required" | The app may not read your photos. Allow access to photos and videos in the Settings app of your phone. See [Install the app](install.md#permissions). |
| "No Photos Selected" | Select at least one photo or video before you tap **Upload**. |
| "Failed to create milestone. Please try again." | A milestone needs the server. Check the connection and try again. |
| "No milestones available" | The app has no list of milestones yet. Pull the timeline down to sync, then try again. |
| "Empty Story" | Write some text first. |
| "Missing measurement" | Type at least one value in its range: height 1 to 250 cm, weight 0.1 to 200 kg, head 1 to 100 cm. |
| "Failed to post comment" | Check the connection. If it is good, you may not comment. Ask the admin. |
| "Failed to delete the moment" or "Failed to save the change" | The server refused the change. You may only change your own moments, unless you are the admin or a partner. |
| "Not saved on the server:" and a reason | The server did not accept a change from this phone. See below. |
| "Processing…" on a video | The server still prepares the video. Wait and try again later. |
| "Could not process video" | The server could not prepare the video. Tell the server owner. |
| "Could not play video" | Check the connection. Close the video and open it again. |

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
| "2 uploads failed. Tap to see and retry." (with the number of failed items) | Tap the bar. On the **Upload Queue** screen, tap **Retry Failed**. See [Offline use and uploads](offline-and-uploads.md#failed-uploads). |
| An item stays **Pending** | The phone is offline, or the server does not answer. The queue tries again every 60 seconds. Check the connection. |
| An item is **Failed** again after a retry | The server refuses the file. See the list below. |

The server refuses a file in these cases:

- The file is larger than 500 MB.
- The file type is not a photo or video type that the server accepts. The server accepts JPEG, PNG, GIF, WebP, HEIC and HEIF photos, and MP4, MOV, AVI, WebM, MKV and M4V videos.
- You no longer have the permission **Add photos and moments**.
- The child was deleted.

You can remove failed items with **Clear Failed**. The photos and videos stay on the phone.

## Servers

| Message | What to do |
| --- | --- |
| "Server already configured" | The app already knows this server. Find it on the **Servers** screen. |
| "Please test the connection first" | Tap **Test Connection** before **Add Server**. |
| "Server not reachable" | The server does not answer. Check the address, the port and the network. |
| "Server address not found" | The host name is wrong, or the phone cannot find it. Check the spelling. |
| "Cannot Remove" | You cannot remove the active server. Switch to another server first. If the server has unsent changes, let them sync first. |
| "Name not saved" | The server did not save the family name. Check the connection and try again. |

## Members and export

| Message | What to do |
| --- | --- |
| "Could not load members." | Check the connection. Only the admin can open **Members**. |
| "Could not create the invitation." | Check the connection and try again. |
| "Could not save the changes." | Check the connection and try again. |
| "Could not transfer the admin role." | Check the connection. Pull the **Members** list down, then try again. |
| "You cannot change your own role. Make another member admin instead." | Use **Make admin** on another member. |
| "An export is already running. Pull down to refresh its status." | Wait until the running export is ready. |
| "Could not open the download link." | Pull the **Export** list down to get a new link, then try again. |

## Settings

| Message | What to do |
| --- | --- |
| "Not reset" | The app deleted nothing, because some changes could not reach the server. Wait until the uploads finish, or retry or remove failed uploads. Then try again. |
| "Unsent changes will be lost" | Tap **Cancel** to keep the changes, and let them sync. Reset only if you accept that they are lost. |
| "Failed to reset and sync. Please try again." | Check the connection and try again. |

## Still a problem?

- Pull the timeline down to start a sync.
- Close the app and open it again.
- In **Settings**, use **Reset & Sync**. See [Settings](settings.md#reset-sync).
- Ask the server owner to check the server. See [Server troubleshooting](../self-hosting/troubleshooting.md).
