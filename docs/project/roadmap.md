# Roadmap

This page gives the direction of the project and the planned work. Changes per release are in [CHANGELOG.md](https://github.com/Kinlore/kinlore-server/blob/develop/CHANGELOG.md).

## Direction

Kinlore is a self-hosted family photo journal. One Kinlore server holds **one family**:

- **One admin.** The owner creates the admin account in a web browser on the setup page of the server, or with the `SEED_USER_*` settings. The app never creates the admin.
- **Invited members.** Everyone else joins with an invitation from the admin. An invitation gives the role `partner` or `family`.
- **Shared family data.** All members see all children and all moments.
- **Roles and permissions.** A partner can add and change everything. A family member can view, and can add moments, like, comment and download when the admin allows it. The full rules are in [API rules, section 1](/reference/api#_1-roles-and-permissions).
- **Two ways to run it.** With Docker (PostgreSQL, SeaweedFS, Caddy with optional built-in HTTPS), see [Install with Docker](/self-hosting/docker). Or directly on a Linux host, where SQLite and the local disk are enough, see [Install without Docker](/self-hosting/bare-metal).

A family that wants a second space runs a second server. The app can hold several servers by address.

## Planned work

Open items, most useful first.

1. Run the app on real phones, and check the comment field with VoiceOver. The app is tested on the iOS simulator and the Android emulator.
2. Notifications: push and email for new moments and comments, and a daily digest.
3. A date-range filter in the app. The API has it.
4. Tag one moment to several children, and access per child for each member.
5. A web app for viewing, and for upload from a desktop computer.
6. Uploads that continue on Android after the system stops the app in the background. iOS does this; on Android the upload starts again when the app opens.
7. Smaller items: look-back in the time zone of the family, a "Change password" item for every member, a sessions screen (the API can list and revoke sign-ins), and cleanup of expired invitations.
