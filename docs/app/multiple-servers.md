# Several families

This page tells you how to use more than one family in the app: add a family, switch between families, choose a default family, rename a family on your phone, and remove a family from your phone.

In Kinlore, one server is one family. You need more than one family when you belong to more than one, for example your own family and the family of your parents. Each family has its own server.

Each family keeps its own sign-in, its own copy of the data on the phone and its own upload queue. Two families never share data on the phone.

## Where the app shows the family

- **Settings** starts with a card that shows the active family: its name, its address, and your name and role there.
- With more than one family, the timeline shows the family name above the name of the child.
- The screens of a family, for example **Children**, **Members**, **Invite** and **Export**, show the family name in their header.

## Switch to another family

The app shows "Choose a family", a list of all families on this phone. Each family shows its name, its address, the email that you use there, and a status, for example **Online** or **Offline**. A check mark shows the active family.

You can open "Choose a family" from three places:

- In **Settings**, tap **Switch family** on the family card.
- On the timeline, tap the family name at the top of the screen.
- On the sign-in screen, tap **Use another family**.

These buttons show only when the phone has more than one family.

1. Open "Choose a family".
2. Tap the family.
3. If the phone has no sign-in for that family, the app shows the sign-in screen. Sign in. To join this family with an invitation, tap **I have an invitation**.

### Check that it worked

The family card in **Settings** shows the new family. The timeline shows the children of that family.

## Add a family

1. Open the **Settings** tab.
2. Under **Families on this phone**, tap **Add another family**. You can also tap **Add another family** in "Choose a family". The app shows "Add a family".
3. In **Server address**, type the address of the server, for example `photos.example.com`.
4. Tap **Test connection**. The button shows **Connected** when the server answers.
5. Optional: type a **Display name (optional)**.
6. Tap **Add family**.

The app adds the family and opens it at once. So the next screen is the sign-in screen of the new family. Sign in, or tap **I have an invitation**.
After the first sync, the app shows the family name of the server.

### Check that it worked

The new family is in "Choose a family" and on the **Families** screen.

## Open the family list

The **Families** screen lists all families on this phone. It has more options than "Choose a family".

1. Open the **Settings** tab.
2. Under **Families on this phone**, tap **Families**. This row shows only when the phone has more than one family. You can also tap **Manage families** in "Choose a family".

Each family shows its name, its address and the account that you use there ("You:" and an email).

- **In use** and a check mark show the **active** family: the family that the app shows now.
- A star shows the **default** family.
- A status shows if the server answers, for example **Online** or **Offline**.

Tap a family to switch to it. Tap the active family, or the info button of a family, to open its details. Long-press a family for more options.
Pull the list down to check the status of all families again. To add a family, tap the **+** button at the top right ("Add a family").

## Choose the default family

The **default** family is the family that the app opens when no family was active before.

1. On the **Families** screen, long-press the family.
2. Tap **Set as default**.

You can also open the family details and tap **Set as default**. To remove the default, open the family details and tap **Remove as default**.

## See the family details

1. With one family: in **Settings**, under **Families on this phone**, tap **Server details**. With several families: on the **Families** screen, tap the info button of the family.
2. The app shows "Family details": the **Address**, the **Status**, the time of the last connection, your account there, and, when the app knows them, the number of children and members.

To check the connection again, tap the refresh button next to **Status** ("Test connection").
For a family that is not active, tap **Switch to this family** to open it.

## Rename a family on this phone

You can give a family a name that only you see, on this phone.

1. Open the family details.
2. Tap the pencil button at the top right ("Rename on this phone").
3. Type the name. The app shows "This name is only on this phone. Leave it empty to use the family name."
4. Tap **Save name**.

To use the family name again, clear the field and save.

For the admin, the pencil on the active family changes the family name for all members. See [Family and permissions](family-and-permissions.md#change-the-family-name).

## Remove a family from this phone

When you remove a family, the app deletes the data of that family on this phone. The data on the server stays.
You cannot remove the active family. Switch to another family first.

1. On the **Families** screen, long-press the family and tap **Remove**. Or open the family details and tap **Remove from this phone**.
2. The app asks "Remove" with the name of the family. Tap **Remove**.

The app removes the family, its sign-in and its data from the phone. To use the family again, add it again and sign in.

Stories, changes or uploads that have not reached that server yet are lost. Before you remove a family, switch to it and let it sync. When the app knows that changes are not sent yet, it shows "Cannot remove this server yet" and removes nothing.

### Check that it worked

The family is not on the **Families** screen.

## Problems

- **"Server already configured"**: the family is already on this phone. Find it on the **Families** screen.
- **"Test the connection first."**: tap **Test connection** before **Add family**.
- **"Server not reachable"** or **"Server address not found"**: check the address and the network. See [Troubleshooting](troubleshooting.md).
- **"Could not open"** with the name of the family: the app could not switch. Check the connection and try again.
- **"The family was added, but it did not open. Open it from Families."**: open the **Families** screen and tap the new family.
- **"Cannot remove this family"**: the family is active. Switch to another family first.
- **"Cannot remove this server yet"**: the family has unsent changes on this phone. Switch to it and let the changes sync, then try again.
