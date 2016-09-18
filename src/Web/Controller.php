<?php
namespace Friday\Web;

use Friday\Base\Controller as BaseController;

/**
 * Class Controller
 * @package Friday\Web
 *
 * @property ConnectionContext $connectionContext
 */
class Controller extends BaseController{
    protected $_connectionContext;

    public function getConnectionContext(){
        return $this->_connectionContext;
    }

    public function setConnectionContext($connectionContext){
        $this->_connectionContext = $connectionContext;
    }
}