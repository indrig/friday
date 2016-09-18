<?php
namespace Friday\Web\HttpException;

/**
 * ForbiddenHttpException represents a "Forbidden" HTTP exception with status code 403.
 *
 * Use this exception when a user has been authenticated but is not allowed to
 * perform the requested action. If the user is not authenticated, consider
 * using a 401 [[UnauthorizedHttpException]]. If you do not want to
 * expose authorization information to the user, it is valid to respond with a
 * 404 [[NotFoundHttpException]].
 */
class ForbiddenHttpException extends HttpException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param integer $code error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct(403, $message, $code, $previous);
    }
}
