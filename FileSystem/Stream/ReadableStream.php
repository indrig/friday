<?php

namespace Friday\Filesystem\Stream;

use Friday\Base\EventTrait;
use Friday\Filesystem\AdapterInterface;
use Friday\Stream\ReadableStreamInterface;

class ReadableStream implements GenericStreamInterface, ReadableStreamInterface
{
    use EventTrait;
    use ReadableStreamTrait;
    use GenericStreamTrait;

    /**
     * @param string $path
     * @param resource $fileDescriptor
     * @param AdapterInterface $fileSystem
     */
    public function __construct($path, $fileDescriptor, AdapterInterface $fileSystem)
    {
        $this->path = $path;
        $this->fileSystem = $fileSystem;
        $this->fileDescriptor = $fileDescriptor;

        $this->resume();
    }
}
