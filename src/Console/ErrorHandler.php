<?php
namespace Friday\Console;

use Friday;

use Friday\Helper\Console;

use Friday\Base\Exception\ErrorException;
use Friday\Base\Exception\UserException;
use Friday\Db\Exception\Exception as DbException;

use Friday\Base\Exception\Exception;


/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\Friday\Base\Application]] by default.
 * You can access that instance via `Friday::$app->errorHandler`.
 */
class ErrorHandler extends \Friday\Base\ErrorHandler
{
    /**
     * Renders an exception using ansi format for console output.
     * @param \Exception $exception the exception to be rendered.
     */
    protected function renderException($exception)
    {

        if ($exception instanceof Exception && ($exception instanceof UserException || !FRIDAY_DEBUG)) {
            $message = $this->formatMessage($exception->getName() . ': ') . $exception->getMessage();
        } elseif (FRIDAY_DEBUG) {
            if ($exception instanceof Exception) {
                $message = $this->formatMessage("Exception ({$exception->getName()})");
            } elseif ($exception instanceof ErrorException) {
                $message = $this->formatMessage($exception->getName());
            } else {
                $message = $this->formatMessage('Exception');
            }
            $message .= $this->formatMessage(" '" . get_class($exception) . "'", [Console::BOLD, Console::FG_BLUE])
                . ' with message ' . $this->formatMessage("'{$exception->getMessage()}'", [Console::BOLD]) //. "\n"
                . "\n\nin " . dirname($exception->getFile()) . DIRECTORY_SEPARATOR . $this->formatMessage(basename($exception->getFile()), [Console::BOLD])
                . ':' . $this->formatMessage($exception->getLine(), [Console::BOLD, Console::FG_YELLOW]) . "\n";
            if ($exception instanceof DbException && !empty($exception->errorInfo)) {
                $message .= "\n" . $this->formatMessage("Error Info:\n", [Console::BOLD]) . print_r($exception->errorInfo, true);
            }
            $message .= "\n" . $this->formatMessage("Stack trace:\n", [Console::BOLD]) . $exception->getTraceAsString();
        } else {
            $message = $this->formatMessage('Error: ') . $exception->getMessage();
        }

        if (PHP_SAPI === 'cli') {
            Console::stderr($message . "\n");
        } else {
            echo $message . "\n";
        }
    }

    /**
     * Colorizes a message for console output.
     * @param string $message the message to colorize.
     * @param array $format the message format.
     * @return string the colorized message.
     * @see Console::ansiFormat() for details on how to specify the message format.
     */
    protected function formatMessage($message, $format = [Console::FG_RED, Console::BOLD])
    {
        $stream = (PHP_SAPI === 'cli') ? \STDERR : \STDOUT;
        // try controller first to allow check for --color switch
        if (Friday::$app->controller instanceof Controller && Friday::$app->controller->isColorEnabled($stream)
            || Friday::$app instanceof Application && Console::streamSupportsAnsiColors($stream)) {
            $message = Console::ansiFormat($message, $format);
        }
        return $message;
    }
}
