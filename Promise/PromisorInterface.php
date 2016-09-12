<?php
namespace Friday\Promise;

interface PromisorInterface
{
    /**
     * @return PromiseInterface
     */
    public function promise();
}
