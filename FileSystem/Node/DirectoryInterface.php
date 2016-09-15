<?php
namespace Friday\FileSystem\Node;

use Friday\FileSystem\AdapterInterface;

interface DirectoryInterface extends NodeInterface
{
    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function create($mode = AdapterInterface::CREATION_MODE);

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function createRecursive($mode = AdapterInterface::CREATION_MODE);

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function remove();

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function ls();

    /**
     * @param int $mode
     * @return \Friday\Promise\PromiseInterface
     */
    public function chmodRecursive($mode);

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function chownRecursive();

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function removeRecursive();

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function lsRecursive();

    /**
     * @param DirectoryInterface $directory
     * @return \Friday\Promise\PromiseInterface
     */
    //public function rsync(DirectoryInterface $directory);
}
