<?php
namespace Friday\Filesystem\Stream;

interface GenericStreamInterface
{
    /**
     * @return resource
     */
    public function getFileDescriptor();
}
