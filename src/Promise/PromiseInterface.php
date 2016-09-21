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

    /**
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @return mixed
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null);

    /**
     * @param callable $onRejected
     * @return mixed
     */
    public function otherwise(callable $onRejected);

    /**
     * @param callable $onFulfilledOrRejected
     * @return mixed
     */
    public function always(callable $onFulfilledOrRejected);
}
