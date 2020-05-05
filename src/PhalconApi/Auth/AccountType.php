<?php

namespace PhalconApi\Auth;

interface AccountType
{
    /**
     * @param array $data Login data
     *
     * @return string Identity
     */
    public function login($data);

    /**
     * @param Session $session Session
     *
     * @return bool Authentication successful
     */
    public function authenticate(Session $session);
}
