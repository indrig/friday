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
    protected $withWrapper = false;

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
     * @param bool $withWrapper
     * @return void
     */
    public function await(callable $onResolved, $withWrapper = false){
        $this->withWrapper   = $withWrapper;
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
            if($this->withWrapper) {

                call_user_func($this->callback, $this->result);
            } else {
                if($this->result->isSucceeded()){
                    call_user_func($this->callback, $this->result->getResult());
                } else {
                    call_user_func($this->callback, $this->result->getException());
                }
            }
        }
    }

}