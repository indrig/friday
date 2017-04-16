<?php
namespace Friday\Base;

use Friday\Stream\Event\ContentEvent;
use Friday\Stream\Stream;

class InternalChildProcess extends ChildProcess{
    public function init()
    {
        $this->on(Stream::EVENT_CONTENT, [$this, 'onContent']);
    }

    public function onContent(ContentEvent $event){

    }
}
