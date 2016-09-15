<?php
namespace Friday\Web;

use Friday\Base\BaseObject;
use Friday\Base\EventTrait;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 */
class ConnectionContext extends BaseObject {

    use EventTrait;

    /**
     * @var Request
     */
    public $_request;

    /**
     * @var Response
     */
    public $_response;

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

}