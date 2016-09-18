<?php

namespace Friday\FileSystem;

use Friday\EventLoop\LoopInterface;
use Friday\FileSystem\Node;
use Friday\Promise\Util as PromiseUtil;

class FileSystem implements FileSystemInterface
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @param LoopInterface $loop
     * @param array $options
     * @return FileSystemInterface
     */
    public static function create(LoopInterface $loop, array $options = [])
    {
        if (extension_loaded('eio')) {
            return static::setFileSystemOnAdapter(static::createFromAdapter(new Eio\Adapter($loop, $options)));
        }

        if (extension_loaded('pthreads')) {
            //return static::setFilesystemOnAdapter(static::createFromAdapter(new Pthreads\Adapter($loop, $options)));
        }

        return static::setFileSystemOnAdapter(static::createFromAdapter(new ChildProcess\Adapter($loop, $options)));
    }

    /**
     * @param AdapterInterface $adapter
     * @return FileSystemInterface
     */
    public static function createFromAdapter(AdapterInterface $adapter)
    {
        return static::setFileSystemOnAdapter(new static($adapter));
    }

    /**
     * @param FileSystemInterface $filesystem
     * @return FileSystemInterface
     */
    protected static function setFileSystemOnAdapter(FileSystemInterface $filesystem)
    {
        $filesystem->getAdapter()->setFilesystem($filesystem);
        return $filesystem;
    }

    /**
     * Filesystem constructor.
     * @param AdapterInterface $adapter
     */
    private function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param string $filename
     * @return Node\FileInterface
     */
    public function file($filename)
    {
        return new Node\File($filename, $this);
    }

    /**
     * @param string $path
     * @return Node\DirectoryInterface
     */
    public function dir($path)
    {
        return new Node\Directory($path, $this);
    }

    /**
     * @param string $path
     * @param Node\NodeInterface $destination
     * @return Node\LinkInterface
     */
    public function link($path, Node\NodeInterface $destination)
    {
        return new Node\Link($path, $destination, $this);
    }

    /**
     * @param string $path
     * @return \Friday\Promise\PromiseInterface
     */
    public function constructLink($path)
    {
        return $this->adapter->readlink($path)->then(function ($linkPath) {
            return $this->adapter->detectType($linkPath);
        })->then(function (Node\NodeInterface $destination) use ($path) {
            return PromiseUtil::resolve($this->link($path, $destination));
        });
    }

    /**
     * @param string $filename
     * @return \Friday\Promise\PromiseInterface
     */
    public function getContents($filename)
    {
        $file = $this->file($filename);
        return $file->exists()->then(function () use ($file) {
            return $file->getContents();
        });
    }

    /**
     * @param CallInvokerInterface $invoker
     */
    public function setInvoker(CallInvokerInterface $invoker)
    {
        $this->adapter->setInvoker($invoker);
    }
}
