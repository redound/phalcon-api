<?php

namespace PhalconApi\Mvc;

use Phalcon\Acl\Enum;
use PhalconApi\Acl\MountableInterface;
use PhalconApi\Constants\HttpMethods;

class Collection extends \Phalcon\Mvc\Micro\Collection implements MountableInterface
{
    protected $_allowedRoles = [];
    protected $_deniedRoles = [];

    protected $_allowedNameRules = [];
    protected $_deniedNameRules = [];

    protected $_allowedPathRules = [];
    protected $_deniedPathRules = [];

    public function __construct() {
        $this->initialize();
    }

    protected function initialize(){}


    public function allow($roles)
    {
        $this->_allowedRoles = array_merge($this->_allowedRoles, is_array($roles) ? $roles : [$roles]);
        return $this;
    }

    public function deny($roles)
    {
        $this->_deniedRoles = array_merge($this->_deniedRoles, is_array($roles) ? $roles : [$roles]);
        return $this;
    }


    public function allowName($name, $roles, $methods=null)
    {
        $allRoles = is_array($roles) ? $roles : [$roles];

        foreach ($allRoles as $role){
            $this->_allowedNameRules[] = [$role, $name, $methods];
        }

        return $this;
    }

    public function denyName($name, $roles, $methods=null)
    {
        $allRoles = is_array($roles) ? $roles : [$roles];

        foreach ($allRoles as $role){
            $this->_deniedNameRules[] = [$role, $name, $methods];
        }

        return $this;
    }


    public function allowPath($path, $roles, $methods=null)
    {
        $allRoles = is_array($roles) ? $roles : [$roles];

        foreach ($allRoles as $role){
            $this->_allowedPathRules[] = [$role, $path, $methods];
        }

        return $this;
    }

    public function denyPath($path, $roles, $methods=null)
    {
        $allRoles = is_array($roles) ? $roles : [$roles];

        foreach ($allRoles as $role){
            $this->_deniedPathRules[] = [$role, $path, $methods];
        }

        return $this;
    }


    public function getAclResource(int $index): string
    {
        return 'route:' . spl_object_id($this) . ':' . $index;
    }

    public function getAclResources()
    {
        $resources = [];

        foreach ($this->handlers as $index => $handler) {
            $resources[] = [$this->getAclResource($index), $this->getAclMethods($handler)];
        }

        return $resources;
    }

    public function getAclRules(array $roles)
    {
        $allowed = [];
        $denied = [];
        $roleNames = [];

        foreach ($roles as $role) {
            $roleNames[] = is_object($role) ? $role->getName() : $role;
        }

        $this->validateAclRules($roleNames);

        foreach ($this->handlers as $index => $handler) {
            $resource = $this->getAclResource($index);

            foreach ($roleNames as $role) {
                foreach ($this->getAclMethods($handler) as $method) {
                    $allow = $this->matchesRoles($this->_allowedRoles, $role)
                        || $this->matchesRules($this->_allowedNameRules, $handler[3], $role, $method)
                        || $this->matchesRules($this->_allowedPathRules, $handler[1], $role, $method);
                    $deny = $this->matchesRoles($this->_deniedRoles, $role)
                        || $this->matchesRules($this->_deniedNameRules, $handler[3], $role, $method)
                        || $this->matchesRules($this->_deniedPathRules, $handler[1], $role, $method);

                    if ($deny) {
                        $denied[] = [$role, $resource, $method];
                    }
                    elseif ($allow) {
                        $allowed[] = [$role, $resource, $method];
                    }
                }
            }
        }

        return [
            Enum::ALLOW => $allowed,
            Enum::DENY => $denied
        ];
    }

    protected function getAclMethods(array $handler): array
    {
        $methods = $handler[0];

        if ($methods === null || $methods === '*') {
            $methods = HttpMethods::$ALL_METHODS;
        }
        elseif (!is_array($methods)) {
            $methods = [$methods];
        }

        return $methods;
    }

    protected function matchesRoles(array $roles, $role): bool
    {
        return in_array($role, $roles, true) || in_array('*', $roles, true);
    }

    protected function matchesRules(array $rules, $target, $role, $method): bool
    {
        if ($target === null) {
            return false;
        }

        foreach ($rules as $rule) {
            list($ruleRole, $ruleTarget, $methods) = $rule;

            if ($ruleRole !== $role && $ruleRole !== '*') {
                continue;
            }

            if ($ruleTarget !== $target && $ruleTarget !== '*') {
                continue;
            }

            if (!$methods || in_array('*', (array) $methods, true) || in_array($method, (array) $methods, true)) {
                return true;
            }
        }

        return false;
    }

    protected function validateAclRules(array $roles): void
    {
        $names = array_column($this->handlers, 3);
        $paths = array_column($this->handlers, 1);
        $configuredRoles = array_merge($this->_allowedRoles, $this->_deniedRoles);

        $ruleGroups = [
            [$this->_allowedNameRules, $names],
            [$this->_deniedNameRules, $names],
            [$this->_allowedPathRules, $paths],
            [$this->_deniedPathRules, $paths]
        ];

        foreach ($ruleGroups as [$rules, $targets]) {
            foreach ($rules as $rule) {
                $configuredRoles[] = $rule[0];

                if ($rule[1] !== '*' && !in_array($rule[1], $targets, true)) {
                    throw new \InvalidArgumentException('ACL rule references an unknown route "' . $rule[1] . '"');
                }
            }
        }

        foreach ($configuredRoles as $role) {
            if ($role !== '*' && !in_array($role, $roles, true)) {
                throw new \InvalidArgumentException('ACL rule references an unknown role "' . $role . '"');
            }
        }
    }

    protected function getFullPath($path){

        if($this->prefix){

            if($path == '/'){
                return $this->prefix;
            }
            else {
                return $this->prefix . $path;
            }
        }
        else {
            return $path;
        }
    }
}
