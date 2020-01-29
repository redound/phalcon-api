<?php

namespace PhalconApi\Middleware;

use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use PhalconApi\Constants\ErrorCodes;
use PhalconApi\Exception;
use PhalconApi\Mvc\DiInjectable;

class NotFoundMiddleware extends DiInjectable implements MiddlewareInterface
{
    public function beforeNotFound()
    {
        throw new Exception(ErrorCodes::GENERAL_NOT_FOUND);
    }

    public function call(Micro $api) {

        return true;
    }
}
