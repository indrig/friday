<?php
namespace Friday\FileSystem\Node;

use Friday\FileSystem\ObjectStream;
use Friday\Promise\PromiseInterface;

interface NodeInterface extends GenericOperationInterface
{
    const DS = DIRECTORY_SEPARATOR;

    /**
     * @return string
     */
    public function __toString();

    /**
     * @return NodeInterface|null
     */
    public function getParent();

    /**
     * @return string
     */
    public function getPath();

    /**
     * @return string
     */
    public function getName();

    /**
     * @param NodeInterface $node
     * @return PromiseInterface
     */
    public function copy(NodeInterface $node);

    /**
     * @param NodeInterface $node
     * @return ObjectStream
     */
    public function copyStreaming(NodeInterface $node);
}
