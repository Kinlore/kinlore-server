# Invite your family

This tutorial takes you from the admin's phone to the first sign-in of an invited person.
In the example, the admin invites Ben to the server of "Anna Example Family" at `photos.example.com`.

## What you need

- You are the admin of the family, and you are signed in to the app. See [Connect to a server](../app/connect.md).
- The family has at least one child. See [Children](../app/children.md).
- The person that you invite has the Kinlore app on their phone. See [Install the app](../app/install.md).
- The phone of the person can reach the server. For a server at home, the phone must use the home network.

## Part 1: The admin makes the invitation

1. Open the **Settings** tab. The card at the top shows the family name. With several families, check that it is the right family, or tap **Switch family**.
2. In the family section, tap **Invite someone**. The app shows "Invite to Anna Example Family", with the server address.
3. In **Email (optional)**, type the email of the person, for example `ben@example.com`. Then only this address can use the invitation. You can also leave the field empty.
4. Under **Role**, tap a role:
   - **Partner** for a person who manages the children and the moments with you, for example the other parent.
   - **Family** for all other people, for example grandparents.
5. For **Family**, choose the permissions. All four are on. Turn off what the person may not do:
   - **Add photos and moments**
   - **Comment**
   - **Like**
   - **Download original files**
6. Under **Expires after**, tap the number of days, for example **7 days**.
7. Tap **Create invitation**.

The app shows "Invitation ready", with a **Web page** link and an **App link**. The links start with the public address of the server, for example `https://photos.example.com/invite/...`.

If the app also shows "The server has no public address", the server still has the example address in its settings. The links then use the address that your phone uses. If that is an address on your home network, Ben can open the links only on that network. Ask the server owner to set `APP_URL` to the public address, then make a new invitation. See [APP_URL placeholder](../self-hosting/configuration.md#app-url-placeholder).

## Part 2: The admin sends the invitation

1. Tap **Share**.
2. Choose a message app, for example the text message app of your phone.
3. Choose the person and send the message.

The message has both links. If you prefer, tap **Copy links** and paste the links in any message.
Then tap **Done**.

Send the invitation only to the person that you invite. Anyone with the link can use it until it expires, unless you typed an email.

### Check that it worked

In **Settings**, tap **Members**. The invitation is under **Pending invitations**, with the role, "invited by" with your name, and the date when it expires.

## Part 3: The invited person joins

The person does these steps on their own phone.

1. Open the message from the admin.
2. Tap the app link that starts with `kinlore://invite/`. Or tap the web page link, then tap the button on the page to open the app.
3. The app asks "Join photos.example.com?". Tap **Join**.
4. The app shows "Create your account", and who sent the invitation.
5. Type **Your name**, for example "Ben".
6. Check the **Email**. If the admin typed an email, the app fills it in.
7. Type a **Password** with at least 8 characters. Type it again in **Repeat the password**.
8. Tap **Create account and join**.

The app shows "Loading your family...", then the timeline of the family.

If the link does not open the app:

1. Open the app.
2. In **Server address**, type the server address, for example `photos.example.com`. The web page of the invitation shows it.
3. Tap **Continue**.
4. Tap **I have an invitation**.
5. Paste the invitation link, or type the invitation code from the web page.
6. Tap **Use invitation**.
7. Continue with step 5 above.

### Check that it worked

- On the phone of the person, the timeline shows the children of the family.
- In **Settings**, the card at the top shows the family name, and the person's name and role, for example "You: Ben, Family".
- On the admin's phone, open **Members**. The person is in the list of members, and the invitation is no longer under **Pending invitations**.

## Part 4: The first sign-in on another phone

The account now exists. On a second phone, or after a sign-out, the person signs in with the email and the password:

1. Open the app.
2. Type the server address in **Server address** and tap **Continue**.
3. Type the **Email** and the **Password**.
4. Tap **Sign in**.

If the person already uses Kinlore for another family, they add this family in **Settings** with **Add another family**. See [Several families](../app/multiple-servers.md#add-a-family).

## Change the rights later

The admin can change the role and the permissions of a member at any time. See [Family and permissions](../app/family-and-permissions.md).

## Problems

- **"This invitation is not valid. It is used, expired or unknown. Ask for a new invitation."**: the admin makes a new invitation.
- **"A valid invitation is required to join this instance."**: the email in the form is not the email of the invitation. Use the email that the admin typed, or ask for a new invitation.
- **"Could not connect to the server"** or **"Cannot reach the server"**: the phone cannot reach the server. Check the network of the phone.
- **The link opens `photos.example.com` or a home address that does not work.** The server has no public address. See [APP_URL placeholder](../self-hosting/configuration.md#app-url-placeholder).
- **The person forgot the password.** The admin sets a new one. See [Family and permissions](../app/family-and-permissions.md#set-a-new-password-for-a-member).
- For more messages, see [Troubleshooting](../app/troubleshooting.md).
