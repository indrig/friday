<?php
namespace Friday\FileSystem\Stream;

use Friday\Base\EventTrait;
use Friday\FileSystem\AdapterInterface;
use Friday\FileSystem\InstantInvoker;

trait GenericStreamTrait
{
    use EventTrait;

    protected $path;
    protected $fileSystem;
    protected $fileDescriptor;
    protected $closed = false;
    protected $callInvoker;

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

        $this->callInvoker = new InstantInvoker($fileSystem);
    }

    /**
     * @return AdapterInterface
     */
    public function getFileSystem()
    {
        return $this->fileSystem;
    }

    /**
     * @param AdapterInterface $filesystem
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileDescriptor()
    {
        return $this->fileDescriptor;
    }

    /**
     * @return boolean
     */
    public function isClosed()
    {
        return $this->closed;
    }

    /**
     * @param boolean $closed
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->trigger('end', [$this]);

        $this->fileSystem->close($this->fileDescriptor)->then(function () {
            $this->trigger('close', [$this]);
        });
    }
}
