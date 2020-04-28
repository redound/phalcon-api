<?php

namespace PhalconApi\Mvc;

use Phalcon\Acl\Enum;
use PhalconApi\Acl\MountableInterface;
use PhalconApi\Http\Request;

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


    public function getAclResources()
    {
        $resources = [];

        foreach($this->handlers as $handler){

            $methods = $handler[0];
            if(!is_array($methods)){
                $methods = [$methods];
            }

            $path = $this->getFullPath($handler[1]);
            $resources[] = [$path, $methods];

            $name = $handler[3];
            if($name){
                $resources[] = [$name, $methods];
            }
        }

        return $resources;
    }

    public function getAclRules(array $roles)
    {
        $resources = $this->getAclResources();

        $allowed = [];
        $denied = [];

        // Allow
        foreach($this->_allowedRoles as $role){

            foreach($resources as $resource) {
                $allowed[] = [$role, $resource[0], '*'];
            }
        }

        foreach($this->_allowedNameRules as $rule){

            list($role, $name, $methods) = $rule;
            $allowed[] = [$role, $name, $methods ?: '*'];
        }

        foreach ($this->_allowedPathRules as $rule) {

            list($role, $path, $methods) = $rule;
            $allowed[] = [$role, $this->getFullPath($path), $methods ?: '*'];
        }

        // Deny
        foreach($this->_deniedRoles as $role){

            foreach($resources as $resource) {
                $denied[] = [$role, $resource[0], '*'];
            }
        }

        foreach($this->_deniedNameRules as $rule){

            list($role, $name, $methods) = $rule;
            $denied[] = [$role, $name, $methods ?: '*'];
        }

        foreach ($this->_deniedPathRules as $rule) {

            list($role, $path, $methods) = $rule;
            $denied[] = [$role, $this->getFullPath($path), $methods ?: '*'];
        }

        return [
            Enum::ALLOW => $allowed,
            Enum::DENY => $denied
        ];
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