<?php
namespace Friday\Web\HttpException;

use Friday\Base\Exception\Exception;
use Friday\Web\HttpCodes;

/**
 * HttpException represents an exception caused by an improper request of the end-user.
 *
 * HttpException can be differentiated via its [[statusCode]] property value which
 * keeps a standard HTTP status code (e.g. 404, 500). Error handlers may use this status code
 * to decide how to format the error page.
 *
 * Throwing an HttpException like in the following example will result in the 404 page to be displayed.
 *
 * ```php
 * if ($item === null) { // item does not exist
 *     throw new \yii\web\HttpException(404, 'The requested Item could not be found.');
 * }
 * ```
 */
class HttpException extends Exception
{
    /**
     * @var integer HTTP status code, such as 403, 404, 500, etc.
     */
    public $statusCode;


    /**
     * Constructor.
     * @param integer $status HTTP status code, such as 404, 500, etc.
     * @param string $message error message
     * @param integer $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($status, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->statusCode = $status;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        if (isset(HttpCodes::$httpStatuses[$this->statusCode])) {
            return HttpCodes::$httpStatuses[$this->statusCode];
        } else {
            return 'Error';
        }
    }
}
