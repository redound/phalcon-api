# Changelog

## [7.0.0] - 2026-10-15

This major release mitigates the high-priority route authorization and
JWT authentication findings API-01 through API-05. Applications must migrate
their permission declarations and JWT configuration before deployment. Legacy
JWTs are invalidated; users must authenticate again.

### Security fixes

- Resolve collection route permissions to one internal resource per handler, so
  an allowed route path or name cannot bypass a matching deny.
- Reject duplicate route names, duplicate collection mounts and unknown route or
  role references before mounting permissions.
- Reset the current authentication session at the start of each request and
  before login or token authentication, preventing identity reuse after failure.
- Remove the default JWT signing key and require explicit parser configuration.
- Validate supported algorithms, key strength, issuer, audience and token claims.
  Separate the configured issuer from the internal account type.

### Added

- `Manager::resetSession()` for explicitly clearing authentication context.
- `Api::getRouteAclResource()` and `Collection::getAclResource()` for the mounted
  collection permission model.
- Optional token parser injection through `FactoryDefault`.
- An isolated security regression suite, available as `composer test`.

### Dependencies

- Recommend `firebase/php-jwt ^7.2.1` and use it as a development dependency for
  the tests. JWT remains optional for applications that use another token parser.
- PHP and Phalcon requirements remain PHP 8.4+ and Phalcon 5+.

### Migration from 6.x

#### Route permissions

Mount `PhalconApi\Mvc\Collection` instances on `PhalconApi\Api` and mount the
application or the same collection instances on the ACL after registering roles.
Keep `AuthenticationMiddleware` before `AuthorizationMiddleware` in the event
listener order. ACL declarations still require the authorization middleware.

Each collection handler has one internal ACL resource. `allow()`, `deny()`,
`allowPath()`, `denyPath()`, `allowName()` and `denyName()` all resolve to that
resource. Any matching deny takes precedence over matching allows for that role
and HTTP method. Without a grant, access is denied. Paths are relative to the
collection prefix; names match handlers in that collection. `*` supports all
roles, paths, names or methods within the corresponding declaration scope.
Phalcon role inheritance remains available.

Route names must be unique across the application, including within a collection.
Duplicate names and mounting the same collection twice throw
`InvalidArgumentException` before route registration. Unknown route or role
references in ACL declarations fail when compiling the ACL, before any of those
permissions are mounted. Register roles before mounting permissions.

Replace direct ACL grants against route paths or names with collection
permission declarations. If custom code checks a mounted route explicitly, use
`$app->getRouteAclResource($route)`. Internal resource IDs belong to the current
collection instances: rebuild the ACL at application initialization instead of
persisting them between processes.

#### Authentication lifecycle

The authentication middleware resets the current session at the beginning of
each request, including requests without a token. `Manager::login()` and
`Manager::authenticateToken()` also reset the session before attempting a new
identity. Failed authentication, account validation and token signing leave no
previous session. Use `Manager::resetSession()` when explicitly clearing a user
context outside the request middleware.

#### Explicit JWT configuration

`FactoryDefault` no longer creates a parser with a default signing key. Supply a
configured `TokenParserInterface` to its constructor, or register the
`tokenParser` service before it is first used. Resolving an unconfigured parser
throws a configuration exception.

```php
use PhalconApi\Auth\TokenParsers\JWTTokenParser;
use PhalconApi\Di\FactoryDefault;

$parser = new JWTTokenParser(
    secret: getenv('API_JWT_SECRET') ?: '',
    algorithm: JWTTokenParser::ALGORITHM_HS256,
    audience: getenv('API_JWT_AUDIENCE') ?: '',
    issuer: getenv('API_JWT_ISSUER') ?: ''
);
$di = new FactoryDefault($parser);
```

Use a separately generated random secret for each trust domain. Minimum HMAC key
lengths are 32 bytes for HS256, 48 for HS384 and 64 for HS512. RS256 accepts RSA
private or public PEM keys of at least 2048 bits; issuance requires the private
key. Other algorithms, empty keys and `this_should_be_changed` are rejected at
parser construction and by the key/algorithm setters.

Audience and issuer are required, nonempty configuration values. Issued JWTs use
`iss` for the configured issuer, `aud` for the intended API and `account_type`
for the internal account type formerly stored in `iss`. Validation requires the
exact issuer, the configured audience (a string or an audience array containing
it), account type, identity, issuance time and expiration, in addition to the
JWT library's signature and time checks. An account must still authenticate the
session identity.

Update every token producer to this claim format, invalidate old tokens and
require users to authenticate again. Replace any deployed default key and never
accept it as a rotation fallback. Deployments must configure their own audience,
issuer and keys; library changes alone do not update consuming projects.

Use `firebase/php-jwt ^7.2.1` in consuming applications. Version 7 introduced
[key size validation](https://github.com/googleapis/php-jwt/releases/tag/v7.0.0).
The library also validates supported key configurations at initialization.

### Validation

With PHP 8.4+ and Phalcon installed:

```sh
composer install
composer test
```

Composer installs JWT as a development dependency for the tests. The package
remains optional for non-JWT consumers. For a source-only comparison run, point
`JWT_SOURCE_DIR` at the `src` directory of an unpacked package:

```sh
JWT_SOURCE_DIR=/absolute/path/php-jwt/src php tests/security.php
```

The tests execute real routes and verify handler execution counts, deny
precedence, duplicate registration, method isolation, reused-service session
reset, JWT configuration, key strength, audience/issuer validation and valid
login/token flows. They run directly against the library without compatibility
shims. The suite was checked on PHP 8.4.26 / Phalcon 5.22.1 with JWT 6.8.1 and
7.2.1; JWT 6.8.1 is a regression comparison, not the recommended deployment
version. All 82 regression checks passed with each JWT version. PHP syntax
checks passed for all 36 source/test files, and the freshly resolved JWT 7.2.1
lockfile passed `composer audit --locked` without vulnerability advisories.
Other supported Phalcon releases were not runtime-tested here.

### Known limitations

URL-token handling (API-06), public exception messages (API-07) and additional
application configuration risks remain separate follow-up work. This release
does not claim to resolve every finding from the security scan.

The existing `CorsMiddleware` constructor still emits PHP 8.4 implicit-nullability
deprecation notices during syntax checks. Keep diagnostic output out of public
production responses. The JWT 6.8.1 comparison also emits deprecation notices;
use the recommended JWT 7.2.1 dependency for deployment.

`phalcon-graphql` 9.0.0 currently requires `phalcon-api ^6.0.0`, so it cannot use
this release without a separately tested phalcon-graphql dependency update.

[7.0.0]: https://github.com/redound/phalcon-api/compare/v6.0.0...v7.0.0
