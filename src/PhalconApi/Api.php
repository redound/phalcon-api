<?php

namespace PhalconApi;

use Phalcon\Acl\Enum as AclEnum;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\CollectionInterface;
use Phalcon\Mvc\Router\RouteInterface;
use PhalconApi\Mvc\Collection;
use PhalconApi\Constants\Services;
use PhalconApi\Acl\MountableInterface;

/**
 * Class Api
 * @package PhalconApi
 *
 * @property \PhalconApi\Api $application
 * @property \PhalconApi\Http\Request $request
 * @property \PhalconApi\Http\Response $response
 * @property \Phalcon\Acl\Adapter\AdapterInterface $acl
 * @property \PhalconApi\Auth\Manager $authManager
 * @property \PhalconApi\User\Service $userService
 * @property \PhalconApi\Auth\TokenParserInterface $tokenParser
 * @property \PhalconApi\Data\Query $query
 * @property \PhalconApi\Data\Query\QueryParsers\UrlQueryParser $urlQueryParser
 */
class Api extends Micro implements MountableInterface
{
    protected $_collections = [];
    protected $_routeResources = [];

    /**
     * Attaches middleware to the API
     *
     * @param $middleware
     *
     * @return static
     */
    public function attach($middleware)
    {
        if (!$this->getEventsManager()) {
            $this->setEventsManager($this->getDI()->get(Services::EVENTS_MANAGER));
        }

        $this->getEventsManager()->attach('micro', $middleware);

        return $this;
    }

    public function mount(CollectionInterface $collection): static
    {
        if (in_array($collection, $this->_collections, true)) {
            throw new \InvalidArgumentException('A collection can only be mounted once');
        }

        $names = [];

        foreach ($this->getRouter()->getRoutes() as $route) {
            if ($route->getName() !== null) {
                $names[$route->getName()] = true;
            }
        }

        foreach ($collection->getHandlers() as $handler) {
            $name = $handler[3];

            if ($name !== null) {
                if (isset($names[$name])) {
                    throw new \InvalidArgumentException('Duplicate route name "' . $name . '"');
                }

                $names[$name] = true;
            }
        }

        $routeCount = count($this->getRouter()->getRoutes());
        parent::mount($collection);

        if ($collection instanceof Collection) {
            $routes = array_slice($this->getRouter()->getRoutes(), $routeCount);

            foreach ($routes as $index => $route) {
                $this->_routeResources[$route->getRouteId()] = $collection->getAclResource($index);
            }
        }

        $this->_collections[] = $collection;

        return $this;
    }

    public function getRouteAclResource(RouteInterface $route): string
    {
        return $this->_routeResources[$route->getRouteId()] ?? $route->getPattern();
    }

    public function getAclResources()
    {
        $resources = [];

        foreach ($this->_collections as $collection){

            if($collection instanceof MountableInterface){
                $resources = array_merge($resources, $collection->getAclResources());
            }
        }

        return $resources;
    }

    public function getAclRules(array $roles)
    {
        $allowed = [];
        $denied = [];

        foreach ($this->_collections as $collection){

            if($collection instanceof MountableInterface){

                $rules = $collection->getAclRules($roles);
                $allowed = array_merge($allowed, $rules[AclEnum::ALLOW]);
                $denied = array_merge($denied, $rules[AclEnum::DENY]);
            }
        }

        return [
            AclEnum::ALLOW => $allowed,
            AclEnum::DENY => $denied
        ];
    }
}
