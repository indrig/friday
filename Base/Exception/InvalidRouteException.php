<?php
namespace Friday\Base\Exception;


/**
 * InvalidRouteException represents an exception caused by an invalid route.
 *
 */
class InvalidRouteException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Invalid Route';
    }
}
