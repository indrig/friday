<?php
namespace Friday\Base;

use Throwable;

interface ResultOrExceptionWrapperInterface {
    /**
     * @return Throwable
     */
    public function getException();

    /**
     * Since this is a successful result wrapper, this always returns the actual result of the Awaitable operation.
     *
     * @return array
     */
    public function getResult();

    /**
     * @return bool
     */
    public function isFailed();

    /**
     * @return bool
     */
    public function isSucceeded();
}