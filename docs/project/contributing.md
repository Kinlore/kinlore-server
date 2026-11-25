# Contributing

Bug reports, questions, ideas and code are welcome. Everyone who takes part must follow the [code of conduct](https://github.com/Kinlore/kinlore-server/blob/develop/CODE_OF_CONDUCT.md).

## Report a bug

1. Open an issue in [kinlore-server](https://github.com/Kinlore/kinlore-server/issues/new/choose) with the "Bug report" form.
2. Tell how you run the server (Docker or a host install), and on which system.
3. List the steps that show the problem, what you expected, and what happened.
4. Add the relevant lines of the server log. See [Maintenance](/self-hosting/maintenance#logs).

Remove passwords, tokens, email addresses and private photos from logs before you post them.

Problems in the app belong in the [kinlore-mobile](https://github.com/Kinlore/kinlore-mobile/issues) repository.

## Report a security problem

Do not open a public issue. Follow [SECURITY.md](https://github.com/Kinlore/kinlore-server/blob/develop/SECURITY.md).

## Ask a question or propose a feature

Open an issue with the "Feature request" form. Describe the problem of the family first, then your idea. Check the [Roadmap](/project/roadmap) first: the work may already be planned.

## Contribute code or documentation

Read [CONTRIBUTING.md](https://github.com/Kinlore/kinlore-server/blob/develop/CONTRIBUTING.md). It explains the development setup, the tests, the code style and the pull requests. Open pull requests against the branch `develop`.

This site is in the folder `docs/` of the server repository. To see it on your computer:

```sh
cd docs
npm install
npm run docs:dev
```

## Changes per release

See [CHANGELOG.md](https://github.com/Kinlore/kinlore-server/blob/develop/CHANGELOG.md).
