# Connect to a server

This page tells you how to connect the app to your family's server, sign in, and join a family with an invitation link.
It also explains the family name, and what to do when the server is not set up yet.

A **server** is the computer that keeps your family's photos and moments. In Kinlore, one server is one family. Each family has one **admin**: the person who manages the family.

## Before you start

- You know the address of the server, for example `photos.example.com` or `192.168.1.20:28461`. The server owner can tell you.
- For a first sign-in, you have an account. The admin creates the first account in a web browser. All other members join with an invitation link. See [Join with an invitation link](#join-with-an-invitation-link).

## Add the server and sign in

1. Open the app. The app shows "Welcome to Kinlore".
2. In **Server address**, type the address of the server. You can add a port, for example `photos.example.com:8443`.
3. Tap **Continue**.
4. The app shows "Welcome back" and the address of the server.
5. Type your **Email** and your **Password**.
6. Tap **Sign in**.

If you type no `https://` or `http://`, the app tries `https://` first. It tries `http://` only for an address on a home network.

To use a different server, tap **Change server** on the sign-in screen. When the phone has more than one family, the button is **Use another family**. It opens "Choose a family". See [Several families](multiple-servers.md#switch-to-another-family).

### What you see after the sign-in

- The app shows "Loading your family..." while it gets the family's data from the server.
- When the family has children, the app shows the **Timeline** tab.
- When the family has no child yet and you are the admin or a partner, the app shows "Add your first child". See [Children](children.md).
- When the family has no child yet and you are a family member, the app shows "No child yet". Ask the admin to add a child, then tap **Check again**.

### Check that it worked

- The **Timeline** tab shows the moments of a child.
- Open the **Settings** tab. The card at the top shows the family name, the server address, and your name and role.

## Join with an invitation link

An **invitation link** lets a new person create an account on the server. The admin makes the link in the app and sends it to you. See [Invite your family](../tutorials/invite-your-family.md).

The admin can send two links:

- A **web page** link that starts with the server address, for example `https://photos.example.com/invite/...`. The page has a button that opens the app. It also shows the server address and an invitation code.
- An **app link** that starts with `kinlore://invite/`. This link opens the app directly.

### Join from the link

1. On your phone, tap the app link. Or open the web page and tap its button to open the app.
2. If the phone does not know this server yet, the app asks "Join photos.example.com?" (with the address of the server). Tap **Join** only if you know and trust this server.
3. The app shows "Create your account". It tells you who invited you and with which role.
4. Type **Your name**, **Email**, **Password** and **Repeat the password**. The password must have at least 8 characters. If the invitation is for one email address, the app fills in that address.
5. Tap **Create account and join**.

### Join with a pasted link or code

Use these steps when the link does not open the app.

1. Open the app.
2. Type the server address in **Server address** and tap **Continue**.
3. On the sign-in screen, tap **I have an invitation**.
4. In **Invitation link or token**, paste the invitation link. You can also paste only the invitation code (the token).
5. Tap **Use invitation**.
6. Fill in the form and tap **Create account and join**.

### Check that it worked

The app shows "Loading your family...", then the timeline of the family.

## The family name

The **family name** is the name of the server for all its members, for example "Anna Example Family".

- The server owner can set the family name when they set up the server.
- The admin can change the family name in the app. See [Family and permissions](family-and-permissions.md#change-the-family-name).
- The app gets the family name from the server with each sync. A **sync** is the exchange of changes between the phone and the server.
- The app shows the family name in place of the server address: in **Settings**, in "Choose a family", on the **Families** screen, and in the header of the family screens, for example **Children** and **Members**.
- A member who is not the admin can give the family a different name on their own phone only. See [Several families](multiple-servers.md#rename-a-family-on-this-phone).

## When the server is not set up yet

A new server has no admin. The admin account is created in a web browser, never in the app.

If the server has no admin, the app shows a message like this: "This server is not set up yet. Open https://photos.example.com/setup in a web browser on your computer to create the admin account, then come back and tap Continue."

1. Open the setup page in a web browser. You can tap **Open in browser**.
2. Create the admin account. You need the setup token of the server. See [Create the first admin](../self-hosting/first-admin.md).
3. Go back to the app and tap **Continue**.
4. Sign in with the email and the password of the admin account.

## Problems

- **"Could not connect to the server. Check the address and your internet connection, then try again."**: the app could not reach the server. Check the address, the port and the network of the phone. See [Troubleshooting](troubleshooting.md).
- **"Enter a domain or IP, with an optional port, ..."**: the address has a wrong form. Type only the host name or the IP address, with an optional port.
- **"This invitation is not valid. It is used, expired or unknown. Ask for a new invitation."**: ask the admin for a new invitation.
- **"This invitation has no server address. Paste the full invitation link."**: you pasted only the code, and the phone does not know the server yet. Type the server address first, or paste the full link.
- For more messages, see [Troubleshooting](troubleshooting.md).
