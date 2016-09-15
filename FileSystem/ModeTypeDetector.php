<?php
namespace Friday\FileSystem;


use Friday\Promise\RejectedPromise;
use Friday\Promise\Util as PromiseUtil;

class ModeTypeDetector implements TypeDetectorInterface
{
    /**
     * @var array
     */
    protected $mapping = [
        0xa000 => 'constructLink',
        0x4000 => 'dir',
        0x8000 => 'file',
    ];

    /**
     * @var FilesystemInterface
     */
    protected $fileSystem;

    /**
     * @param FilesystemInterface $fileSystem
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
        return $this->fileSystem->getAdapter()->stat($node['path'])->then(function ($stat) {
            return $this->walkMapping($stat);
        });
    }

    protected function walkMapping($stat)
    {
        $promiseChain = new RejectedPromise();
        foreach ($this->mapping as $mappingMode => $method) {
            $promiseChain = $promiseChain->otherwise(function () use ($stat, $mappingMode, $method) {
                return $this->matchMapping($stat['mode'], $mappingMode, $method);
            });
        }
        return $promiseChain;
    }

    protected function matchMapping($mode, $mappingMode, $method)
    {
        if (($mode & $mappingMode) == $mappingMode) {
            return PromiseUtil::resolve([
                $this->fileSystem,
                $method,
            ]);
        }

        return new RejectedPromise();
    }
}
