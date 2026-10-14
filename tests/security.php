<?php

use Firebase\JWT\JWT;
use Phalcon\Di\Di;
use PhalconApi\Api;
use PhalconApi\Auth\AccountType;
use PhalconApi\Auth\Session;
use PhalconApi\Auth\TokenParsers\JWTTokenParser;
use PhalconApi\Constants\ErrorCodes;
use PhalconApi\Di\FactoryDefault;
use PhalconApi\Middleware\AuthenticationMiddleware;
use PhalconApi\Middleware\AuthorizationMiddleware;
use PhalconApi\Mvc\Collection;

$jwtSource = getenv('JWT_SOURCE_DIR');
if (!$jwtSource && is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(function ($class) use ($jwtSource) {
    $prefixes = ['PhalconApi\\' => __DIR__ . '/../src/PhalconApi/'];
    if ($jwtSource) {
        $prefixes['Firebase\\JWT\\'] = rtrim($jwtSource, '/') . '/';
    }

    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            require $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            return;
        }
    }

    if ($class === 'AclAdapterMountTrait') {
        require __DIR__ . '/../src/PhalconApi/Acl/AclAdapterMountTrait.php';
    }
});

if (!extension_loaded('phalcon') || !class_exists(JWT::class)) {
    throw new RuntimeException('Tests require Phalcon and firebase/php-jwt (Composer or JWT_SOURCE_DIR)');
}

const TEST_KEY = 'test-only-signing-key-with-at-least-64-bytes-never-use-in-production';
const TEST_AUDIENCE = 'security-test-api';
const TEST_ISSUER = 'security-test-issuer';

class SecurityTestUser extends PhalconApi\User\Service
{
    public function __construct(public ?string $role = 'member')
    {
    }

    public function getRole()
    {
        return $this->role ?? ($this->getIdentity() === '42' ? 'admin' : 'guest');
    }
}

class SecurityTestHandler
{
    public int $executions = 0;

    public function secret(...$arguments)
    {
        ++$this->executions;

        return 'protected-result';
    }
}

function parser(): JWTTokenParser
{
    return new JWTTokenParser(TEST_KEY, JWTTokenParser::ALGORITHM_HS256, TEST_AUDIENCE, TEST_ISSUER);
}

function environment(?string $role = 'member'): array
{
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);

    $di = new FactoryDefault(parser());
    Di::setDefault($di);
    $user = new SecurityTestUser($role);
    $user->setDI($di);
    $di->setShared('userService', $user);

    foreach (['member', 'admin', 'guest'] as $name) {
        $di->get('acl')->addRole($name);
    }

    return [$di, new Api($di), new SecurityTestHandler()];
}

function attach($app, $di, $middleware): void
{
    $middleware->setDI($di);
    $app->attach($middleware);
}

function invoke($app, string $uri, string $method = 'GET'): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    ob_start();

    try {
        return ['result' => $app->handle($uri)];
    }
    catch (Throwable $e) {
        return ['exception' => get_class($e), 'code' => $e->getCode()];
    }
    finally {
        ob_end_clean();
    }
}

$checks = 0;
function check(string $name, bool $condition): void
{
    global $checks;
    ++$checks;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $name);
    }

    echo 'PASS: ', $name, "\n";
}

function rejects(callable $operation, string $exception, ?int $code = null): bool
{
    try {
        $operation();
    }
    catch (Throwable $e) {
        return $e instanceof $exception && ($code === null || $e->getCode() === $code);
    }

    return false;
}

function account(): AccountType
{
    return new class implements AccountType {
        public function login($data)
        {
            if ($data['throw'] ?? false) {
                throw new RuntimeException('Account login failed');
            }

            return ($data['valid'] ?? false) ? '42' : null;
        }

        public function authenticate(Session $session)
        {
            return $session->getIdentity() === '42';
        }
    };
}

function claims(): array
{
    return [
        'iss' => TEST_ISSUER,
        'aud' => TEST_AUDIENCE,
        'account_type' => 'accounts',
        'sub' => '42',
        'iat' => time(),
        'exp' => time() + 600
    ];
}

foreach (['no-grant', 'collection-allow', 'path-allow', 'name-allow', 'collection-deny-path',
    'collection-deny-name', 'path-allow-name-deny', 'name-allow-path-deny', 'both-denied',
    'collection-deny-name-allow', 'wildcard-role-deny', 'wildcard-method-deny',
    'wildcard-name-deny', 'wildcard-path-deny', 'wildcard-allow', 'method-deny'] as $mode) {
    [$di, $app, $handler] = environment();
    $collection = new Collection();
    $collection->setPrefix('/api')->setHandler($handler)->get('/secret', 'secret', 'secretRoute');

    switch ($mode) {
        case 'collection-allow':
            $collection->allow('member');
            break;
        case 'path-allow':
            $collection->allowPath('/secret', 'member', 'GET');
            break;
        case 'name-allow':
            $collection->allowName('secretRoute', 'member', 'GET');
            break;
        case 'collection-deny-path':
            $collection->allow('member')->denyPath('/secret', 'member', 'GET');
            break;
        case 'collection-deny-name':
            $collection->allow('member')->denyName('secretRoute', 'member', 'GET');
            break;
        case 'path-allow-name-deny':
            $collection->allowPath('/secret', 'member', 'GET')->denyName('secretRoute', 'member', 'GET');
            break;
        case 'name-allow-path-deny':
            $collection->allowName('secretRoute', 'member', 'GET')->denyPath('/secret', 'member', 'GET');
            break;
        case 'both-denied':
            $collection->allow('member')->denyPath('/secret', 'member', 'GET')->denyName('secretRoute', 'member', 'GET');
            break;
        case 'collection-deny-name-allow':
            $collection->deny('member')->allowName('secretRoute', 'member', 'GET');
            break;
        case 'wildcard-role-deny':
            $collection->allow('member')->denyPath('/secret', '*', 'GET');
            break;
        case 'wildcard-method-deny':
            $collection->allowPath('/secret', 'member', 'GET')->denyName('secretRoute', 'member', '*');
            break;
        case 'wildcard-name-deny':
            $collection->allow('member')->denyName('*', 'member');
            break;
        case 'wildcard-path-deny':
            $collection->allowName('secretRoute', 'member')->denyPath('*', 'member');
            break;
        case 'wildcard-allow':
            $collection->allow('*');
            break;
        case 'method-deny':
            $collection->allow('member')->denyName('secretRoute', 'member', 'DELETE');
            break;
    }

    $app->mount($collection);
    attach($app, $di, new AuthorizationMiddleware());
    $di->get('acl')->mount($app);
    $response = invoke($app, '/api/secret');
    $allowed = in_array($mode, ['collection-allow', 'path-allow', 'name-allow', 'wildcard-allow', 'method-deny'], true);
    check('API-01 ' . $mode, $allowed
        ? ($response['result'] ?? null) === 'protected-result' && $handler->executions === 1
        : ($response['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 0);
}

[$di, $app, $handler] = environment();
$public = new Collection();
$public->setHandler($handler)->get('/public', 'secret', 'sharedName')->allowName('sharedName', 'member');
$private = new Collection();
$private->setHandler($handler)->get('/private', 'secret', 'sharedName')->allow('admin');
$app->mount($public);
$routeCount = count($app->getRouter()->getRoutes());
check('API-02 duplicate names across collections rejected', rejects(
    fn() => $app->mount($private), InvalidArgumentException::class
));
check('API-02 failed registration adds no routes', count($app->getRouter()->getRoutes()) === $routeCount);
$duplicate = new Collection();
$duplicate->setHandler($handler)->get('/one', 'secret', 'duplicate')->get('/two', 'secret', 'duplicate');
check('API-02 duplicates within a collection rejected', rejects(
    fn() => $app->mount($duplicate), InvalidArgumentException::class
));

$private = new Collection();
$private->setHandler($handler)->get('/private', 'secret', 'privateName')->allow('admin');
$app->mount($private);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
check('API-02 public route works', (invoke($app, '/public')['result'] ?? null) === 'protected-result');
check('API-02 public name grant cannot open private route',
    (invoke($app, '/private')['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 1);

[$di, $app, $handler] = environment();
$get = new Collection();
$get->setHandler($handler)->get('/method', 'secret', 'getMethod')->allowName('getMethod', 'member');
$delete = new Collection();
$delete->setHandler($handler)->delete('/method', 'secret', 'deleteMethod')->allow('admin');
$app->mount($get)->mount($delete);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mountMany([$get, $delete]);
check('API-02 distinct routes have distinct ACL resources', $get->getAclResource(0) !== $delete->getAclResource(0));
check('GET grant works on shared path', (invoke($app, '/method')['result'] ?? null) === 'protected-result');
check('GET grant cannot open DELETE route',
    (invoke($app, '/method', 'DELETE')['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 1);

[$di, $app, $handler] = environment();
$collection = new Collection();
$collection->setPrefix('/api')->setHandler($handler)->get('/record/{id}', 'secret')->allowPath('/record/{id}', 'member');
$app->mount($collection);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
check('Parameterized route keeps its canonical ACL resource',
    (invoke($app, '/api/record/42')['result'] ?? null) === 'protected-result');

[$di, $app, $handler] = environment();
$collection = new Collection();
$collection->setHandler($handler)->map('/mapped', 'secret')->allowPath('/mapped', 'member', ['GET', 'POST']);
$collection->denyPath('/mapped', 'member', ['POST']);
$app->mount($collection);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
check('Method arrays allow GET on mapped route', (invoke($app, '/mapped')['result'] ?? null) === 'protected-result');
check('Method arrays deny POST on mapped route',
    (invoke($app, '/mapped', 'POST')['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 1);
check('Unconfigured method on mapped route denied',
    (invoke($app, '/mapped', 'DELETE')['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 1);
check('API-02 same collection cannot be mounted twice', rejects(
    fn() => $app->mount($collection), InvalidArgumentException::class
));

foreach (['unknown-name', 'unknown-path', 'unknown-role'] as $case) {
    [$di, $app, $handler] = environment();
    $collection = new Collection();
    $collection->setHandler($handler)->get('/known', 'secret', 'knownName')->allow('member');

    switch ($case) {
        case 'unknown-name':
            $collection->denyName('typoName', 'member');
            break;
        case 'unknown-path':
            $collection->denyPath('/typo', 'member');
            break;
        case 'unknown-role':
            $collection->deny('typoRole');
            break;
    }

    $app->mount($collection);
    check('Invalid ACL configuration fails before mounting permissions: ' . $case, rejects(
        fn() => $di->get('acl')->mount($app), InvalidArgumentException::class
    ));
}

[$di, $app, $handler] = environment('inherited');
$di->get('acl')->addRole('inherited', 'member');
$collection = new Collection();
$collection->setHandler($handler)->get('/inherited', 'secret')->allow('member');
$app->mount($collection);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
check('Existing role inheritance still grants access',
    (invoke($app, '/inherited')['result'] ?? null) === 'protected-result');

[$di, $app, $handler] = environment();
$public = new Collection();
$public->setHandler($handler)->get('/same-path', 'secret', 'publicSamePath')->allow('member');
$private = new Collection();
$private->setHandler($handler)->get('/same-path', 'secret', 'privateSamePath')->allow('admin');
$app->mount($public)->mount($private);
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
check('API-02 separate handlers on identical path and method keep separate rights',
    (invoke($app, '/same-path')['code'] ?? null) === ErrorCodes::ACCESS_DENIED && $handler->executions === 0);

[$di, $app, $handler] = environment(null);
$manager = $di->get('authManager');
$manager->registerAccountType('accounts', account());
$collection = new Collection();
$collection->setHandler($handler)->get('/admin', 'secret', 'adminRoute')->allow('admin');
$app->mount($collection);
attach($app, $di, new AuthenticationMiddleware());
attach($app, $di, new AuthorizationMiddleware());
$di->get('acl')->mount($app);
$token = JWT::encode(claims(), TEST_KEY, 'HS256');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
check('API-03 valid token reaches protected handler', (invoke($app, '/admin')['result'] ?? null) === 'protected-result');
unset($_SERVER['HTTP_AUTHORIZATION']);
check('API-03 reused DI without token has no previous identity',
    (invoke($app, '/admin')['code'] ?? null) === ErrorCodes::ACCESS_DENIED
    && !$manager->loggedIn() && $handler->executions === 1);
$manager->authenticateToken($token);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token';
check('API-03 invalid request clears previous identity',
    (invoke($app, '/admin')['code'] ?? null) === ErrorCodes::AUTH_TOKEN_INVALID
    && !$manager->loggedIn() && $handler->executions === 1);
$manager->authenticateToken($token);
check('API-03 direct invalid authentication clears previous identity',
    rejects(fn() => $manager->authenticateToken('invalid'), PhalconApi\Exception::class, ErrorCodes::AUTH_TOKEN_INVALID)
    && !$manager->loggedIn());

foreach (['invalid-password', 'unknown-account', 'account-exception'] as $case) {
    $manager->authenticateToken($token);
    $accountType = $case === 'unknown-account' ? 'unknown' : 'accounts';
    $data = ['valid' => false, 'throw' => $case === 'account-exception'];
    check('API-03 failed login clears identity: ' . $case,
        rejects(fn() => $manager->login($accountType, $data), Exception::class) && !$manager->loggedIn());
}

$session = $manager->login('accounts', ['valid' => true]);
check('Successful login issues verifiable token', $manager->authenticateToken($session->getToken()));
$manager->resetSession();
check('Explicit session reset clears identity', !$manager->loggedIn());
$emptyParser = new class implements PhalconApi\Auth\TokenParserInterface {
    public function getToken(Session $session)
    {
        return '';
    }

    public function getSession($token)
    {
        return null;
    }
};
$emptyDi = new FactoryDefault($emptyParser);
$emptyManager = $emptyDi->get('authManager');
$emptyManager->setSession(new Session('accounts', '42', time(), time() + 600));
check('API-03 null parser result clears identity', !$emptyManager->authenticateToken('empty') && !$emptyManager->loggedIn());
$di->setShared('tokenParser', parser());

$failingParser = new class implements PhalconApi\Auth\TokenParserInterface {
    public function getToken(Session $session)
    {
        throw new RuntimeException('Signing failed');
    }

    public function getSession($token)
    {
        throw new RuntimeException('Parsing failed');
    }
};
$failingDi = new FactoryDefault($failingParser);
$failingManager = $failingDi->get('authManager');
$failingManager->registerAccountType('accounts', account());
$failingManager->setSession(new Session('accounts', '42', time(), time() + 600));
check('API-03 token signing failure leaves no previous session', rejects(
    fn() => $failingManager->login('accounts', ['valid' => true]), RuntimeException::class
) && !$failingManager->loggedIn());

$unconfigured = new FactoryDefault();
check('API-04 missing parser configuration fails when resolved', rejects(
    fn() => $unconfigured->get('tokenParser'), PhalconApi\Exception::class, ErrorCodes::GENERAL_SYSTEM
));
foreach (['', 'this_should_be_changed', str_repeat('a', 31)] as $key) {
    check('API-04 unsuitable key rejected at initialization', rejects(
        fn() => new JWTTokenParser($key, 'HS256', TEST_AUDIENCE, TEST_ISSUER), InvalidArgumentException::class
    ));
}
foreach (['HS256' => 32, 'HS384' => 48, 'HS512' => 64] as $algorithm => $minimum) {
    check('API-04 short ' . $algorithm . ' key rejected', rejects(
        fn() => new JWTTokenParser(str_repeat('a', $minimum - 1), $algorithm, TEST_AUDIENCE, TEST_ISSUER),
        InvalidArgumentException::class
    ));
    $configured = new JWTTokenParser(str_repeat('a', $minimum), $algorithm, TEST_AUDIENCE, TEST_ISSUER);
    $session = new Session('accounts', '42', time(), time() + 600);
    check('API-04 suitable ' . $algorithm . ' key works', $configured->getSession($configured->getToken($session))->getIdentity() === '42');
}
$configured = parser();
check('API-04 secret setter rejects known default', rejects(
    fn() => $configured->setSecret('this_should_be_changed'), InvalidArgumentException::class
));
check('API-04 algorithm setter validates existing key', rejects(
    fn() => (new JWTTokenParser(str_repeat('a', 32), 'HS256', TEST_AUDIENCE, TEST_ISSUER))->setAlgorithm('HS512'),
    InvalidArgumentException::class
));
$header = JWT::urlsafeB64Encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
$body = JWT::urlsafeB64Encode(json_encode(claims()));
$forged = $header . '.' . $body . '.' . JWT::urlsafeB64Encode(hash_hmac('sha256', $header . '.' . $body, 'this_should_be_changed', true));
check('API-04 independently forged default-key token rejected', rejects(
    fn() => $configured->getSession($forged), Exception::class
));

foreach (['missing-audience', 'missing-issuer', 'empty-audience', 'empty-issuer'] as $case) {
    $audience = $case === 'missing-audience' ? null : ($case === 'empty-audience' ? '' : TEST_AUDIENCE);
    $issuer = $case === 'missing-issuer' ? null : ($case === 'empty-issuer' ? '' : TEST_ISSUER);
    check('API-05 explicit configuration required: ' . $case, rejects(
        fn() => new JWTTokenParser(TEST_KEY, 'HS256', $audience, $issuer), InvalidArgumentException::class
    ));
}

foreach (['foreign-audience', 'foreign-issuer', 'missing-audience', 'missing-issuer', 'missing-account-type',
    'unknown-account', 'unknown-identity', 'expired', 'future-iat', 'future-nbf', 'wrong-key',
    'wrong-algorithm', 'invalid-audience-type', 'missing-expiration', 'invalid-account-type'] as $case) {
    $data = claims();
    $key = TEST_KEY;
    $algorithm = 'HS256';

    switch ($case) {
        case 'foreign-audience':
            $data['aud'] = 'different-project';
            break;
        case 'foreign-issuer':
            $data['iss'] = 'different-issuer';
            break;
        case 'missing-audience':
            unset($data['aud']);
            break;
        case 'missing-issuer':
            unset($data['iss']);
            break;
        case 'missing-account-type':
            unset($data['account_type']);
            break;
        case 'unknown-account':
            $data['account_type'] = 'unknown';
            break;
        case 'unknown-identity':
            $data['sub'] = 'unknown';
            break;
        case 'expired':
            $data['exp'] = time() - 600;
            break;
        case 'future-iat':
            $data['iat'] = time() + 600;
            break;
        case 'future-nbf':
            $data['nbf'] = time() + 600;
            break;
        case 'wrong-key':
            $key .= '-other';
            break;
        case 'wrong-algorithm':
            $algorithm = 'HS384';
            break;
        case 'invalid-audience-type':
            $data['aud'] = 123;
            break;
        case 'missing-expiration':
            unset($data['exp']);
            break;
        case 'invalid-account-type':
            $data['account_type'] = ['accounts'];
            break;
    }

    $manager->authenticateToken($token);
    $invalid = JWT::encode($data, $key, $algorithm);
    check('API-05 token rejected and session cleared: ' . $case,
        rejects(fn() => $manager->authenticateToken($invalid), PhalconApi\Exception::class)
        && !$manager->loggedIn());
}

$data = claims();
$data['aud'] = ['other-api', TEST_AUDIENCE];
check('API-05 matching audience in array works', $manager->authenticateToken(JWT::encode($data, TEST_KEY, 'HS256')));
$data['aud'] = ['other-api'];
check('API-05 foreign audience array rejected', rejects(
    fn() => $manager->authenticateToken(JWT::encode($data, TEST_KEY, 'HS256')), PhalconApi\Exception::class
));
$none = JWT::urlsafeB64Encode(json_encode(['typ' => 'JWT', 'alg' => 'none'])) . '.' . $body . '.';
check('Unsigned token rejected', rejects(fn() => $configured->getSession($none), Exception::class));

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $privateKey);
$publicKey = openssl_pkey_get_details($key)['key'];
$signer = new JWTTokenParser($privateKey, 'RS256', TEST_AUDIENCE, TEST_ISSUER);
$verifier = new JWTTokenParser($publicKey, 'RS256', TEST_AUDIENCE, TEST_ISSUER);
check('Explicit RSA private/public configuration works',
    $verifier->getSession($signer->getToken(new Session('accounts', '42', time(), time() + 600)))->getIdentity() === '42');
$weakKey = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($weakKey, $weakPem);
check('Weak RSA key rejected at initialization', rejects(
    fn() => new JWTTokenParser($weakPem, 'RS256', TEST_AUDIENCE, TEST_ISSUER), InvalidArgumentException::class
));

echo $checks, ' checks passed; PHP ', PHP_VERSION, '; Phalcon ', phpversion('phalcon'), ".\n";
