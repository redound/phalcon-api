<?php

namespace PhalconApi\Middleware;

use Phalcon\Mvc\Micro;
use PhalconApi\Constants\ErrorCodes;
use PhalconApi\Constants\Services;
use PhalconApi\Mvc\Collection;
use PhalconApi\Mvc\DiInjectable;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use PhalconApi\Exception;

class AuthorizationMiddleware extends DiInjectable implements MiddlewareInterface
{
    /**
     * @param $event
     * @param Micro $app
     *
     * @return bool
     */
    public function beforeExecuteRoute($event, $app){

        $request = $app->request;
        $userService = $app->di->get(Services::USER_SERVICE);
        $acl = $app->di->get(Services::ACL);

        $route = $app->getRouter()->getMatchedRoute();
        if(!$route){
            return true;
        }

        $role = $userService->getRole();
        $name = $route->getName();
        $path = $route->getPattern();
        $method = $request->getMethod();

        $allowedPath = $acl->isAllowed($role, $path, $method);

        $allowedName = false;
        if($name){
            $allowedName = $acl->isAllowed($role, $name, $method);
        }

        if (!$allowedPath && !$allowedName) {
            throw new Exception(ErrorCodes::ACCESS_DENIED, 'No access to path "' . $route->getPattern() . '"');
        }

        return true;
    }

    public function call(Micro $application)
    {
        return true;
    }
}