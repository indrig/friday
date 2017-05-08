<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Component;
use Friday\Base\Awaitable;
use Friday\Base\Deferred;
use Friday\Base\Exception\BadMethodCallException;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Base\Exception\RuntimeException;
use Friday\Base\ResultOrExceptionWrapperInterface;
use Friday\Helper\FileHelper;
use Friday\Helper\Url;
use Friday\SocketServer\Connection;
use Friday\Stream\Stream;
use Friday\Web\HttpException\HttpException;
use Throwable;
/**
 * Class Response
 * @package Friday\Web
 *
 * @property Connection $connection
 *
 * @property HeaderCollection $headers
 *
 * @property ConnectionContext $connectionContext
 */
class Response extends Component
{
    use ConnectionContextTrait;

    /**
     * @event ResponseEvent an event that is triggered at the beginning of [[send()]].
     */
    const EVENT_BEFORE_SEND = 'beforeSend';
    /**
     * @event ResponseEvent an event that is triggered at the end of [[send()]].
     */
    const EVENT_AFTER_SEND = 'afterSend';
    /**
     * @event ResponseEvent an event that is triggered right after [[prepare()]] is called in [[send()]].
     * You may respond to this event to filter the response content before it is sent to the client.
     */
    const EVENT_AFTER_PREPARE = 'afterPrepare';

    const EVENT_ERROR = 'error';
    const EVENT_DRAIN = 'drain';
    const EVENT_CLOSE = 'close';

    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';
    const FORMAT_JSONP = 'jsonp';
    const FORMAT_XML = 'xml';

    /**
     * @var array the formatters for converting data into the response content of the specified [[format]].
     * The array keys are the format names, and the array values are the corresponding configurations
     * for creating the formatter objects.
     * @see format
     * @see defaultFormatters
     */
    public $formatters = [];

    /**
     * @var string the response format. This determines how to convert [[data]] into [[content]]
     * when the latter is not set. The value of this property must be one of the keys declared in the [[formatters] array.
     * By default, the following formats are supported:
     *
     * - [[FORMAT_RAW]]: the data will be treated as the response content without any conversion.
     *   No extra HTTP header will be added.
     * - [[FORMAT_HTML]]: the data will be treated as the response content without any conversion.
     *   The "Content-Type" header will set as "text/html".
     * - [[FORMAT_JSON]]: the data will be converted into JSON format, and the "Content-Type"
     *   header will be set as "application/json".
     * - [[FORMAT_JSONP]]: the data will be converted into JSONP format, and the "Content-Type"
     *   header will be set as "text/javascript". Note that in this case `$data` must be an array
     *   with "data" and "callback" elements. The former refers to the actual data to be sent,
     *   while the latter refers to the name of the JavaScript callback.
     * - [[FORMAT_XML]]: the data will be converted into XML format. Please refer to [[XmlResponseFormatter]]
     *   for more details.
     *
     * You may customize the formatting process or support additional formats by configuring [[formatters]].
     * @see formatters
     */
    public $format = self::FORMAT_HTML;

    /**
     * @var mixed the original response data. When this is not null, it will be converted into [[content]]
     * according to [[format]] when the response is being sent out.
     * @see content
     */
    public $data;
    /**
     * @var string the response content. When [[data]] is not null, it will be converted into [[content]]
     * according to [[format]] when the response is being sent out.
     * @see data
     */
    public $content;

    /**
     * @var string the charset of the text response. If not set, it will use
     * the value of [[Application::charset]].
     */
    public $charset;

    /**
     * @var integer the HTTP status code to send with the response.
     */
    private $_statusCode = 200;

    /**
     * @var string the HTTP status description that comes together with the status code.
     * @see httpStatuses
     */
    public $statusText = 'OK';
    /**
     * @var string the version of the HTTP protocol to use. If not set, it will be determined via `$_SERVER['SERVER_PROTOCOL']`,
     * or '1.1' if that is not available.
     */
    public $version = '1.1';

    /**
     * @var bool
     */
    private $_isHeadersSent = false;

    /**
     * @var bool
     */
    private $_isContentSent = false;

    protected $_isSent = false;

    private $closed = false;

    private $writable = true;

    /**
     * @var Connection
     */
    private $_connection;


    /**
     * @var bool
     */
    private $chunkedEncoding = true;

    /**
     * @var HeaderCollection|null
     */
    protected $_headers;

    /**
     * @var CookieCollection|null
     */
    protected $_cookies;


    /**
     * @var resource|array the stream to be sent. This can be a stream handle or an array of stream handle,
     * the begin position and the end position. Note that when this property is set, the [[data]] and [[content]]
     * properties will be ignored by [[send()]].
     */
    public $stream;

    public function __construct(Connection $connection, array $config = [])
    {
        $this->_connection = $connection;
        parent::__construct($config);
    }

    /**
     *
     */
    public function init()
    {
        $this->_connection->on(Connection::EVENT_END, function () {
            $this->close();
        });

        $this->_connection->on(Connection::EVENT_ERROR, function ($event) {
            $this->trigger(static::EVENT_ERROR, $event);
            $this->close();
        });

        $this->_connection->on(Connection::EVENT_DRAIN, function () {
            $this->trigger(static::EVENT_DRAIN);
        });

        if ($this->charset === null) {
            $this->charset = Friday::$app->charset;
        }
        $this->formatters = array_merge($this->defaultFormatters(), $this->formatters);
    }

    /**
     * @return array the formatters that are supported by default
     */
    protected function defaultFormatters()
    {
        return [
            self::FORMAT_HTML => 'Friday\Web\HtmlResponseFormatter',
            self::FORMAT_XML => 'Friday\Web\XmlResponseFormatter',
            self::FORMAT_JSON => 'Friday\Web\JsonResponseFormatter',
            self::FORMAT_JSONP => [
                'class' => 'Friday\web\JsonResponseFormatter',
                'useJsonp' => true,
            ],
        ];
    }

    /**
     * @return bool
     */
    protected function isWritable()
    {
        return $this->writable;
    }

    /**
     * @throws \Exception
     */
    protected function writeContinue()
    {
        if ($this->_isHeadersSent) {
            throw new \Exception('Response head has already been written.');
        }

        $this->_connection->write("HTTP/1.1 100 Continue\r\n\r\n");
    }


    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    protected function write($data)
    {
        if (!$this->_isHeadersSent) {

            throw new \Exception('Response head has not yet been written.');
        }

        if ($this->chunkedEncoding) {
            $len = strlen($data);
            $chunk = dechex($len) . "\r\n" . $data . "\r\n";
            $flushed = $this->_connection->write($chunk);
        } else {
            $flushed = $this->_connection->write($data);
        }

        return $flushed;
    }

    /**
     * @param null $data
     */
    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        if ($this->chunkedEncoding) {
            $this->_connection->write("0\r\n\r\n");
        }

        $this->trigger('end');
        $this->_connection->end();
    }

    /**
     *
     */
    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->writable = false;
        $this->trigger(static::EVENT_CLOSE);
        $this->_connection->close();
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @param bool $finishAfterSend
     * @return Awaitable
     */
    public function send(bool $finishAfterSend = true) : Awaitable
    {
        $deferred = new Deferred();

        if($this->connectionContext === null){
            $deferred->exception(new RuntimeException('Connection content is NULL.'));
        } else {
            $this->connectionContext->task(function () use ($deferred, $finishAfterSend) {
                if ($this->_isSent) {
                    $deferred->exception(new RuntimeException('Response already sanded.'));
                } else {
                    $this->_isSent = true;

                    try {
                        $this->trigger(self::EVENT_BEFORE_SEND);
                        $this->prepare()
                            ->await(function (ResultOrExceptionWrapperInterface $result) use ($deferred, $finishAfterSend){
                                if($result->isSucceeded()) {
                                    $this->trigger(self::EVENT_AFTER_PREPARE);

                                    $this->sendHeaders()->await(function (ResultOrExceptionWrapperInterface $result) use ($deferred, $finishAfterSend) {
                                        if($result->isSucceeded()) {
                                            $this->sendContent()->await(function (ResultOrExceptionWrapperInterface $result) use ($deferred, $finishAfterSend) {
                                                if($result->isSucceeded()) {
                                                    if ($finishAfterSend) {
                                                        $this->connectionContext->finish();
                                                    }
                                                    $deferred->result();
                                                } else {
                                                    if ($finishAfterSend) {
                                                        $this->connectionContext->finish();
                                                    }

                                                    $deferred->exception($result->getException());
                                                }
                                            }, true) ;
                                        } else {
                                            if ($finishAfterSend) {
                                                $this->connectionContext->finish();
                                            }

                                            $deferred->exception($result->getException());
                                        }
                                    }, true);
                                } else {
                                    if ($finishAfterSend) {
                                        $this->connectionContext->finish();
                                    }

                                    $deferred->exception($result->getException());
                                }
                            }, true);
                    } catch (Throwable $throwable) {
                        if ($finishAfterSend) {
                            $this->connectionContext->finish();
                        }

                        $deferred->exception($throwable);
                    }
                }
            });
        }


        return $deferred->awaitable();
    }

    protected function afterPrepare(){

    }
    /**
     * Prepares for sending the response.
     * The default implementation will convert [[data]] into [[content]] and set headers accordingly.
     * @return Awaitable
     */
    protected function prepare() : Awaitable
    {
        $deferred = new Deferred();

        $this->connectionContext->task(function () use ($deferred) {
            if (isset($this->formatters[$this->format])) {
                $formatter = $this->formatters[$this->format];
                if (!is_object($formatter)) {
                    $this->formatters[$this->format] = $formatter = Friday::createObject($formatter);
                }
                if ($formatter instanceof ResponseFormatterInterface) {
                    $formatter->format($this);
                } else {
                    $deferred->exception(new InvalidConfigException("The '{$this->format}' response formatter is invalid. It must implement the ResponseFormatterInterface."));
                    return;
                }
            } elseif ($this->format === self::FORMAT_RAW) {
                if ($this->data !== null) {
                    $this->content = $this->data;
                }
            } else {
                $deferred->exception(new InvalidConfigException("Unsupported response format: {$this->format}"));
                return;
            }

            if (is_array($this->content)) {
                $deferred->exception(new InvalidArgumentException('Response content must not be an array.'));
            } elseif (is_object($this->content)) {
                if (method_exists($this->content, '__toString')) {
                    $this->content = $this->content->__toString();
                } else {
                    $deferred->exception(new InvalidArgumentException('Response content must be a string or an object implementing __toString().'));
                    return;
                }
            }
            $deferred->result();
        });

        return $deferred->awaitable();
    }

    /**
     * Returns the header collection.
     * The header collection contains the currently registered HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection;
        }
        return $this->_headers;
    }

    /**
     * Returns the cookie collection.
     * Through the returned cookie collection, you add or remove cookies as follows,
     *
     * ```php
     * // add a cookie
     * $response->cookies->add(new Cookie([
     *     'name' => $name,
     *     'value' => $value,
     * ]);
     *
     * // remove a cookie
     * $response->cookies->remove('name');
     * // alternatively
     * unset($response->cookies['name']);
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection;
        }
        return $this->_cookies;
    }

    /**
     * Sends the response headers to the client
     *
     * @return Awaitable
     */
    protected function sendHeaders() : Awaitable
    {
        $deferred = new Deferred();
        $this->connectionContext->task(function () use ($deferred) {
            if ($this->_isHeadersSent) {
                $deferred->exception(new RuntimeException("Headers already sended."));
                return;
            }


            $statusCode = $this->getStatusCode();
            $data = "HTTP/{$this->version} {$statusCode} {$this->statusText}\r\n";

            $headers = $this->getHeaders();


            if ($headers->has('content-length')) {
                $this->chunkedEncoding = false;
            }
            if ($this->chunkedEncoding) {
                $headers->add('transfer-encoding', 'chunked');
            }
            foreach ($headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                foreach ($values as $value) {
                    $data .= "$name: $value\r\n";
                }
            }


            if ($this->_cookies !== null && $this->getCookies()->count > 0) {
                $request = $this->connectionContext->request;


                if ($request->enableCookieValidation) {
                    if ($request->cookieValidationKey == '') {
                        $deferred->exception(new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.'));
                        return;
                    }
                    $validationKey = $request->cookieValidationKey;
                }
                foreach ($this->getCookies() as $cookie) {
                    $value = $cookie->value;
                    if ($cookie->expire != 1 && isset($validationKey)) {
                        try {
                            $value = Friday::$app->security->hashData(serialize([$cookie->name, $value]), $validationKey);

                        } catch (Throwable $e) {
                            $deferred->exception($e);
                            return;
                        }
                    }
                    $data .= Cookie::createCookieHeader($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly) . "\r\n";
                }
            }

            $data .= "\r\n";
            $this->connection->write($data);

            $this->_isHeadersSent = true;

            $deferred->result();
        });

        return $deferred->awaitable();
    }

    /**
     * Sends the response content to the client
     */
    protected function sendContent() : Awaitable
    {
        $deferred = new Deferred();
        $this->connectionContext->task(function () use ($deferred) {
            if ($this->stream === null) {
                $this->write($this->content);
                $deferred->result();
                return;
            }

            $chunkSize = 8 * 1024 * 1024; // 8MB per chunk
            if (is_array($this->stream)) {
                list ($handle, $begin, $end) = $this->stream;
                fseek($handle, $begin);
                while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
                    if ($pos + $chunkSize > $end) {
                        $chunkSize = $end - $pos + 1;
                    }
                    $this->write(fread($handle, $chunkSize));
                    //flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
                }
                fclose($handle);
                $deferred->result();
            } elseif (is_resource($this->stream)) {
                while (!feof($this->stream)) {
                    $this->write(fread($this->stream, $chunkSize));
                }
                fclose($this->stream);

                $deferred->result();
            } elseif (is_object($this->stream)) {
                if ($this->stream instanceof Stream) {
                    $this->stream->on(Stream::EVENT_CONTENT, function (Friday\Stream\Event\ContentEvent $event) {
                        $this->write($event->content);
                    });

                    $this->stream->on(Stream::EVENT_END, function () use ($deferred) {
                        $deferred->result();
                    });

                    $this->stream->on(Stream::EVENT_ERROR, function () use ($deferred) {
                        $deferred->exception(new RuntimeException('Stream error'));
                    });
                } else {
                    $deferred->exception(new BadMethodCallException('I do not understand the answer configuration.'));
                }
            } else {
                $deferred->exception(new BadMethodCallException('I do not understand the answer configuration.'));
            }
        });
        return $deferred->awaitable();
    }

    /**
     * @return integer the HTTP status code to send with the response.
     */
    public function getStatusCode()
    {
        return $this->_statusCode;
    }

    /**
     * Sets the response status code.
     * This method will set the corresponding status text if `$text` is null.
     * @param integer $value the status code
     * @param string $text the status text. If not set, it will be set automatically based on the status code.
     * @throws InvalidArgumentException if the status code is invalid.
     *
     * @return static
     */
    public function setStatusCode($value, $text = null)
    {
        if ($value === null) {
            $value = 200;
        }
        $this->_statusCode = (int)$value;
        if ($this->getIsInvalid()) {
            throw new InvalidArgumentException("The HTTP status code is invalid: $value");
        }
        if ($text === null) {
            $this->statusText = isset(HttpCodes::$httpStatuses[$this->_statusCode]) ? HttpCodes::$httpStatuses[$this->_statusCode] : '';
        } else {
            $this->statusText = $text;
        }

        return $this;
    }

    /**
     * @return boolean whether this response has a valid [[statusCode]].
     */
    public function getIsInvalid()
    {
        return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
    }

    /**
     * @return boolean whether this response is informational
     */
    public function getIsInformational()
    {
        return $this->getStatusCode() >= 100 && $this->getStatusCode() < 200;
    }

    /**
     * @return boolean whether this response is successful
     */
    public function getIsSuccessful()
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    /**
     * @return boolean whether this response is a redirection
     */
    public function getIsRedirection()
    {
        return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
    }

    /**
     * @return boolean whether this response indicates a client error
     */
    public function getIsClientError()
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    /**
     * @return boolean whether this response indicates a server error
     */
    public function getIsServerError()
    {
        return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
    }

    /**
     * @return boolean whether this response is OK
     */
    public function getIsOk()
    {
        return $this->getStatusCode() == 200;
    }

    /**
     * @return boolean whether this response indicates the current request is forbidden
     */
    public function getIsForbidden()
    {
        return $this->getStatusCode() == 403;
    }

    /**
     * @return boolean whether this response indicates the currently requested resource is not found
     */
    public function getIsNotFound()
    {
        return $this->getStatusCode() == 404;
    }

    /**
     * @return boolean whether this response is empty
     */
    public function getIsEmpty()
    {
        return in_array($this->getStatusCode(), [201, 204, 304]);
    }


    /**
     * Redirects the browser to the specified URL.
     *
     * This method adds a "Location" header to the current response. Note that it does not send out
     * the header until [[send()]] is called. In a controller action you may use this method as follows:
     *
     * ```php
     * return Yii::$app->getResponse()->redirect($url);
     * ```
     *
     * In other places, if you want to send out the "Location" header immediately, you should use
     * the following code:
     *
     * ```php
     * Yii::$app->getResponse()->redirect($url)->send();
     * return;
     * ```
     *
     * In AJAX mode, this normally will not work as expected unless there are some
     * client-side JavaScript code handling the redirection. To help achieve this goal,
     * this method will send out a "X-Redirect" header instead of "Location".
     *
     * If you use the "yii" JavaScript module, it will handle the AJAX redirection as
     * described above. Otherwise, you should write the following JavaScript code to
     * handle the redirection:
     *
     * ```javascript
     * $document.ajaxComplete(function (event, xhr, settings) {
     *     var url = xhr.getResponseHeader('X-Redirect');
     *     if (url) {
     *         window.location = url;
     *     }
     * });
     * ```
     *
     * @param string|array $url the URL to be redirected to. This can be in one of the following formats:
     *
     * - a string representing a URL (e.g. "http://example.com")
     * - a string representing a URL alias (e.g. "@example.com")
     * - an array in the format of `[$route, ...name-value pairs...]` (e.g. `['site/index', 'ref' => 1]`).
     *   Note that the route is with respect to the whole application, instead of relative to a controller or module.
     *   [[Url::to()]] will be used to convert the array into a URL.
     *
     * Any relative URL will be converted into an absolute one by prepending it with the host info
     * of the current request.
     *
     * @param integer $statusCode the HTTP status code. Defaults to 302.
     * See <http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html>
     * for details about HTTP status code
     * @param boolean $checkAjax whether to specially handle AJAX (and PJAX) requests. Defaults to true,
     * meaning if the current request is an AJAX or PJAX request, then calling this method will cause the browser
     * to redirect to the given URL. If this is false, a `Location` header will be sent, which when received as
     * an AJAX/PJAX response, may NOT cause browser redirection.
     * Takes effect only when request header `X-Ie-Redirect-Compatibility` is absent.
     * @return $this the response object itself
     */
    public function redirect($url, $statusCode = 302, $checkAjax = true)
    {
        if (is_array($url) && isset($url[0])) {
            // ensure the route is absolute
            $url[0] = '/' . ltrim($url[0], '/');
        }
        $request = $this->connectionContext->request;
        $url = Url::to($url);
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = $request->getHostInfo() . $url;
        }

        if ($checkAjax) {
            if ($request->getIsAjax()) {
                if ($request->getHeaders()->get('X-Ie-Redirect-Compatibility') !== null && $statusCode === 302) {
                    // Ajax 302 redirect in IE does not work. Change status code to 200. See https://github.com/yiisoft/yii2/issues/9670
                    $statusCode = 200;
                }
                if ($request->getIsPjax()) {
                    $this->getHeaders()->set('X-Pjax-Url', $url);
                } else {
                    $this->getHeaders()->set('X-Redirect', $url);
                }
            } else {
                $this->getHeaders()->set('Location', $url);
            }
        } else {
            $this->getHeaders()->set('Location', $url);
        }

        $this->setStatusCode($statusCode);

        return $this;
    }

    /**
     * Determines the HTTP range given in the request.
     * @param integer $fileSize the size of the file that will be used to validate the requested HTTP range.
     * @return array|boolean the range (begin, end), or false if the range request is invalid.
     */
    protected function getHttpRange($fileSize)
    {
        $range = $this->getRequest()->getHeaders()->get('range');
        if ($range === null || $range === '-') {
            return [0, $fileSize - 1];
        }
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches)) {
            return false;
        }
        if ($matches[1] === '') {
            $start = $fileSize - $matches[2];
            $end = $fileSize - 1;
        } elseif ($matches[2] !== '') {
            $start = $matches[1];
            $end = $matches[2];
            if ($end >= $fileSize) {
                $end = $fileSize - 1;
            }
        } else {
            $start = $matches[1];
            $end = $fileSize - 1;
        }
        if ($start < 0 || $start > $end) {
            return false;
        } else {
            return [$start, $end];
        }
    }

    /**
     * Sets a default set of HTTP headers for file downloading purpose.
     * @param string $attachmentName the attachment file name
     * @param string $mimeType the MIME type for the response. If null, `Content-Type` header will NOT be set.
     * @param boolean $inline whether the browser should open the file within the browser window. Defaults to false,
     * meaning a download dialog will pop up.
     * @param integer $contentLength the byte length of the file being downloaded. If null, `Content-Length` header will NOT be set.
     * @return $this the response object itself
     */
    public function setDownloadHeaders($attachmentName, $mimeType = null, $inline = false, $contentLength = null)
    {
        $headers = $this->getHeaders();

        if($attachmentName !== false){
            $disposition = $inline ? 'inline' : 'attachment';
            $headers->setDefault('Pragma', 'public')
                ->setDefault('Accept-Ranges', 'bytes')
                ->setDefault('Expires', '0')
                ->setDefault('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                ->setDefault('Content-Disposition', "$disposition; filename=\"$attachmentName\"");

        }

        if ($mimeType !== null) {
            $headers->setDefault('Content-Type', $mimeType);
        }

        if ($contentLength !== null) {
            $headers->setDefault('Content-Length', $contentLength);
        }

        return $this;
    }

    /**
     * Sends the specified stream as a file to the browser.
     *
     * Note that this method only prepares the response for file sending. The file is not sent
     * until [[send()]] is called explicitly or implicitly. The latter is done after you return from a controller action.
     *
     * @param resource $handle the handle of the stream to be sent.
     * @param string $attachmentName the file name shown to the user.
     * @param array $options additional options for sending the file. The following options are supported:
     *
     *  - `mimeType`: the MIME type of the content. Defaults to 'application/octet-stream'.
     *  - `inline`: boolean, whether the browser should open the file within the browser window. Defaults to false,
     *    meaning a download dialog will pop up.
     *  - `fileSize`: the size of the content to stream this is useful when size of the content is known
     *    and the content is not seekable. Defaults to content size using `ftell()`.
     *    This option is available since version 2.0.4.
     *
     * @return $this the response object itself
     * @throws HttpException if the requested range cannot be satisfied.
     */
    public function sendStreamAsFile($handle, $attachmentName, $options = [])
    {
        $headers = $this->getHeaders();
        if (isset($options['fileSize'])) {
            $fileSize = $options['fileSize'];
        } else {
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
        }

        $range = $this->getHttpRange($fileSize);
        if ($range === false) {
            $headers->set('Content-Range', "bytes */$fileSize");
            throw new HttpException(416, 'Requested range not satisfiable');
        }

        list($begin, $end) = $range;
        if ($begin != 0 || $end != $fileSize - 1) {
            $this->setStatusCode(206);
            $headers->set('Content-Range', "bytes $begin-$end/$fileSize");
        } else {
            $this->setStatusCode(200);
        }

        $mimeType = isset($options['mimeType']) ? $options['mimeType'] : 'application/octet-stream';

        $this->setDownloadHeaders($attachmentName, $mimeType, !empty($options['inline']), $end - $begin + 1);


        $this->format = self::FORMAT_RAW;
        $this->stream = [$handle, $begin, $end];

        return $this;
    }
    /**
     * Sends a file to the browser.
     *
     * Note that this method only prepares the response for file sending. The file is not sent
     * until [[send()]] is called explicitly or implicitly. The latter is done after you return from a controller action.
     *
     * @param string $filePath the path of the file to be sent.
     * @param string $attachmentName the file name shown to the user. If null, it will be determined from `$filePath`.
     * @param array $options additional options for sending the file. The following options are supported:
     *
     *  - `mimeType`: the MIME type of the content. If not set, it will be guessed based on `$filePath`
     *  - `inline`: boolean, whether the browser should open the file within the browser window. Defaults to false,
     *    meaning a download dialog will pop up.
     *
     * @return $this the response object itself
     */
    public function sendFile($filePath, $attachmentName = null, $options = [])
    {
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = FileHelper::getMimeTypeByExtension($filePath);
        }
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }
        $handle = fopen($filePath, 'rb');
        $this->sendStreamAsFile($handle, $attachmentName, $options);

        return $this;
    }

}
