<?php
namespace Fridaty\base;

use Friday\Base\Exception\Exception;

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
