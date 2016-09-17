<?php
namespace Friday\Web\Event;

use Friday\Base\Event;

class ConnectionContextErrorEvent extends ConnectionContextEvent{
    public $error;
}