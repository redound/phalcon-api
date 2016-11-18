<?php

namespace PhalconApi\Middleware;

use Phalcon\Events\Event;
use PhalconApi\Constants\ErrorCodes;
use PhalconApi\Exception;

class NotFoundMiddleware extends \PhalconApi\Mvc\Plugin
{
    public function beforeNotFound(Event $event, \PhalconApi\Api $api)
    {
        throw new Exception(ErrorCodes::GENERAL_NOT_FOUND);
    }
}
