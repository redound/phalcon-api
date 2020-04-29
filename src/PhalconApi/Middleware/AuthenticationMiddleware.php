<?php

namespace PhalconApi\Middleware;

use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use PhalconApi\Mvc\DiInjectable;

class AuthenticationMiddleware extends DiInjectable implements MiddlewareInterface
{
    public function beforeHandleRoute()
    {
        $token = $this->request->getToken();

        if ($token) {
            $this->authManager->authenticateToken($token);
        }
    }

    public function call(Micro $api)
    {
        return true;
    }
}