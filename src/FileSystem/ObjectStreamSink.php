<?php

namespace Friday\FileSystem;

use Friday\Promise\Deferred;

class ObjectStreamSink
{
    /**
     * @param ObjectStream $stream
     * @return \Friday\Promise\Promise
     */
    public static function promise(ObjectStream $stream)
    {
        $deferred = new Deferred();
        $list = new \SplObjectStorage();

        $stream->on('data', function ($object) use ($list) {
            $list->attach($object);
        });
        $stream->on('end', function () use ($deferred, $list) {
            $deferred->resolve($list);
        });

        return $deferred->promise();
    }
}
