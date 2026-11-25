# Security policy

## Supported versions

| Version | Security fixes |
| --- | --- |
| 1.5.x | Yes |
| 1.4.x | No. Update to 1.5. |
| 1.3.x | No. Update to 1.5. |
| 1.2.x | No. Update to 1.5. |
| 1.1.x | No. Update to 1.5. |
| 1.0.x | No. Update to 1.5. |

Only the newest minor version gets fixes. They are released as a new patch version (1.5.1, 1.5.2 and so on). Always run the newest release.

## Report a vulnerability

Do not open a public issue for a security problem.

Report it in private with GitHub's private vulnerability reporting:

1. Open <https://github.com/Kinlore/kinlore-server/security/advisories/new> (the "Security" tab of this repository, then "Report a vulnerability").
2. Describe the problem, the affected version or commit, and the steps to reproduce it.
3. Add the impact you expect, and a fix if you have one.

Only the maintainers can read the report.

## What happens next

- The maintainers aim to answer within one week.
- They confirm the problem, prepare a fix and agree a publication date with you.
- The fix is published with a security advisory. The advisory names you as the reporter, unless you prefer not to be named.

## Scope

This policy covers the Kinlore server: the API, the Docker setup and the host-install files in this repository. Report problems in the mobile app in the [kinlore-mobile](https://github.com/Kinlore/kinlore-mobile) repository.

A problem in a dependency (Laravel, PHP, PostgreSQL, SeaweedFS, Redis, Caddy) belongs to that project, unless Kinlore's use of it causes the problem.

If you run a Kinlore server, see [Security](https://kinlore.github.io/kinlore-server/self-hosting/security), [HTTPS and reverse proxy](https://kinlore.github.io/kinlore-server/self-hosting/https-and-reverse-proxy) and [Backup and restore](https://kinlore.github.io/kinlore-server/self-hosting/backup-and-restore).
