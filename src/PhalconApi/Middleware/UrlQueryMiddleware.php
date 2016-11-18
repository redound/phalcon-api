<?php

namespace PhalconApi\Middleware;

use PhalconApi\Constants\Services;

class UrlQueryMiddleware extends \PhalconApi\Mvc\Plugin
{
    public function beforeExecuteRoute(\Phalcon\Events\Event $event, \PhalconApi\Api $api)
    {
        $params = $this->getDI()->get(Services::REQUEST)->getQuery();
        $query = $this->getDI()->get(Services::URL_QUERY_PARSER)->createQuery($params);

        $this->getDI()->get(Services::QUERY)->merge($query);
    }
}