<?php
namespace Friday\FileSystem\Node;

use Friday\Filesystem\AdapterInterface;
use Friday\Promise\PromiseInterface;

interface FileInterface extends NodeInterface
{
    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function exists();

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function remove();

    /**
     * @param $flags
     * @param string $mode
     * @return mixed
     */
    public function open($flags, $mode = AdapterInterface::CREATION_MODE);

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function time();

    /**
     * @param string $toFilename
     * @return \Friday\Promise\PromiseInterface
     */
    public function rename($toFilename);

    /**
     * @return \Friday\Promise\PromiseInterface
     */
    public function size();

    /**
     * @param string $mode
     * @param null $time
     * @return \Friday\Promise\PromiseInterface
     */
    public function create($mode = AdapterInterface::CREATION_MODE, $time = null);

    /**
     * @param string $mode
     * @param null $time
     * @return \Friday\Promise\PromiseInterface
     */
    public function touch($mode = AdapterInterface::CREATION_MODE, $time = null);

    /**
     * @return PromiseInterface
     */
    public function getContents();

    /**
     * @param string $contents
     * @return PromiseInterface
     */
    public function putContents($contents);
}
