<?php
namespace Friday\Base\Event;

/**
 * ModelEvent represents the parameter needed by [[Model]] events.
 */
class ModelEvent extends Event
{
    /**
     * @var boolean whether the model is in valid status. Defaults to true.
     * A model is in valid status if it passes validations or certain checks.
     */
    public $isValid = true;
}
