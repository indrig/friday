<?php
namespace Friday\FileSystem\Stream;

use Friday\FileSystem\AdapterInterface;
use Friday\Stream\DuplexStreamInterface;
use Friday\FileSystem\ThrottledQueuedInvoker;
use Friday\Promise\FulfilledPromise;

class DuplexStream implements DuplexStreamInterface, GenericStreamInterface
{
    use ReadableStreamTrait;
    use WritableStreamTrait;
    use GenericStreamTrait;

    /**
     * @param string $path
     * @param resource $fileDescriptor
     * @param AdapterInterface $filesystem
     */
    public function __construct($path, $fileDescriptor, AdapterInterface $filesystem)
    {
        $this->path = $path;
        $this->setFilesystem($filesystem);
        $this->fileDescriptor = $fileDescriptor;

        $this->callInvoker = new ThrottledQueuedInvoker($filesystem);
    }

    protected function readChunk()
    {
        if ($this->pause) {
            return;
        }

        $this->resolveSize()->then(function () {
            $this->performRead($this->calculateChunkSize());
        });
    }

    protected function resolveSize()
    {
        if ($this->readCursor < $this->size) {
            return new FulfilledPromise();
        }

        return $this->callInvoker->invokeCall('eio_stat', [$this->path])->then(function ($stat) {
            $this->size = $stat['size'];
            return new FulfilledPromise();
        });
    }
}
