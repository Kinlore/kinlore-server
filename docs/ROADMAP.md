# Kinlore Server Roadmap

This file gives the direction of the project and the planned work. The exact rules between server and app are in [API_CONTRACT.md](API_CONTRACT.md).

## 1. Direction

Kinlore is a self-hosted alternative to hosted family journals such as Backthen. One Kinlore deployment holds **one family**:

- **One admin.** The owner creates the admin account in a web browser on the server's `/setup` page (or with `SEED_USER_*`). The app never creates the admin.
- **Invited members.** Everyone else joins with an invitation that the admin creates. An invitation gives the role `partner` or `family`.
- **Shared family data.** All members see all kids and memories.
- **Roles and permissions.** A partner can add and change everything. A family member can view, and can add content, like, comment and download when the admin allows it. The full rules are in [API_CONTRACT.md](API_CONTRACT.md) section 1.
- **Two ways to run it.** Docker (PostgreSQL, SeaweedFS, Caddy with optional built-in HTTPS), see [SELF_HOSTING.md](SELF_HOSTING.md). Or directly on a Linux host (SQLite and the local disk are enough), see [BARE_METAL.md](BARE_METAL.md).

A family that wants a second space runs a second deployment. The mobile app can hold several deployments by address.

## 2. Planned work

Open items, most useful first.

1. Run the app on Android and on real phones (so far: iOS simulator only), and check the comment field with VoiceOver.
2. Notifications: push and email for new memories and comments, and a daily digest.
3. Date-range filter in the app (the API has it).
4. Tag one memory to several children; access per child for each member.
5. A web app for viewing and for upload from a desktop.
6. Smaller items: look-back in the family's time zone, a "Change password" item for every member, a sessions screen (the API can list and revoke sign-ins), cleanup of expired invitations.
