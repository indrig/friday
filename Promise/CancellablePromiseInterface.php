<?php
namespace Friday\Promise;

interface CancellablePromiseInterface extends PromiseInterface
{
    /**
     * @return void
     */
    public function cancel();
}
