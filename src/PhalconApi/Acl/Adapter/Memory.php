<?php

namespace PhalconApi\Acl\Adapter;

use PhalconApi\Acl\MountingEnabledAdapterInterface;

class Memory extends \Phalcon\Acl\Adapter\Memory implements MountingEnabledAdapterInterface
{
    use \AclAdapterMountTrait;
}