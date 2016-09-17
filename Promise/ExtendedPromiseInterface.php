<?php
namespace Friday\Promise;

interface ExtendedPromiseInterface extends PromiseInterface
{
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
