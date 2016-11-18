<?php

namespace PhalconApi\Http;

use PhalconApi\Constants\PostedDataMethods;

class Request extends \Phalcon\Http\Request
{
    /**
     * Returns auth username
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->getServer('PHP_AUTH_USER');
    }

    /**
     * Returns auth password
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->getServer('PHP_AUTH_PW');
    }

    /**
     * Returns the data posted by the client. By default this method returns JSON raw body, post or query parameters.
     * Override this method to provide data from another source.
     *
     * @param $method string The method to use (see PostedDataMethods)
     * @return mixed
     */
    public function getPostedData($method=PostedDataMethods::AUTO)
    {
        if($method == PostedDataMethods::JSON_BODY){
            return $this->getJsonRawBody(true);
        }
        else if($method == PostedDataMethods::POST){
            return $this->getPost();
        }
        else if($method == PostedDataMethods::AUTO){

            if ($this->getContentType() === 'application/json') {
                return $this->getJsonRawBody(true);
            }
            else if($this->getPost()){
                return $this->getPost();
            }
            else {
                return $this->getQuery();
            }
        }

        return null;
    }

    /**
     * Returns token from the request.
     * Uses token URL query field, or Authorization header
     *
     * @return string|null
     */
    public function getToken()
    {
        $authHeader = $this->getHeader('AUTHORIZATION');
        $authQuery = $this->getQuery('token');

        return ($authQuery ? $authQuery : $this->parseBearerValue($authHeader));
    }

    protected function parseBearerValue($string)
    {
        if (strpos(trim($string), 'Bearer') !== 0) {
            return null;
        }

        return preg_replace('/.*\s/', '', $string);
    }
}
