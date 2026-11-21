# Kinlore Server

Kinlore is a self-hosted place for one family to keep the photos, videos, stories, milestones and growth records of their children. It is an alternative to hosted services such as Backthen: your memories stay on your own server, with no storage limits, subscriptions or print shop.

- One deployment holds one family. The first user is the admin. Everyone else joins with an invitation.
- The mobile app is in the `kinlore-mobile` repository. It works offline and syncs with this server.
- The API is described in [`kinlore-api/openapi.yaml`](kinlore-api/openapi.yaml) and agreed with the app in [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md).

Start here: **[docs/SELF_HOSTING.md](docs/SELF_HOSTING.md)** explains how to run the server with Docker or on bare metal.

Other documents:
- [docs/ROADMAP.md](docs/ROADMAP.md): direction, history and planned work.
- [docs/PROGRESS.md](docs/PROGRESS.md): status of each item.
- [docs/OPEN_QUESTIONS.md](docs/OPEN_QUESTIONS.md): decisions for the owner.

Run the tests:

```sh
cd kinlore-api && composer install && php artisan test
```
