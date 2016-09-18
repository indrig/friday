<?php

namespace Friday\FileSystem;

interface FlagResolverInterface
{
    /**
     * @return int
     */
    public function defaultFlags();

    /**
     * @return array
     */
    public function flagMapping();
}
