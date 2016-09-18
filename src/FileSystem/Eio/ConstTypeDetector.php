<?php
namespace Friday\FileSystem\Eio;

use Friday\FileSystem\FileSystemInterface;
use Friday\FileSystem\TypeDetectorInterface;
use Friday\Promise\RejectedPromise;
use Friday\Promise\Util as PromiseUtil;

class ConstTypeDetector implements TypeDetectorInterface
{
    /**
     * @var array
     */
    protected $mapping = [
        EIO_DT_DIR => 'dir',
        EIO_DT_REG => 'file',
        EIO_DT_LNK => 'constructLink',
    ];

    /**
     * @var FileSystemInterface
     */
    protected $fileSystem;

    /**
     * @param FileSystemInterface $fileSystem
     */
    public function __construct(FileSystemInterface $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    /**
     * @param array $node
     * @return \Friday\Promise\PromiseInterface
     */
    public function detect(array $node)
    {
        if (!isset($node['type']) || !isset($this->mapping[$node['type']])) {
            return new RejectedPromise();
        }

        return PromiseUtil::resolve([
            $this->fileSystem,
            $this->mapping[$node['type']],
        ]);
    }
}
