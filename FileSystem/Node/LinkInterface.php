<?php
namespace Friday\FileSystem\Node;

interface LinkInterface extends NodeInterface
{
    /**
     * @return NodeInterface
     */
    public function getDestination();
}
