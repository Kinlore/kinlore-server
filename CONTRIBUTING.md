# Contributing to Kinlore Server

Thank you for your help. This file explains how to report problems, how to set up the code, and how to send a change.

By contributing, you agree that your contributions are licensed under the project's license (see LICENSE).

Everyone who takes part must follow the [code of conduct](CODE_OF_CONDUCT.md).

## Report a bug

Open an issue with the "Bug report" form. Tell us:

- how you run the server (Docker or a host install) and on which system;
- the steps that show the problem;
- what you expected and what happened;
- the relevant lines of the server log. In Docker: `docker compose logs api`. On a host install: `storage/logs/` in `kinlore-api`.

Remove passwords, tokens, email addresses and private photos from logs before you post them.

Do not report security problems in a public issue. Follow [SECURITY.md](SECURITY.md).

Problems in the mobile app belong in the [kinlore-mobile](https://github.com/elyess/kinlore-mobile) repository.

## Propose a feature

Open an issue with the "Feature request" form. Describe the problem for the family first, then your idea. Check [docs/ROADMAP.md](docs/ROADMAP.md) to see if the work is already planned. For a large change, wait for a reply on the issue before you write code.

## Local development setup

You need PHP 8.5 with the extensions `pdo_sqlite`, `gd`, `exif`, `zip`, `intl`, `mbstring` and `bcmath`, and Composer. FFmpeg is needed only to convert videos.

The steps below use SQLite and the local disk, so you need no database server, Redis or S3 storage.

```sh
cd kinlore-api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

Then change these lines in `kinlore-api/.env`:

```ini
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/kinlore-api/database/database.sqlite
SEAWEEDFS_DRIVER=local
```

`SEAWEEDFS_DRIVER=local` keeps photos and videos in `storage/app/media`. Set `MEDIA_ROOT` to use another folder.

Create the tables and the list of milestones, then start the server:

```sh
php artisan migrate --seed
php artisan serve
```

Check it with `curl http://127.0.0.1:8000/api/health`. Open `http://127.0.0.1:8000` in a browser to create the admin account.

Videos and exports are processed by a queue worker. Start one in a second terminal:

```sh
php artisan queue:work --queue=video-processing,default
```

To work on the full Docker stack instead, follow [docs/SELF_HOSTING.md](docs/SELF_HOSTING.md).

### Try the API by hand

[`kinlore-api/openapi.yaml`](kinlore-api/openapi.yaml) describes every endpoint. Postman, Insomnia and similar tools can import it.

## Run the tests

There are three kinds of tests. The GitHub workflow runs all three on every push to `main` and `develop` and on every pull request.

1. **Unit and feature tests.** They use an in-memory SQLite database and touch no data.
   ```sh
   cd kinlore-api
   php artisan test
   ```
   They need the `.env` file and `APP_KEY` from the setup above.

2. **End-to-end test** of a running server: `scripts/smoke-test.sh`. It signs in as the admin and tries every feature over HTTP. It creates test data, so use a test instance only. It needs `curl`, `jq` and `uuidgen`. The video checks need FFmpeg, or a test video in `SMOKE_VIDEO=/path/to/file.mp4`.
   ```sh
   scripts/smoke-test.sh http://localhost admin@example.test 'admin-password'
   ```
   The last line must say `Failed: 0`. To test the Docker stack, start it with `SEED_USER_EMAIL` and `SEED_USER_PASSWORD` set in `.env`, then use those values.

3. **Host-install test**: `scripts/test-bare-metal.sh`. It needs only Docker. It follows [docs/BARE_METAL.md](docs/BARE_METAL.md) in a clean Debian 13 container and runs the end-to-end test through Caddy and through nginx. It takes several minutes.
   ```sh
   scripts/test-bare-metal.sh
   ```

Run the unit and feature tests before every pull request. Run the other two when you change the Docker files, `deploy/`, the install guides or the scripts.

## Code style

- **Laravel Pint** formats the PHP code with the rules in [`kinlore-api/pint.json`](kinlore-api/pint.json). Run `vendor/bin/pint` in `kinlore-api` before you commit. `vendor/bin/pint --test` only checks.
- **Strict types.** Every PHP file starts with `declare(strict_types=1);`. Pint adds it when it is missing.
- **Enums** in `app/Enums` hold fixed sets of values: roles, moment types, video and export statuses, interaction types. Use them, not strings.
- **Laravel 13 attributes.** Models declare `#[Fillable]`, `#[Appends]` and `#[UsePolicy]` as attributes. Jobs declare `#[Queue]`, `#[Tries]` and `#[Timeout]`. Artisan commands declare `#[Signature]` and `#[Description]`. Follow the same form in new code.
- **Responses.** API controllers return JSON through `App\Http\Responses\ApiResponse`, which builds the envelope of [docs/API_CONTRACT.md](docs/API_CONTRACT.md).
- **Permissions.** Check permissions with `User::hasPermission()` and the policies in `app/Policies`.
- Add or change tests for each change of behaviour.

## Changes to the API

The mobile app depends on the API. When you add, remove or change an endpoint, a field or a rule, update in the same pull request:

- [`kinlore-api/openapi.yaml`](kinlore-api/openapi.yaml), the OpenAPI description;
- [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md), the behaviour rules.

`tests/Feature/OpenApiRoutesTest.php` compares the paths in `openapi.yaml` with the routes of the server. It fails when they differ. A change that breaks the app also needs a matching change in [kinlore-mobile](https://github.com/elyess/kinlore-mobile).

## Branches and pull requests

- `main` is the stable branch. `develop` is the working branch.
- Fork the repository and make a branch from `develop`.
- Open the pull request against `develop`. Fill in the checklist of the pull request template.
- Keep one topic per pull request.
- The tests must pass before a pull request is merged.

## Commit messages

Start the subject with a type, a colon and a short summary in the present tense:

```
fix: sync pull returns the moments of deleted kids
feat: date-range filter for moments
docs: explain the reverse proxy settings
refactor: move the export ZIP layout into a service
test: cover the invitation expiry
chore: update Composer packages
```

## Documentation style

- Write plain English in short sentences, one idea per sentence.
- Use the active voice.
- Use the same word for the same thing everywhere.
- Write for a reader who has never seen the project.
- Show commands that work as they are written. Test them.
- Update the documents in `docs/` in the same pull request as the change they describe. Add a line to the `Unreleased` section of [CHANGELOG.md](CHANGELOG.md) for a change that users notice.
