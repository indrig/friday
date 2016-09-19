<?php

namespace Friday\Stream;

use Friday\Stream\Event\ContentEvent;

class ThroughStream extends CompositeStream
{
    public function __construct()
    {
        $readable = new ReadableStream();
        $writable = new WritableStream();

        parent::__construct($readable, $writable);
    }

    public function filter($data)
    {
        return $data;
    }

    public function write($data)
    {
        $this->readable->trigger(ReadableStreamInterface::EVENT_CONTENT, new ContentEvent(['context' => $this->filter($data)]));
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->readable->trigger(ReadableStreamInterface::EVENT_CONTENT, new ContentEvent(['context' => $this->filter($data)]));
        }

        $this->writable->end($data);
    }
}
