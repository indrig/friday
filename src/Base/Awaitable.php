<?php
namespace Friday\Base;

use Throwable;

class Awaitable {
    /**
     * @var ResultOrExceptionWrapperInterface
     */
    protected $result;

    /**
     * @var callback
     */
    protected $callback;

    /**
     * @var bool
     */
    protected $withoutWrapper = false;

    public function __construct(callable $callback)
    {
        try {
            $callback(
                function ($values = []) {
                    $this->result($values);
                },
                function ($throwable = null) {
                    $this->exception($throwable);
                }
            );
        } catch (Throwable $throwable) {
            $this->exception($throwable);
        }
    }

    /**
     * @param callable $onResolved
     * @param bool $withoutWrapper
     * @return void
     */
    public function await(callable $onResolved, $withoutWrapper = false){
        $this->withoutWrapper   = $withoutWrapper;
        $this->callback         = $onResolved;

        $this->resolve();
    }

    /**
     * @param Throwable $exception
     */
    protected function exception(Throwable $exception){
        $this->result = new WrappedException($exception);

        $this->resolve();
    }

    /**
     * @param mixed $value
     */
    protected function result($value){

        $this->result = new WrappedResult($value);

        $this->resolve();
    }

    /**
     *
     */
    protected function resolve(){
        if ($this->result !== null && $this->callback !== null) {
            if($this->withoutWrapper) {
                if($this->result->isSucceeded()){
                    call_user_func($this->callback, $this->result->getResult());
                } else {
                    call_user_func($this->callback, $this->result->getException());
                }

            } else {
                call_user_func($this->callback, $this->result);
            }
        }
    }

}