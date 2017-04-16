<?php
namespace Friday\Console;

use Friday\Base\Exception\UserException;

/**
 * Exception represents an exception caused by incorrect usage of a console command.
 */
class Exception extends UserException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Error';
    }
}
