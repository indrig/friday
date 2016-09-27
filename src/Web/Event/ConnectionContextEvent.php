<?php
namespace Friday\Web\Event;

use Friday\Base\Event\Event;

class ConnectionContextEvent extends Event{
    public $connectionContent;
}