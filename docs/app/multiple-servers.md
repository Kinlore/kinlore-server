# Multiple servers

This page tells you how to use more than one server in the app: add a server, switch between servers, choose a default server, rename a server on your phone, and remove a server.

You need more than one server when you belong to more than one family, for example your own family and the family of your parents. Each family has its own server.

Each server keeps its own sign-in, its own copy of the data on the phone and its own upload queue. Two families never share data on the phone.

## Open the server list

1. Open the **Settings** tab.
2. Under **Server**, tap the row with the server name. The row also shows how many servers the app knows, for example "2 servers configured".

The app shows "Servers". Each server shows its name, its address and the name that you use there ("Signed in as" and a name).

- A check mark shows the **active** server: the server that the app shows now.
- A star shows the **default** server.
- A status shows if the server answers, for example **Online** or **Offline**.

Pull the list down to check the status of all servers again.

## Add a server

1. On the **Servers** screen, tap the **+** button at the top right ("Add server"). The app shows "Add Server".
2. In **Server URL**, type the address of the server, for example `photos.example.com`.
3. Tap **Test Connection**. The button shows **Connected** when the server answers.
4. Optional: type a **Display Name (Optional)**.
5. Tap **Add Server**.

The app adds the server to the list. The server does not become active.
After the first sync with the server, the app shows the family name of the server.

### Check that it worked

The new server is in the list on the **Servers** screen.

## Switch to another server

1. On the **Servers** screen, tap the server.
2. If the phone has no sign-in for that server, the app shows the sign-in screen. Sign in. To join this family with an invitation, tap **I have an invitation**.

You can also long-press the server, or open its details, and tap **Switch to this Server**.

### Check that it worked

The server has the check mark. The **Settings** tab shows its name under **Server**. The timeline shows the children of that family.

## Choose the default server

The **default** server is the server that the app opens when no server was active before.

1. On the **Servers** screen, long-press the server.
2. Tap **Set as Default**.

To remove the default, open the server details and tap **Remove as Default**.

## See the server details

1. On the **Servers** screen, tap the active server. For another server, long-press it and tap **View Details**.
2. The app shows "Server Details": the address (**URL**), the **Status**, the time of the last connection, your account, and, when the app knows them, the number of children and members.

To check the connection again, tap the refresh button next to **Status** ("Test connection").

## Rename a server on this phone

You can give a server a name that only you see, on this phone.

1. Open the server details.
2. Tap the pencil button at the top right ("Rename server").
3. Type the name. The app shows "This name is only on this phone. Leave it empty to use the family name."
4. Tap the check mark ("Save name").

To use the family name again, clear the field and save.

For the admin, the pencil on the active server changes the family name for all members. See [Family and permissions](family-and-permissions.md#change-the-family-name).

## Remove a server

When you remove a server, the app deletes the data of that server on this phone. The data on the server stays.
You cannot remove the active server. Switch to another server first.

1. On the **Servers** screen, long-press the server.
2. Tap **Remove**. Or open the server details and tap **Remove Server**.
3. The app asks "Remove Server". Tap **Remove**.

The app removes the server, its sign-in and its data from the phone. To use the server again, add it again and sign in.

Stories, changes or uploads that have not reached that server yet are lost. Before you remove a server, switch to it and let it sync.

### Check that it worked

The server is not in the list on the **Servers** screen.

## Problems

- **"Server already configured"**: the app already knows this server. Find it in the list.
- **"Please test the connection first"**: tap **Test Connection** before **Add Server**.
- **"Server not reachable"** or **"Server address not found"**: check the address and the network. See [Troubleshooting](troubleshooting.md).
- **"Cannot Remove"**: the server is active, or it has unsent changes. Switch to another server first, or let the changes sync.
