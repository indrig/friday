<?php
namespace Friday\Base\Exception;

use Exception as BaseException;

class Exception extends BaseException implements ExceptionInterface  {
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Exception';
    }
}