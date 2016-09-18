<?php
namespace Friday\FileSystem\Node;

interface GenericOperationInterface
{
    /**
     * @return \Friday\FileSystem\AdapterInterface
     */
    public function getFileSystem();

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function stat();

    /**
     * @param int $mode
     * @return \Friday\Promise\PromiseInterface
     */
    public function chmod($mode);

    /**
     * @param int $uid
     * @param int $gid
     * @return \Friday\Promise\PromiseInterface
     */
    public function chown($uid = -1, $gid = -1);
}
