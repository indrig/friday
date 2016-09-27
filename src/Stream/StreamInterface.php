<?php
namespace Friday\Stream;

use Friday\Base\Event\Event;

interface StreamInterface{
    const EVENT_END         = 'end';
    const EVENT_CLOSE       = 'close';
    const EVENT_CONTENT     = 'content';
    const EVENT_PIPE        = 'pipe';
    const EVENT_ERROR       = 'error';
    const EVENT_DRAIN       = 'drain';
    const EVENT_PAUSE       = 'pause';
    const EVENT_RESUME      = 'resume';

    public function trigger($name, Event $event = null);

    public function on($name, $handler, $data = null, $append = true);
}