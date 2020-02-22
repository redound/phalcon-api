<?php

namespace PhalconApi\Mvc;

/**
 * PhalconApi\Mvc\DiInjectable
 * This class allows to access services in the services container by just only accessing a public property
 * with the same name of a registered service
 *
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

class DiInjectable extends \Phalcon\Di\Injectable
{

}