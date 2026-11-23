# Security policy

## Supported versions

Kinlore has no numbered release yet. Security fixes go to the `develop` branch and from there to `main`. Run the newest code to have all fixes.

## Report a vulnerability

Do not open a public issue for a security problem.

Report it in private with GitHub's private vulnerability reporting:

1. Open <https://github.com/elyess/kinlore-server/security/advisories/new> (the "Security" tab of this repository, then "Report a vulnerability").
2. Describe the problem, the affected version or commit, and the steps to reproduce it.
3. Add the impact you expect, and a fix if you have one.

Only the maintainers can read the report.

## What happens next

- The maintainers aim to answer within one week.
- They confirm the problem, prepare a fix and agree a publication date with you.
- The fix is published with a security advisory. The advisory names you as the reporter, unless you prefer not to be named.

## Scope

This policy covers the Kinlore server: the API, the Docker setup and the host-install files in this repository. Report problems in the mobile app in the [kinlore-mobile](https://github.com/elyess/kinlore-mobile) repository.

A problem in a dependency (Laravel, PHP, PostgreSQL, SeaweedFS, Redis, Caddy) belongs to that project, unless Kinlore's use of it causes the problem.

If you run a Kinlore server, see [docs/SELF_HOSTING.md](docs/SELF_HOSTING.md) for HTTPS, reverse proxies and backups.
