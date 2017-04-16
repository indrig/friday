<?php
namespace Friday\Base\Event;

class ExitEvent extends Event{
    /**
     * @var int|null
     */
    public $code;

    /**
     * @var int|null
     */
    public $termSignal;
}