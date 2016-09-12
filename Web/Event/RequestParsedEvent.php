<?php
namespace Friday\Web\Event;

use Friday\Base\Event as BaseEvent;
use Friday\Web\Request;

/**
 * Class RequestParsedEvent
 * @package Firday\Web\Event
 */
class RequestParsedEvent extends BaseEvent {
    /**
     * @var Request
     */
    public $request;
}