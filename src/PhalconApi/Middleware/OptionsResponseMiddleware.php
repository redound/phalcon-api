<?php

namespace PhalconApi\Middleware;

use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use PhalconApi\Mvc\DiInjectable;

class OptionsResponseMiddleware extends DiInjectable implements MiddlewareInterface
{
    public function beforeHandleRoute()
    {
        // OPTIONS request, just send the headers and respond OK
        if ($this->request->isOptions()) {

            $this->response->setJsonContent([
                'result' => 'OK',
            ]);

            $this->response->send();

            return false;
        }
    }

    public function call(Micro $api)
    {
        return true;
    }
}