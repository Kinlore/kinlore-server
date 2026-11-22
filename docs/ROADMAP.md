# Kinlore Server Roadmap

Last updated: 2026-11-22. This file gives the direction, the history and the planned work. The status of each item is in [PROGRESS.md](PROGRESS.md). The exact rules between server and app are in [API_CONTRACT.md](API_CONTRACT.md). Decisions for the owner are in [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md).

## 1. Direction

Kinlore is a self-hosted alternative to hosted family journals such as Backthen. One Kinlore deployment holds **one family**:

- **One admin.** The owner creates the admin account in a web browser on the server's `/setup` page (or with `SEED_USER_*`). The app never creates the admin.
- **Invited members.** Everyone else joins with an invitation that the admin creates. An invitation gives the role `partner` or `family`.
- **Shared family data.** All members see all kids and memories.
- **Roles and permissions.** A partner can add and change everything. A family member can view, and can add content, like, comment and download when the admin allows it. The full rules are in API_CONTRACT §1.
- **Two ways to run it.** Docker (PostgreSQL, SeaweedFS, optional Caddy proxy) or bare metal (SQLite and local disk are enough). See [SELF_HOSTING.md](SELF_HOSTING.md).

A family that wants a second space runs a second deployment. The mobile app can hold several deployments by address.

## 2. History: how the server became multi-tenant

There was no roadmap or design document before this file. This history comes from git.

| Date | Commit | Change |
|---|---|---|
| 2025-12-20 → 12-29 | `5671aad` … `e5f07e1` | Single deployment. Each user owns their own kids (`kids.user_id`). No sharing, roles or invitations. |
| 2025-12-29 | `a151002` | **The split.** Adds `servers`, `server_members` (owner, contributor, viewer), `invitations`, `kids.server_id`. A data migration creates a "{name}'s Family" server for every user. |
| 2025-12-29 | `5abf1e0` | Sync sets `server_id` on kids. |
| 2025-12-30 | `95fa097` | Every sign-up without an invitation creates a new server. This makes the API multi-tenant. |
| 2025-12-30 | `cd1610f` | `/auth/me` lists the user's servers. |
| 2025-12-30 | `37946a0` | Moment, media and sync access use server membership. The commit also adds security hardening. |
| 2026-01-08 → 01-09 | `d8aee80`, `d0717b7`, `dd578ee` | Request classes (including server and invitation ones), the `ApiResponse` refactor, and Redis rate limiting. |
| 2026-11-21 | PR #1 (`e905435`, `b9871a1`) | **Back to a single instance.** The multi-tenant commits are reverted; the security hardening of `37946a0` is kept. Merged to `main`. |
| 2026-11-22 | (stash dropped) | The uncommitted multi-server work (server discovery, per-server limits, audit logs) was dropped on the owner's decision. |

## 3. Planned work

Open items, most useful first. Details and status are in [PROGRESS.md](PROGRESS.md).

1. Run the app on Android and on real phones (so far: iOS simulator only), and check the comment field with VoiceOver.
2. Notifications: push and email for new memories and comments, and a daily digest.
3. Date-range filter in the app (the API has it).
4. Tag one memory to several children; access per child for each member.
5. A web app for viewing and for upload from a desktop.
6. Smaller items: look-back in the family's time zone, a "Change password" item for every member, a sessions screen (the API can list and revoke sign-ins), cleanup of expired invitations.
