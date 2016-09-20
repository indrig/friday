<?php
namespace Friday\Base\Exception;

/**
 * NotSupportedException represents an exception caused by accessing features that are not supported.
 *
 */
class NotSupportedException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Not Supported';
    }
}
