<?php
namespace Friday\Web\Event;

use Friday\Base\Event\Event as BaseEvent;
use Friday\Web\Request;
use Friday\Web\Response;

class RequestEvent extends BaseEvent {
    /**
     * @var Request
     */
    public $request;

    /**
     * @var Response
     */
    public $response;
}