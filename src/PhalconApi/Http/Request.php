<?php

namespace PhalconApi\Http;

use PhalconApi\Constants\PostedDataMethods;

class Request extends \Phalcon\Http\Request
{
    protected $postedDataMethod = PostedDataMethods::AUTO;
    protected $cachedPostedData = null;

    /**
     * @param string $method One of the method constants defined in PostedDataMethods
     *
     * @return static
     */
    public function postedDataMethod($method)
    {
        $this->postedDataMethod = $method;
        return $this;
    }

    /**
     * Sets the posted data method to POST
     *
     * @return static
     */
    public function expectsPostData()
    {
        $this->postedDataMethod(PostedDataMethods::POST);
        return $this;
    }

    /**
     * Sets the posted data method to PUT
     *
     * @return static
     */
    public function expectsPutData()
    {
        $this->postedDataMethod(PostedDataMethods::PUT);
        return $this;
    }

    /**
     * Sets the posted data method to PUT
     *
     * @return static
     */
    public function expectsGetData()
    {
        $this->postedDataMethod(PostedDataMethods::GET);
        return $this;
    }

    /**
     * Sets the posted data method to JSON_BODY
     *
     * @return static
     */
    public function expectsJsonData()
    {
        $this->postedDataMethod(PostedDataMethods::JSON_BODY);
        return $this;
    }

    /**
     * @return string $method One of the method constants defined in PostedDataMethods
     */
    public function getPostedDataMethod()
    {
        return $this->postedDataMethod;
    }

    /**
     * Returns the data posted by the client. This method uses the set postedDataMethod to collect the data.
     *
     * @param $httpMethod string Method
     * @return mixed
     */
    public function getPostedData($httpMethod = null)
    {
        $method = $httpMethod !== null ? $httpMethod : $this->postedDataMethod;

        if($method == PostedDataMethods::AUTO){

            if (stristr($this->getContentType(), 'application/json') !== false) {
                $method = PostedDataMethods::JSON_BODY;
            }
            else if($this->isPost()){
                $method = PostedDataMethods::POST;
            }
            else if($this->isPut()){
                $method = PostedDataMethods::PUT;
            }
            else if($this->isGet()) {
                $method = PostedDataMethods::GET;
            }
        }

        if ($method == PostedDataMethods::JSON_BODY) {
            return $this->getJsonRawBody(true);
        }
        else if($method == PostedDataMethods::POST) {
            return $this->getPost();
        }
        else if($method == PostedDataMethods::PUT) {
            return $this->getPut();
        }
        else if($method == PostedDataMethods::GET) {
            return $this->getQuery();
        }

        return [];
    }

    /**
     * Gets a variable from the posted data (getPostedData) applying filters if needed
     * If no parameters are given the posted data is returned
     *
     * @param string $name
     * @param mixed $filters
     * @param mixed $defaultValue
     * @param bool $notAllowEmpty
     * @param bool $noRecursive
     * @return mixed
     */
    public function getPosted($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false){

        if(!$this->cachedPostedData){
            $this->cachedPostedData = $this->getPostedData();
        }

        if(!$this->cachedPostedData){
            return null;
        }

        return $this->getHelper($this->cachedPostedData, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }

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
     * Returns token from the request.
     * Uses token URL query field, or Authorization header
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        $authHeader = $this->getHeader('AUTHORIZATION');
        $authQuery = $this->getQuery('token');

        return $authQuery ? $authQuery : $this->parseBearerValue($authHeader);
    }

    protected function parseBearerValue(?string $string): ?string
    {
        if (!$string || strpos(trim($string), 'Bearer') !== 0) {
            return null;
        }

        return preg_replace('/.*\s/', '', $string);
    }
}
