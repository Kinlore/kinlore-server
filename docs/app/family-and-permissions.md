# Family and permissions

This page explains the roles and permissions of family members.
It also tells the admin how to invite people, change a member, change the family name, give the admin role to another member, and set a new password for a member.

A **member** is a person with an account on the family's server. Each member has one **role**.

## Roles

| Role | What the member may do |
| --- | --- |
| **Admin** | Everything. Only the admin manages members, invitations, the family name and the export. Each family has exactly one admin. |
| **Partner** | Add, edit and delete all children and all moments. Like and comment. Permissions do not apply to a partner. |
| **Family** | See all children and all moments. Do only what the admin allows with the permissions below. |

All members see all children and all moments of the family.

## Permissions of a family member

The admin turns each permission on or off for each family member. For a new invitation, all four permissions are on.

| Permission | What it allows |
| --- | --- |
| **Add photos and moments** | Add photos, videos, stories, milestones and growth records. Without it, the timeline has no **+** button. |
| **Comment** | Write comments on moments. |
| **Like** | Like moments. |
| **Download original files** | Get the original photo and video files from the server. The app shows photos in a smaller size and has no download button. |

A family member may also do these things, without a permission:

- Edit or delete the moments that they added.
- Edit or delete the moments of a child that they created, and that child.

A family member may not add a child.

You see your own role in **Settings**: under **Servers**, next to your name, and on the **Server** screen under **Your account**.

## Open the member list

Only the admin sees these screens.

1. Open the **Settings** tab.
2. Under **Servers**, tap the server that shows **In use**. The app shows its **Server** screen.
3. Under **Family**, tap **Members**. The header shows the server name.

The **Members** screen has two lists:

- **Members of** and the family name: each member with name, email and role. Your own entry has "(you)".
- **Pending invitations**: invitations that nobody has used yet, with the role, who sent the invitation ("invited by" and a name) and the date when they expire. When the member who sent it no longer exists, the name is not shown.

## Invite a person

1. On the **Members** screen, tap **Invite someone**. The app shows "Invite to" with the family name and the server address.
2. Optional: type an email in **Email (optional)**. With an email, only that address can use the invitation.
3. Under **Role**, tap **Family** or **Partner**.
4. For **Family**, turn the permissions on or off.
5. Under **Expires after**, tap **1 day**, **7 days**, **14 days** or **30 days**.
6. Tap **Create invitation**.
7. The app shows "Invitation ready" with two links: **Web page** and **App link**.
8. Tap **Share** to send the links with a message app. Or tap **Copy links** and paste them in a message.
9. Tap **Done**.

The links start with the public address of the server. If the server has no public address yet, the app shows "The server has no public address". The links then use the address that your phone uses. If that is an address on your home network, people outside it cannot open the links. To fix this, the server owner sets `APP_URL`. See [APP_URL placeholder](../self-hosting/configuration.md#app-url-placeholder).

For the full steps, from the admin to the first sign-in of the new member, see [Invite your family](../tutorials/invite-your-family.md).

### Check that it worked

The invitation is in **Pending invitations**. When the person has joined, the invitation is no longer in the list, and the person is in the list of members.

## Revoke an invitation

1. On the **Members** screen, find the invitation under **Pending invitations**.
2. Tap the **X** button next to it.
3. The app asks "Revoke invitation?". Tap **Revoke**.

The link stops working.

## Change the role or the permissions of a member

1. On the **Members** screen, tap the member.
2. Under **Role**, tap **Partner** or **Family**.
3. For **Family**, turn the permissions on or off.
4. Tap **Save**.

The member gets the new rights at once. The app of the member updates its screens when it loads the member's profile again, for example when the member opens **Settings**.

## Remove a member

1. On the **Members** screen, tap the member.
2. Tap **Remove member**.
3. Read the message and tap **Remove**.

After the removal:

- The member can no longer sign in.
- The children that the member created move to you, the admin.
- The photos and moments of the member stay.
- The likes and comments of the member are deleted.

You cannot remove yourself.

## Change the family name

The family name is the name of the server for all members, for example "Anna Example Family".

1. Open the **Settings** tab.
2. Under **Servers**, tap the server that shows **In use**. The app shows its **Server** screen.
3. Under **Server**, tap **Rename**. The row shows "Everyone on this server sees the new name".
4. In **Name**, type the new name. The app shows "You are the admin: everyone on this server sees the new name."
5. Tap **Save name**.

The name can have up to 60 characters. The other members see the new name after their next sync.

### Check that it worked

Under **Servers** in **Settings**, the server shows the new name.

## Give the admin role to another member

Each server has one admin. When you give the admin role to another member, you become a partner.

1. On the **Members** screen, tap the member.
2. Tap **Make admin**.
3. The app asks "Make" with the name of the member, "the admin of" and the family name. Tap **Make admin**.

After the change, you can no longer manage members, invitations or exports. Only the new admin can give the role back to you.

### Check that it worked

In **Settings**, your role is "Partner". On the **Server** screen, the **Members** and **Export** rows are gone.

## Set a new password for a member

Use this when a member forgot their password. The app has no other way to reset a password.

1. On the **Members** screen, tap the member. To change your own password, tap your own entry.
2. Under **Set new password**, type the new password. It must have at least 8 characters.
3. Tap **Save new password**.
4. Read the message and tap **Set password**.
5. Give the new password to the member.

For another member: the member is signed out on all devices and must sign in with the new password.
For yourself: your other devices are signed out. This phone stays signed in.

### Check that it worked

The app shows "Password changed." under the field, with what happens to the sign-ins.

## Problems

- **"The password must have at least 8 characters."**: type a longer password.
- **"Could not load members."**: the app could not reach the server, or you are no longer the admin.
- **"Could not create the invitation."**: check the connection and try again.
- **"The server has no public address"** on the invitation: the server owner sets `APP_URL`. See [APP_URL placeholder](../self-hosting/configuration.md#app-url-placeholder).
- **"Name not saved"**: the server did not save the family name. Check the connection and try again.
- **"You cannot change your own role. Make another member admin instead."**: use **Make admin** on another member.
- For more messages, see [Troubleshooting](troubleshooting.md).
