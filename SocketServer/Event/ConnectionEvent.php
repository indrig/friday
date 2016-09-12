<?php
namespace Friday\SocketServer\Event;

use Friday\Base\Event;
use Friday\SocketServer\Connection;

class ConnectionEvent extends Event {
    /**
     * @var Connection
     */
    public $connection;
}