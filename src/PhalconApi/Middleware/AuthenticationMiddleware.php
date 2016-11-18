<?php

namespace PhalconApi\Middleware;

use Phalcon\Events\Event;

class AuthenticationMiddleware extends \PhalconApi\Mvc\Plugin
{
    public function beforeExecuteRoute(Event $event, \PhalconApi\Api $api)
    {
        $token = $this->request->getToken();

        if ($token) {
            $this->authManager->authenticateToken($token);
        }
    }
}