<?php
namespace Friday\Db\Event;

use Friday\Base\Event\Event;

/**
 * AfterSaveEvent represents the information available in [[ActiveRecord::EVENT_AFTER_INSERT]] and [[ActiveRecord::EVENT_AFTER_UPDATE]].
 */
class AfterSaveEvent extends Event
{
    /**
     * @var array The attribute values that had changed and were saved.
     */
    public $changedAttributes;
}
