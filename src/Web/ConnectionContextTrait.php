<?php
namespace Friday\Web;

use Friday\Base\Exception\InvalidConfigException;

trait ConnectionContextTrait {
    /**
     * @var ConnectionContext
     */
    protected $_connectionContext;

    /**
     * @param ConnectionContext $connectionContext
     * @return static
     */
    public function setConnectionContext($connectionContext){
        $this->_connectionContext = $connectionContext;
        return $this;
    }

    /**
     * @return ConnectionContext
     */
    public function getConnectionContext(){
        return $this->_connectionContext;
    }

    /**
     * @return Request
     *
     * @throws InvalidConfigException
     */
    public function getRequest(){
        return $this->_connectionContext->getRequest();
    }

    /**
     * @return Response
     *
     * @throws InvalidConfigException
     */
    public function getResponse(){
        return $this->_connectionContext->getResponse();
    }

    /**
     * @return User
     *
     * @throws InvalidConfigException
     */
    public function getUser(){
        return $this->_connectionContext->getUser();
    }

    /**
     * @return Session
     *
     * @throws InvalidConfigException
     */
    public function getSession(){
        return $this->_connectionContext->getSession();
    }


    /**
     * @return AssetManager
     *
     * @throws InvalidConfigException
     */
    public function getAssetManager(){
        return $this->_connectionContext->getAssetsManager();
    }
}