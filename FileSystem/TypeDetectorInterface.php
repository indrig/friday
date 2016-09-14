<?php
namespace Friday\FileSystem;

interface TypeDetectorInterface
{
    /**
     * @param FilesystemInterface $filesystem
     */
    public function __construct(FileSystemInterface $filesystem);

    /**
     * @param array $node
     * @return \Friday\Promise\PromiseInterface
     */
    public function detect(array $node);
}
