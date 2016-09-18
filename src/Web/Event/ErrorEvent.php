<?php
namespace Friday\Web\Event;

use Friday\Base\Event as BaseEvent;

class ErrorEvent extends BaseEvent {
    public $error;
}