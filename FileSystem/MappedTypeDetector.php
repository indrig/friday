<?php

namespace Friday\FileSystem;

use Friday\Promise\RejectedPromise;
use Friday\Promise\Util as PromiseUtil;

class MappedTypeDetector implements TypeDetectorInterface
{
    /**
     * @var array
     */
    protected static $defaultMapping = [
        'dir' => 'dir',
        'file' => 'file',
        'link' => 'constructLink',
    ];

    /**
     * @var array
     */
    protected $mapping = [];

    /**
     * @var FileSystemInterface
     */
    protected $filesystem;

    public static function createDefault(FileSystemInterface $filesystem)
    {
        return new static($filesystem, [
            'mapping' => static::$defaultMapping,
        ]);
    }

    /**
     * @param FileSystemInterface $filesystem
     * @param array $options
     */
    public function __construct(FileSystemInterface $filesystem, $options = [])
    {
        $this->filesystem = $filesystem;

        if (isset($options['mapping']) && is_array($options['mapping']) && count($options['mapping']) > 0) {
            $this->mapping = $options['mapping'];
        }
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
            $this->filesystem,
            $this->mapping[$node['type']],
        ]);
    }
}
