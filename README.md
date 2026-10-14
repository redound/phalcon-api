# Phalcon API

Base library for API features in Phalcon.

## Security migration (API-01 through API-05)

These changes require application configuration and invalidate legacy JWTs.

### Route permissions

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

### Authentication lifecycle

The authentication middleware resets the current session at the beginning of
each request, including requests without a token. `Manager::login()` and
`Manager::authenticateToken()` also reset the session before attempting a new
identity. Failed authentication, account validation and token signing leave no
previous session. Use `Manager::resetSession()` when explicitly clearing a user
context outside the request middleware.

### Explicit JWT configuration

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

## Regression tests

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
version. Other supported Phalcon releases were not runtime-tested here.

The medium-priority findings API-06 (URL-token handling) and API-07 (public
exception messages), plus the report's additional configuration risks, remain
separate follow-up work.
