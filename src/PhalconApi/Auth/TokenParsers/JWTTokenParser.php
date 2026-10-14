<?php

namespace PhalconApi\Auth\TokenParsers;

use PhalconApi\Auth\Session;
use PhalconApi\Constants\ErrorCodes;
use PhalconApi\Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTTokenParser implements \PhalconApi\Auth\TokenParserInterface
{
    const ALGORITHM_HS256 = 'HS256';
    const ALGORITHM_HS512 = 'HS512';
    const ALGORITHM_HS384 = 'HS384';
    const ALGORITHM_RS256 = 'RS256';

    public function __construct(
        protected string $secret,
        protected string $algorithm = self::ALGORITHM_HS256,
        protected ?string $audience = null,
        protected ?string $issuer = null
    ) {
        $this->validateKey($secret, $algorithm);

        if ($audience === null || trim($audience) === '' || $issuer === null || trim($issuer) === '') {
            throw new \InvalidArgumentException('JWT audience and issuer must be explicitly configured');
        }

        if (!class_exists('\Firebase\JWT\JWT')) {
            throw new Exception(ErrorCodes::GENERAL_SYSTEM, 'JWT class is needed for the JWT token parser');
        }
    }

    public function setAlgorithm($algorithm): void
    {
        $this->validateKey($this->secret, $algorithm);
        $this->algorithm = $algorithm;
    }

    public function setSecret($secret): void
    {
        $this->validateKey($secret, $this->algorithm);
        $this->secret = $secret;
    }


    public function getToken(Session $session, $expirationTime = null): string
    {
        $tokenData = $this->create($session->getAccountTypeName(), $session->getIdentity(), $session->getStartTime(),
            $session->getExpirationTime());

        return $this->encode($tokenData);
    }

    protected function create($accountType, $user, $iat, $exp): array
    {
        return [
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "account_type" => $accountType,
            "sub" => $user,
            "iat" => $iat,
            "exp" => $exp,
        ];
    }

    public function encode($token): string
    {
        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    public function getSession($token): Session
    {
        $tokenData = $this->decode($token);

        return new Session($tokenData->account_type, $tokenData->sub, $tokenData->iat, $tokenData->exp, $token);
    }

    public function decode($token): object
    {
        $tokenData = JWT::decode($token, new Key($this->secret, $this->algorithm));
        $audiences = $tokenData->aud ?? null;

        if (!is_string($audiences) && !is_array($audiences)) {
            throw new Exception(ErrorCodes::AUTH_TOKEN_INVALID);
        }

        if (($tokenData->iss ?? null) !== $this->issuer
            || !in_array($this->audience, (array) $audiences, true)) {
            throw new Exception(ErrorCodes::AUTH_TOKEN_INVALID);
        }

        if (!isset($tokenData->account_type, $tokenData->sub, $tokenData->iat, $tokenData->exp)
            || !is_string($tokenData->account_type) || $tokenData->account_type === ''
            || (!is_string($tokenData->sub) && !is_int($tokenData->sub))
            || !is_int($tokenData->iat) || !is_int($tokenData->exp)) {
            throw new Exception(ErrorCodes::AUTH_TOKEN_INVALID);
        }

        return $tokenData;
    }

    protected function validateKey(string $secret, string $algorithm): void
    {
        if ($secret === '' || $secret === 'this_should_be_changed') {
            throw new \InvalidArgumentException('An explicit JWT key is required');
        }

        $minimumLengths = [
            self::ALGORITHM_HS256 => 32,
            self::ALGORITHM_HS384 => 48,
            self::ALGORITHM_HS512 => 64
        ];

        if (isset($minimumLengths[$algorithm])) {
            if (strlen($secret) < $minimumLengths[$algorithm]) {
                throw new \InvalidArgumentException('JWT HMAC key is too short for ' . $algorithm);
            }

            return;
        }

        if ($algorithm !== self::ALGORITHM_RS256) {
            throw new \InvalidArgumentException('Unsupported JWT algorithm');
        }

        $key = openssl_pkey_get_private($secret);
        if ($key === false) {
            $key = openssl_pkey_get_public($secret);
        }

        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!$details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
            throw new \InvalidArgumentException('JWT RS256 requires an RSA key of at least 2048 bits');
        }
    }
}
