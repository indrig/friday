<?php
namespace Friday\Web;

use Friday\Base\BaseObject;
use Friday\Base\EventTrait;
use Friday\Base\Exception\RuntimeException;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 * @property string|null $requestedRoute
 */
class ConnectionContext extends BaseObject {

    use EventTrait;

    /**
     * @var Request
     */
    private $_request;

    /**
     * @var Response
     */
    private $_response;

    /**
     * @var string|null
     */
    private $_requestedRoute;

    /**
     * @return Request
     */
    public function getRequest(){
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse(){
        return $this->_response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return static
     */
    public static function create(Request $request, Response $response){
        $context = new static();
        $context->_request  = $request;
        $context->_response = $response;
        return $context;
    }

    public function getRequestedRoute(){
        return $this->_requestedRoute;
    }

    public function setRequestedRoute($requestedRoute){
        if($this->_requestedRoute !== null) {
            throw new RuntimeException('User context parameter "requestedRoute" already set.');
        }
        $this->_requestedRoute = $requestedRoute;
        return $this;
    }
}