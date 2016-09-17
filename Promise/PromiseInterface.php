<?php
namespace Friday\Promise;

interface PromiseInterface
{
    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @return mixed
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null);
}
