<?php
namespace Friday\Web\Event;

use Friday\Base\Event as BaseEvent;

class RequestEvent extends BaseEvent {
    public $request;
    public $response;
}