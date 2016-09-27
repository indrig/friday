<?php
namespace Friday\Base;

use Throwable;

class Deferred {
    private $awaitable;
    private $resultCallback;
    private $exceptionCallback;

    public function awaitable()
    {
        if (null === $this->awaitable) {
            $this->awaitable = new Awaitable(function ($resultCallback, $exceptionCallback) {
                $this->resultCallback       = $resultCallback;
                $this->exceptionCallback    = $exceptionCallback;
            });
        }

        return $this->awaitable;
    }


    /**
     *
     */
    public function result($value = null)
    {
        $this->awaitable();

        call_user_func($this->resultCallback, $value);
    }

    /**
     * @param null $throwable
     */
    public function exception($throwable)
    {
        $this->awaitable();

        call_user_func($this->exceptionCallback, $throwable);
    }

    /**
     * @param null $valueOrThrowable
     */
    public function proxy($valueOrThrowable = null)
    {
        if($valueOrThrowable instanceof Throwable){
            $this->awaitable();

            call_user_func($this->exceptionCallback, $valueOrThrowable);
        } else {
            $this->awaitable();

            call_user_func($this->resultCallback, $valueOrThrowable);
        }

    }
}