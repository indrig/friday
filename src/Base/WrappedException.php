<?php
namespace Friday\Base;

use Throwable;

class WrappedException implements ResultOrExceptionWrapperInterface {
    protected $exception;

    public function __construct(Throwable $throwable)
    {
        $this->exception = $throwable;
    }

    /**
     * @inheritdoc
     */
    public function isFailed(){
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isSucceeded() {
        return false;
    }

    /**
     * @return null
     */
    public function getResult()
    {
        return null;
    }

    /**
     * @return null
     */
    public function getException()
    {
        return $this->exception;
    }
}