<?php
namespace Friday\Web\Event;

use Friday\Base\Event\Event;

class ConnectionContextErrorEvent extends ConnectionContextEvent{
    public $error;
}