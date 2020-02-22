<?php

namespace PhalconApi\Mvc;

/**
 * @property \PhalconApi\Api $application
 * @property \PhalconApi\Http\Request $request
 * @property \PhalconApi\Http\Response $response
 * @property \Phalcon\Acl\Adapter\AdapterInterface $acl
 * @property \PhalconApi\Auth\Manager $authManager
 * @property \PhalconApi\User\Service $userService
 * @property \PhalconApi\Helpers\ErrorHelper $errorHelper
 * @property \PhalconApi\Helpers\FormatHelper $formatHelper
 * @property \PhalconApi\Auth\TokenParserInterface $tokenParser
 * @property \PhalconApi\Data\Query $query
 * @property \PhalconApi\Data\Query\QueryParsers\UrlQueryParser $urlQueryParser
 */

class Controller extends \Phalcon\Mvc\Controller
{

}