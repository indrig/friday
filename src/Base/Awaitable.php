<?php
namespace Friday\Base;

use Friday;
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
     * @var ContextInterface
     */
    protected $context;

    /**
     * @var bool
     */
    protected $withWrapper = false;

    public function __construct(callable $callback)
    {
        $this->context = Friday::$app->getContext();
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
            $oldContext = Friday::$app->getContext();

            Friday::$app->setContext($this->context);

            try {
                if($this->withWrapper) {
                    call_user_func($this->callback, $this->result);
                } else {
                    if($this->result->isSucceeded()){
                        call_user_func($this->callback, $this->result->getResult());
                    } else {
                        call_user_func($this->callback, $this->result->getException());
                    }
                }
            }catch (Throwable $throwable){
                Friday::$app->errorHandler->handleException($throwable);
            }

            Friday::$app->setContext($oldContext);

        }
    }

}