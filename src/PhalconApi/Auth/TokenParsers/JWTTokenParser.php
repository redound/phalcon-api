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
        protected string $algorithm = self::ALGORITHM_HS256
    ) {
        if (!class_exists('\Firebase\JWT\JWT')) {
            throw new Exception(ErrorCodes::GENERAL_SYSTEM, 'JWT class is needed for the JWT token parser');
        }
    }

    public function setAlgorithm($algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function setSecret($secret): void
    {
        $this->secret = $secret;
    }


    public function getToken(Session $session, $expirationTime = null): string
    {
        $tokenData = $this->create($session->getAccountTypeName(), $session->getIdentity(), $session->getStartTime(),
            $session->getExpirationTime());

        return $this->encode($tokenData);
    }

    protected function create($issuer, $user, $iat, $exp): array
    {
        return [
            "iss" => $issuer,
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

        return new Session($tokenData->iss, $tokenData->sub, $tokenData->iat, $tokenData->exp, $token);
    }

    public function decode($token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }
}
