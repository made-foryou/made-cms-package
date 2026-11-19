# Contribution Guide

Thank you for considering contributing to Made Cms Package! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test
```

The tests run on SQLite in memory. To run them on MySQL or PostgreSQL, name the server with `DB_CONNECTION` (`mysql`, `mariadb` or `pgsql`), `DB_HOST`, `DB_PORT`, `DB_USERNAME` and `DB_PASSWORD`:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= composer test:unit
```

The tests create a database of their own on that server, `made_cms_package_test` (`made_cms_package_test_1`, `_2`, … for the parallel workers), migrate it and wipe it whenever they need to. They never read `DB_DATABASE` or `DB_URL`, so your own databases stay untouched; drop the `made_cms_package_test*` databases when you no longer need them.
