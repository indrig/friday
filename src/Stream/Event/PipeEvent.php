<?php
namespace Friday\Stream\Event;

use Friday\Stream\ReadableStreamInterface;

class PipeEvent extends Event {
    /**
     * @var ReadableStreamInterface
     */
    public $source;

}