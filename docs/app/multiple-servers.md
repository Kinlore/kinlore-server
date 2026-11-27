# Several servers

This page tells you how to use more than one server in the app: add a server, switch between servers, change the name of a server, and remove a server from your phone.

In Kinlore, one server is one family. You need more than one server when you belong to more than one family, for example your own family and the family of your parents. Each family has its own server.

Each server keeps its own sign-in, its own copy of the data on the phone and its own upload queue. Two servers never share data on the phone.

The app opens the server that you used last.

## Where the app shows the server

- **Settings** lists all servers on this phone under **Servers**. **In use** marks the server that the app shows now. See [Settings](settings.md#servers).
- With more than one server, the timeline shows the server name above the name of the child.
- The screens of a server, for example **Children**, **Members**, **Invite** and **Export**, show the server name in their header.

## Switch to another server

You can switch from two places: the **Server** screen in **Settings**, and "Choose a server".

### From Settings

1. Open the **Settings** tab.
2. Under **Servers**, tap the server. The app shows its **Server** screen.
3. Tap **Switch to this server**. When you are not signed in there, the button is **Sign in to this server**, and the app then shows the sign-in screen. Sign in. To join this server with an invitation, tap **I have an invitation**.

### From "Choose a server"

"Choose a server" is a list of all servers on this phone. Each server shows its name, its address, the email that you use there ("You:" and an email, or "Not signed in"), and a status, for example **Online** or **Offline**. A check mark shows the server in use.

You can open "Choose a server" from these places. They show only when the phone has more than one server.

- On the timeline, tap the server name at the top of the screen.
- On the sign-in screen, tap **Use another server**. With one server, this button opens the screen where you type a server address.
- On the "Add your first child" screen, tap **Use another server**.

1. Open "Choose a server".
2. Tap the server.
3. If the phone has no sign-in for that server, the app shows the sign-in screen. Sign in, or tap **I have an invitation**.

In "Choose a server", **Add a server** opens "Add a server", and **Manage servers** opens **Settings**.

### Check that it worked

In **Settings**, the server shows **In use**. The timeline shows the children of that server.

## Add a server

1. Open the **Settings** tab.
2. Under **Servers**, tap **Add a server**. You can also tap **Add a server** in "Choose a server". The app shows "Add a server".
3. In **Server address**, type the address of the server, for example `photos.example.com`.
4. Tap **Test connection**. The button shows **Connected** when the server answers.
5. Optional: type a **Display name (optional)**.
6. Tap **Add server**.

The app adds the server and opens it at once. So the next screen is the sign-in screen of the new server. Sign in, or tap **I have an invitation**.
After the first sync, the app shows the family name of the server as the server name.

### Check that it worked

The new server is under **Servers** in **Settings**, and in "Choose a server".

## Change the server name

1. Open the **Settings** tab and tap the server.
2. Under **Server**, tap **Rename**.
3. Type the name.
4. Tap **Save name**.

Who sees the new name depends on you and the server:

- You are the admin, and the server is in use: the app shows "You are the admin: everyone on this server sees the new name." The name is the family name for all members. See [Family and permissions](family-and-permissions.md#change-the-family-name).
- In all other cases, the app shows "This name is only on this phone. Leave it empty to use the name the server reports." To use the name of the server again, clear the field and save.

## Remove a server from this phone

When you remove a server, the app deletes the data of that server on this phone. The data on the server stays.
You cannot remove the server in use. Switch to another server first. With only one server, use **Reset this phone** in **Settings**. See [Settings](settings.md#reset-this-phone).

1. Open the **Settings** tab and tap the server.
2. Tap **Remove from this phone**.
3. The app asks "Remove" with the name of the server. Tap **Remove**.

The app removes the server, its sign-in and its data from the phone. To use the server again, add it again and sign in.

Stories, changes or uploads that have not reached that server yet are lost. Before you remove a server, switch to it and let it sync. When the app knows that changes are not sent yet, it shows "Cannot remove this server yet" and removes nothing.

### Check that it worked

The server is not under **Servers** in **Settings**.

## Problems

- **"Server already configured"**: the server is already on this phone. Find it under **Servers** in **Settings**.
- **"Test the connection first."**: tap **Test connection** before **Add server**.
- **"Server not reachable"** or **"Server address not found"**: check the address and the network. See [Troubleshooting](troubleshooting.md).
- **"Could not open"** with the name of the server: the app could not switch. Check the connection and try again.
- **"Could not switch to this server. Check the connection and try again."**: the same problem, on the **Server** screen.
- **"The server was added, but it did not open. Open it from Settings."**: open **Settings**, tap the new server, then tap **Switch to this server** or **Sign in to this server**.
- **"Cannot remove this server"**: the server is in use. Switch to another server first.
- **"Cannot remove this server yet"**: the server has unsent changes on this phone. Switch to it and let the changes sync, then try again.
