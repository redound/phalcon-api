<?php

namespace PhalconApi\Di;

use PhalconApi\Acl\Adapter\Memory as Acl;
use PhalconApi\Auth\Manager as AuthManager;
use PhalconApi\Auth\TokenParsers\JWTTokenParser;
use PhalconApi\Constants\Services;
use PhalconApi\Data\Query;
use PhalconApi\Data\Query\QueryParsers\UrlQueryParser;
use PhalconApi\Helpers\ErrorHelper;
use PhalconApi\Helpers\FormatHelper;
use PhalconApi\Http\Request;
use PhalconApi\Http\Response;
use PhalconApi\User\Service as UserService;

class FactoryDefault extends \Phalcon\Di\FactoryDefault
{
    public function __construct()
    {
        parent::__construct();

        $this->setShared(Services::REQUEST, new Request);
        $this->setShared(Services::RESPONSE, new Response);

        $this->setShared(Services::AUTH_MANAGER, new AuthManager);

        $this->setShared(Services::USER_SERVICE, new UserService);

        $this->setShared(Services::TOKEN_PARSER, function () {

            return new JWTTokenParser('this_should_be_changed');
        });

        $this->setShared(Services::QUERY, new Query);

        $this->setShared(Services::URL_QUERY_PARSER, new UrlQueryParser);

        $this->setShared(Services::ACL, new Acl);

        $this->setShared(Services::ERROR_HELPER, new ErrorHelper);

        $this->setShared(Services::FORMAT_HELPER, new FormatHelper);
    }
}
