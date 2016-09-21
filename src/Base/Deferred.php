<?php
namespace Friday\Base;

class Deferred
{
    private $promise;
    private $successCallback;
    private $canceller;

    public function __construct(callable $canceller = null)
    {
        $this->canceller = $canceller;
    }

    public function promise()
    {
        if (null === $this->promise) {
            $this->promise = new Awaitable(function ($resolve) {
                $this->successCallback = $success;
            }, $this->canceller);
        }

        return $this->promise;
    }

    public function success()
    {
        $this->promise();

        call_user_func_array($this->successCallback, func_get_args());
    }
}
