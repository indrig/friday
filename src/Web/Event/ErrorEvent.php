<?php
namespace Friday\Web\Event;

use Friday\Base\Event\Event as BaseEvent;

class ErrorEvent extends BaseEvent {
    public $error;
}