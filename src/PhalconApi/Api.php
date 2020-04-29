<?php

namespace PhalconApi;

use Phalcon\Acl\Enum as AclEnum;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\CollectionInterface;
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

    public function mount(CollectionInterface $collection): Micro
    {
        $this->_collections[] = $collection;
        return parent::mount($collection);
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
