## What and why

<!-- Describe the change and the reason for it. Link the issue, for example "Closes #12". -->

## Checklist

- [ ] The pull request targets `develop`.
- [ ] `php artisan test` passes in `kinlore-api`.
- [ ] `vendor/bin/pint --test` passes in `kinlore-api`.
- [ ] For changes to Docker, `deploy/`, the install guides in `docs/self-hosting/` or `scripts/`: I ran `scripts/smoke-test.sh` or `scripts/test-bare-metal.sh`.
- [ ] For API changes: I updated `kinlore-api/openapi.yaml` and `docs/reference/api.md`.
- [ ] I updated the documentation, and `CHANGELOG.md` for changes that users notice.
