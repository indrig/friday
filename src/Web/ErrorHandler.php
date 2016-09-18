<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Exception\Exception;
use Friday\Base\AbstractErrorHandler;
use Friday\Base\Exception\ErrorException;
use Friday\Base\Exception\UserException;
use Friday\Helper\AliasHelper;
use Friday\Helper\Console;
use Friday\Helper\VarDumper;
use Friday\Web\HttpException\HttpException;

class ErrorHandler extends AbstractErrorHandler{
    /**
     * @var integer maximum number of source code lines to be displayed. Defaults to 19.
     */
    public $maxSourceLines = 19;
    /**
     * @var integer maximum number of trace source code lines to be displayed. Defaults to 13.
     */
    public $maxTraceSourceLines = 13;
    /**
     * @var string the route (e.g. 'site/error') to the controller action that will be used
     * to display external errors. Inside the action, it can retrieve the error information
     * using `Yii::$app->errorHandler->exception. This property defaults to null, meaning ErrorHandler
     * will handle the error display.
     */
    public $errorAction;
    /**
     * @var string the path of the view file for rendering exceptions without call stack information.
     */
    public $errorView = '@friday/view/errorHandler/error.php';
    /**
     * @var string the path of the view file for rendering exceptions.
     */
    public $exceptionView = '@friday/view/errorHandler/exception.php';
    /**
     * @var string the path of the view file for rendering exceptions and errors call stack element.
     */
    public $callStackItemView = '@friday/view/errorHandler/callStackItem.php';
    /**
     * @var string the path of the view file for rendering previous exceptions.
     */
    public $previousExceptionView = '@friday/view/errorHandler/previousException.php';

    /**
     * @var array list of the PHP predefined variables that should be displayed on the error page.
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise it won't be displayed.
     * Defaults to `['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION']`.
     * @see renderRequest()
     * @since 2.0.7
     */
    public $displayVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION'];
    /**
     * Renders an exception using ansi format for console output.
     * @param \Exception $exception the exception to be rendered.
     */
    protected function renderException($exception)
    {
        if(null !== $currentContext = Friday::$app->currentContext){
            $response   = $currentContext->response;
            $request    = $currentContext->request;

            // reset parameters of response to avoid interference with partially created response data
            // in case the error occurred while sending the response.
            //$response->isSent = false;
            $response->stream   = null;
            $response->data     = null;
            $response->content  = null;
            $useErrorView = $response->format === Response::FORMAT_HTML && (!FRIDAY_DEBUG || $exception instanceof UserException);

            if ($useErrorView && $this->errorAction !== null) {
                $result = Friday::$app->runAction($this->errorAction);
                if ($result instanceof Response) {
                    $response = $result;
                } else {
                    $response->data = $result;
                }
            } elseif ($response->format === Response::FORMAT_HTML) {
                if (FRIDAY_ENV_TEST || $request->isAjax) {
                    // AJAX request
                    $response->data = '<pre>' . $this->htmlEncode(static::convertExceptionToString($exception)) . '</pre>';
                } else {
                    // if there is an error during error rendering it's useful to
                    // display PHP error in debug mode instead of a blank screen
                    if (FRIDAY_DEBUG) {
                        ini_set('display_errors', 1);
                    }
                    $file = $useErrorView ? $this->errorView : $this->exceptionView;
                    $response->data = $this->renderFile($file, [
                        'exception' => $exception,
                    ]);
                }
            } elseif ($response->format === Response::FORMAT_RAW) {
                $response->data = static::convertExceptionToString($exception);
            } else {
                $response->data = $this->convertExceptionToArray($exception);
            }
            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->statusCode);
            } else {
                $response->setStatusCode(500);
            }

            $response->send();
            return;
        }


        if ($exception instanceof Exception && !FRIDAY_DEBUG) {
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
           // if ($exception instanceof \yii\db\Exception && !empty($exception->errorInfo)) {
            //    $message .= "\n" . $this->formatMessage("Error Info:\n", [Console::BOLD]) . print_r($exception->errorInfo, true);
            //}
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
     * Converts an exception into an array.
     * @param \Exception $exception the exception being converted
     * @return array the array representation of the exception.
     */
    protected function convertExceptionToArray($exception)
    {
        if (!FRIDAY_DEBUG && !$exception instanceof UserException && !$exception instanceof HttpException) {
            $exception = new HttpException(500, Friday::t('An internal server error occurred.'));
        }

        $array = [
            'name' => ($exception instanceof Exception || $exception instanceof ErrorException) ? $exception->getName() : 'Exception',
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
        if ($exception instanceof HttpException) {
            $array['status'] = $exception->statusCode;
        }
        if (FRIDAY_DEBUG) {
            $array['type'] = get_class($exception);
            if (!$exception instanceof UserException) {
                $array['file'] = $exception->getFile();
                $array['line'] = $exception->getLine();
                $array['stack-trace'] = explode("\n", $exception->getTraceAsString());
                if ($exception instanceof Friday\Db\Exception\Exception) {
                    $array['error-info'] = $exception->errorInfo;
                }
            }
        }
        if (($prev = $exception->getPrevious()) !== null) {
            $array['previous'] = $this->convertExceptionToArray($prev);
        }

        return $array;
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
        //if (\Friday::$app->controller instanceof \yii\console\Controller && Yii::$app->controller->isColorEnabled($stream)
         //   || Yii::$app instanceof \yii\console\Application && Console::streamSupportsAnsiColors($stream)) {
            $message = Console::ansiFormat($message, $format);
       // }
        return $message;
    }

    /**
     * Converts special characters to HTML entities.
     * @param string $text to encode.
     * @return string encoded original text.
     */
    public function htmlEncode($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renders a view file as a PHP script.
     * @param string $_file_ the view file.
     * @param array $_params_ the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @return string the rendering result
     */
    public function renderFile($_file_, $_params_)
    {
        $_params_['handler'] = $this;
        if (!Friday::$app->has('view')) {
            ob_start();
            ob_implicit_flush(false);
            extract($_params_, EXTR_OVERWRITE);

            require(AliasHelper::getAlias($_file_));

            return ob_get_clean();
        } else {
            return Friday::$app->view->renderFile($_file_, $_params_, $this);
        }
    }

    /**
     * Returns human-readable exception name
     * @param \Exception $exception
     * @return string human-readable exception name or null if it cannot be determined
     */
    public function getExceptionName($exception)
    {
        if (method_exists($exception, 'getName')) {
            return $exception->getName();
        }
        return null;
    }

    /**
     * Adds informational links to the given PHP type/class.
     * @param string $code type/class name to be linkified.
     * @return string linkified with HTML type/class name.
     */
    public function addTypeLinks($code)
    {
        if (preg_match('/(.*?)::([^(]+)/', $code, $matches)) {
            $class = $matches[1];
            $method = $matches[2];
            $text = $this->htmlEncode($class) . '::' . $this->htmlEncode($method);
        } else {
            $class = $code;
            $method = null;
            $text = $this->htmlEncode($class);
        }

        $url = $this->getTypeUrl($class, $method);

        if (!$url) {
            return $text;
        }

        return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
    }

    /**
     * Returns the informational link URL for a given PHP type/class.
     * @param string $class the type or class name.
     * @param string|null $method the method name.
     * @return string|null the informational link URL.
     * @see addTypeLinks()
     */
    protected function getTypeUrl($class, $method)
    {
        if (strpos($class, 'yii\\') !== 0) {
            return null;
        }

        $page = $this->htmlEncode(strtolower(str_replace('\\', '-', $class)));
        $url = "http://www.yiiframework.com/doc-2.0/$page.html";
        if ($method) {
            $url .= "#$method()-detail";
        }

        return $url;
    }

    /**
     * Renders the previous exception stack for a given Exception.
     * @param \Exception $exception the exception whose precursors should be rendered.
     * @return string HTML content of the rendered previous exceptions.
     * Empty string if there are none.
     */
    public function renderPreviousExceptions($exception)
    {
        if (($previous = $exception->getPrevious()) !== null) {
            return $this->renderFile($this->previousExceptionView, ['exception' => $previous]);
        } else {
            return '';
        }
    }

    /**
     * Renders a single call stack element.
     * @param string|null $file name where call has happened.
     * @param integer|null $line number on which call has happened.
     * @param string|null $class called class name.
     * @param string|null $method called function/method name.
     * @param array $args array of method arguments.
     * @param integer $index number of the call stack element.
     * @return string HTML content of the rendered call stack element.
     */
    public function renderCallStackItem($file, $line, $class, $method, $args, $index)
    {
        $lines = [];
        $begin = $end = 0;
        if ($file !== null && $line !== null) {
            $line--; // adjust line number from one-based to zero-based
            $lines = @file($file);
            if ($line < 0 || $lines === false || ($lineCount = count($lines)) < $line) {
                return '';
            }

            $half = (int) (($index === 1 ? $this->maxSourceLines : $this->maxTraceSourceLines) / 2);
            $begin = $line - $half > 0 ? $line - $half : 0;
            $end = $line + $half < $lineCount ? $line + $half : $lineCount - 1;
        }

        return $this->renderFile($this->callStackItemView, [
            'file' => $file,
            'line' => $line,
            'class' => $class,
            'method' => $method,
            'index' => $index,
            'lines' => $lines,
            'begin' => $begin,
            'end' => $end,
            'args' => $args,
        ]);
    }

    /**
     * Creates string containing HTML link which refers to the home page of determined web-server software
     * and its full name.
     * @return string server software information hyperlink.
     */
    public function createServerInformationLink()
    {
        $serverUrls = [
            'http://httpd.apache.org/' => ['apache'],
            'http://nginx.org/' => ['nginx'],
            'http://lighttpd.net/' => ['lighttpd'],
            'http://gwan.com/' => ['g-wan', 'gwan'],
            'http://iis.net/' => ['iis', 'services'],
            'http://php.net/manual/en/features.commandline.webserver.php' => ['development'],
        ];
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            foreach ($serverUrls as $url => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($_SERVER['SERVER_SOFTWARE'], $keyword) !== false) {
                        return '<a href="' . $url . '" target="_blank">' . $this->htmlEncode($_SERVER['SERVER_SOFTWARE']) . '</a>';
                    }
                }
            }
        }

        return '';
    }

    /**
     * Creates string containing HTML link which refers to the page with the current version
     * of the framework and version number text.
     * @return string framework version information hyperlink.
     */
    public function createFrameworkVersionLink()
    {
        return '<a href="http://github.com/yiisoft/yii2/" target="_blank">' . $this->htmlEncode(Friday::getVersion()) . '</a>';
    }

    /**
     * Renders the global variables of the request.
     * List of global variables is defined in [[displayVars]].
     * @return string the rendering result
     * @see displayVars
     */
    public function renderRequest()
    {
        $request = '';
        foreach ($this->displayVars as $name) {
            if (!empty($GLOBALS[$name])) {
                $request .= '$' . $name . ' = ' . VarDumper::export($GLOBALS[$name]) . ";\n\n";
            }
        }

        return '<pre>' . rtrim($request, "\n") . '</pre>';
    }

    /**
     * Determines whether given name of the file belongs to the framework.
     * @param string $file name to be checked.
     * @return boolean whether given name of the file belongs to the framework.
     */
    public function isCoreFile($file)
    {
        return $file === null || strpos(realpath($file), FRIDAY_PATH . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Converts arguments array to its string representation
     *
     * @param array $args arguments array to be converted
     * @return string string representation of the arguments array
     */
    public function argumentsToString($args)
    {
        $count = 0;
        $isAssoc = $args !== array_values($args);

        foreach ($args as $key => $value) {
            $count++;
            if ($count>=5) {
                if ($count>5) {
                    unset($args[$key]);
                } else {
                    $args[$key] = '...';
                }
                continue;
            }

            if (is_object($value)) {
                $args[$key] = '<span class="title">' . $this->htmlEncode(get_class($value)) . '</span>';
            } elseif (is_bool($value)) {
                $args[$key] = '<span class="keyword">' . ($value ? 'true' : 'false') . '</span>';
            } elseif (is_string($value)) {
                $fullValue = $this->htmlEncode($value);
                if (mb_strlen($value, 'UTF-8') > 32) {
                    $displayValue = $this->htmlEncode(mb_substr($value, 0, 32, 'UTF-8')) . '...';
                    $args[$key] = "<span class=\"string\" title=\"$fullValue\">'$displayValue'</span>";
                } else {
                    $args[$key] = "<span class=\"string\">'$fullValue'</span>";
                }
            } elseif (is_array($value)) {
                $args[$key] = '[' . $this->argumentsToString($value) . ']';
            } elseif ($value === null) {
                $args[$key] = '<span class="keyword">null</span>';
            } elseif (is_resource($value)) {
                $args[$key] = '<span class="keyword">resource</span>';
            } else {
                $args[$key] = '<span class="number">' . $value . '</span>';
            }

            if (is_string($key)) {
                $args[$key] = '<span class="string">\'' . $this->htmlEncode($key) . "'</span> => $args[$key]";
            } elseif ($isAssoc) {
                $args[$key] = "<span class=\"number\">$key</span> => $args[$key]";
            }
        }
        $out = implode(', ', $args);

        return $out;
    }

    /**
     * Creates HTML containing link to the page with the information on given HTTP status code.
     * @param integer $statusCode to be used to generate information link.
     * @param string $statusDescription Description to display after the the status code.
     * @return string generated HTML with HTTP status code information.
     */
    public function createHttpStatusLink($statusCode, $statusDescription)
    {
        return '<a href="http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#' . (int) $statusCode . '" target="_blank">HTTP ' . (int) $statusCode . ' &ndash; ' . $statusDescription . '</a>';
    }

}