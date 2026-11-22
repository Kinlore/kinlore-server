# Kinlore Server

Kinlore is a self-hosted place for one family to keep the photos, videos, stories, milestones and growth records of their children. It is an alternative to hosted services such as Backthen: your memories stay on your own server, with no storage limits, subscriptions or print shop.

- One deployment holds one family. The owner creates the admin account in a web browser at `/setup`. Everyone else joins with an invitation.
- The mobile app is in the `kinlore-mobile` repository. It works offline and syncs with this server.
- The API is described in [`kinlore-api/openapi.yaml`](kinlore-api/openapi.yaml) and agreed with the app in [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md).

Start here:
- **[docs/SELF_HOSTING.md](docs/SELF_HOSTING.md)**: run the server with Docker, with built-in HTTPS or behind your own reverse proxy.
- **[docs/BARE_METAL.md](docs/BARE_METAL.md)**: install the full server directly on a Linux host, without Docker.

Other documents:
- [docs/ROADMAP.md](docs/ROADMAP.md): direction, history and planned work.
- [docs/PROGRESS.md](docs/PROGRESS.md): status of each item.
- [docs/OPEN_QUESTIONS.md](docs/OPEN_QUESTIONS.md): decisions for the owner.

Run the tests:

```sh
cd kinlore-api && composer install && php artisan test   # unit and feature tests
scripts/smoke-test.sh <address> <admin email> <password>  # end-to-end, against a running test instance
scripts/test-bare-metal.sh                                # the host-install guide, in a clean Debian container
```
