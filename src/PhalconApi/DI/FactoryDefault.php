<?php

namespace PhalconApi\DI;

use PhalconApi\Constants\Services;

class FactoryDefault extends \Phalcon\Di\FactoryDefault
{
    public function __construct()
    {
        parent::__construct();

        $this->setShared(Services::REQUEST, new \PhalconApi\Http\Request);
        $this->setShared(Services::RESPONSE, new \PhalconApi\Http\Response);

        $this->setShared(Services::AUTH_MANAGER, new \PhalconApi\Auth\Manager);

        $this->setShared(Services::USER_SERVICE, new \PhalconApi\User\Service);

        $this->setShared(Services::TOKEN_PARSER, function () {

            return new \PhalconApi\Auth\TokenParsers\JWTTokenParser('this_should_be_changed');
        });

        $this->setShared(Services::QUERY, function () {

            return new \PhalconApi\Data\Query();
        });

        $this->setShared(Services::PHQL_QUERY_PARSER, function () {

            return new \PhalconApi\Data\Query\QueryParsers\PhqlQueryParser();
        });

        $this->setShared(Services::URL_QUERY_PARSER, function () {

            return new \PhalconApi\Data\Query\QueryParsers\UrlQueryParser();
        });

        $this->setShared(Services::ACL, new \PhalconApi\Acl\Adapter\Memory());
    }
}