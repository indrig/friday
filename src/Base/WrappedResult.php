<?php
namespace Friday\Base;

use Throwable;

class WrappedResult implements ResultOrExceptionWrapperInterface {
    protected $result;

    /**
     * WrappedResult constructor.
     * @param $result
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * @inheritdoc
     */
    public function isFailed(){
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isSucceeded() {
        return true;
    }

    /**
     * @return null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return null
     */
    public function getException()
    {
        return null;
    }
}