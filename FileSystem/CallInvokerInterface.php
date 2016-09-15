<?php
namespace Friday\FileSystem;

interface CallInvokerInterface
{
    /**
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter);

    /**
     * @param string $function
     * @param array $args
     * @param int $errorResultCode
     * @return \Friday\Promise\ExtendedPromiseInterface
     */
    public function invokeCall($function, $args, $errorResultCode = -1);

    /**
     * @return bool
     */
    public function isEmpty();
}
