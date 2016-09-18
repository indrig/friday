<?php

namespace Friday\FileSystem\Stream;

use Friday\FileSystem\AdapterInterface;
use Friday\Stream\WritableStreamInterface;

class WritableStream implements GenericStreamInterface, WritableStreamInterface
{
    use WritableStreamTrait;
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
    }
}
