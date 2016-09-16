<?php
namespace Friday\Promise;

class Deferred implements PromisorInterface
{
    private $promise;
    private $resolveCallback;
    private $rejectCallback;
    private $canceller;

    public function __construct(callable $canceller = null)
    {
        $this->canceller = $canceller;
    }

    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new Promise(function ($resolve, $reject) {
                $this->resolveCallback = $resolve;
                $this->rejectCallback  = $reject;
            }, $this->canceller);
        }

        return $this->promise;
    }

    public function resolve()
    {
        $this->promise();

        call_user_func_array($this->resolveCallback, func_get_args());
    }

    public function reject()
    {
        $this->promise();

        call_user_func_array($this->rejectCallback, func_get_args());
    }
}
