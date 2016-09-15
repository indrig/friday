<?php
namespace Friday\FileSystem\Exception;

use Friday\Base\Exception\ExceptionInterface;
use RuntimeException as SplRuntimeException;

class RuntimeException extends SplRuntimeException implements ExceptionInterface
{
    use ArgsExceptionTrait;
}
