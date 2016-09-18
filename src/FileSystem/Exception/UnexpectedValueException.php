<?php
namespace Friday\FileSystem\Exception;

use Friday\Base\Exception\ExceptionInterface;
use UnexpectedValueException as SplUnexpectedValueException;

class UnexpectedValueException extends SplUnexpectedValueException implements ExceptionInterface
{
    use ArgsExceptionTrait;
}
