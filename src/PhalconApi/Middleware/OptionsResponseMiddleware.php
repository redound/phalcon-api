<?php

namespace PhalconApi\Middleware;

use Phalcon\Events\Event;
use PhalconApi\Constants\HttpMethods;

class OptionsResponseMiddleware extends \PhalconApi\Mvc\Plugin
{
    public function beforeHandleRoute(Event $event, \PhalconApi\Api $api)
    {
        // OPTIONS request, just send the headers and respond OK
        if ($this->request->getMethod() == HttpMethods::OPTIONS) {

            $this->response->setJsonContent([
                'result' => 'OK',
            ]);

            return false;
        }
    }
}