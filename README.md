# Phalcon API

Base library for API features in Phalcon, including route authorization,
authentication, token parsing, request handling and responses.

## Requirements

- PHP 8.4 or newer.
- Phalcon 5 or newer.
- `firebase/php-jwt ^7.2.1` when using the optional JWT token parser.

## Installation

```sh
composer require redound/phalcon-api:^7.0
```

For JWT authentication, also install the optional JWT dependency:

```sh
composer require firebase/php-jwt:^7.2.1
```

## Releases and migration

See [CHANGELOG.md](CHANGELOG.md) for release notes and the
[migration guide for 7.0.0](CHANGELOG.md#migration-from-6x).

## Tests

With the required PHP extensions installed, install the development dependencies
and run the isolated regression suite:

```sh
composer install
composer test
composer audit --locked
```

The tests use isolated application state and test signing keys. They do not
modify external applications. Validate each consuming application's runtime,
configuration and dependency lockfile when updating.
