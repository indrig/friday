<?php
namespace Friday\Db\Exception;

use Friday\Base\Exception\Exception as BaseException;


class Exception extends BaseException {

    /**
     * Constructor.
     * @param string $message PDO error message

     * @param integer $code PDO error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Database Exception';
    }
}