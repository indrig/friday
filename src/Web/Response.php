<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Component;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Helper\Url;
use Friday\Promise\Deferred;
use Friday\Promise\PromiseInterface;
use Friday\SocketServer\Connection;
use Friday\Stream\Stream;
use Throwable;
use Friday\SocketServer\Event\ErrorEvent as SocketServerErrorEvent;
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

        $this->_connection->on(Connection::EVENT_ERROR, function (SocketServerErrorEvent $event) {
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
     * @return PromiseInterface
     */
    public function send(bool $finishAfterSend = true) : PromiseInterface
    {
        $deferred = new Deferred();

        $this->connectionContext->post(function () use ($deferred, $finishAfterSend) {
            if ($this->_isSent) {
                $deferred->reject();
            } else {
                $this->_isSent = true;

                try {

                    $this->trigger(self::EVENT_BEFORE_SEND);
                    $this->prepare()->then(function () use ($deferred, $finishAfterSend) {
                        $this->trigger(self::EVENT_AFTER_PREPARE);
                        $this->sendHeaders()->then(function () use ($deferred, $finishAfterSend) {
                            $this->sendContent()->then(function () use ($deferred, $finishAfterSend) {
                                $this->trigger(self::EVENT_AFTER_SEND);
                                if ($finishAfterSend) {
                                    $this->connectionContext->finish();
                                }

                                $deferred->resolve();
                            }, function ($throwable = null) use ($deferred, $finishAfterSend) {
                                if ($finishAfterSend) {
                                    $this->connectionContext->finish();
                                }

                                $deferred->reject($throwable);
                            });
                        }, function ($throwable = null) use ($deferred, $finishAfterSend) {
                            if ($finishAfterSend) {
                                $this->connectionContext->finish();
                            }
                            $deferred->reject($throwable);
                        });
                    }, function ($throwable = null) use ($deferred, $finishAfterSend) {
                        if ($finishAfterSend) {
                            $this->connectionContext->finish();
                        }

                        $deferred->reject($throwable);

                    });
                } catch (Throwable $throwable) {
                    if ($finishAfterSend) {
                        $this->connectionContext->finish();
                    }

                    $deferred->reject($throwable);
                }
            }
        });

        return $deferred->promise();
    }

    /**
     * Prepares for sending the response.
     * The default implementation will convert [[data]] into [[content]] and set headers accordingly.
     * @return Friday\Promise\PromiseInterface
     */
    protected function prepare()
    {
        $deferred = new Deferred();

        $this->connectionContext->post(function () use ($deferred) {
            if (isset($this->formatters[$this->format])) {
                $formatter = $this->formatters[$this->format];
                if (!is_object($formatter)) {
                    $this->formatters[$this->format] = $formatter = Friday::createObject($formatter);
                }
                if ($formatter instanceof ResponseFormatterInterface) {
                    $formatter->format($this);
                } else {
                    $deferred->reject(new InvalidConfigException("The '{$this->format}' response formatter is invalid. It must implement the ResponseFormatterInterface."));
                    return;
                }
            } elseif ($this->format === self::FORMAT_RAW) {
                if ($this->data !== null) {
                    $this->content = $this->data;
                }
            } else {
                $deferred->reject(new InvalidConfigException("Unsupported response format: {$this->format}"));
                return;
            }

            if (is_array($this->content)) {
                $deferred->reject(new InvalidArgumentException('Response content must not be an array.'));
            } elseif (is_object($this->content)) {
                if (method_exists($this->content, '__toString')) {
                    $this->content = $this->content->__toString();
                } else {
                    $deferred->reject(new InvalidArgumentException('Response content must be a string or an object implementing __toString().'));
                    return;
                }
            }
            $deferred->resolve();
        });

        return $deferred->promise();
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
     * @return Friday\Promise\PromiseInterface
     */
    protected function sendHeaders()
    {
        $deferred = new Deferred();
        $this->connectionContext->post(function () use ($deferred) {
            if ($this->_isHeadersSent) {
                $deferred->reject();
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
                        $deferred->reject(new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.'));
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
                            $deferred->reject();
                            return;
                        }
                    }
                    $data .= Cookie::createCookieHeader($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly) . "\r\n";
                }
            }

            $data .= "\r\n";
            $this->connection->write($data);

            $this->_isHeadersSent = true;

            $deferred->resolve();
        });

        return $deferred->promise();

    }

    /**
     * Sends the response content to the client
     */
    protected function sendContent()
    {
        $deferred = new Deferred();
        $this->connectionContext->post(function () use ($deferred) {
            if ($this->stream === null) {
                $this->write($this->content);
                $deferred->resolve();
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
                    echo fread($handle, $chunkSize);
                    flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
                }
                fclose($handle);
                $deferred->resolve();
            } elseif (is_resource($this->stream)) {
                while (!feof($this->stream)) {
                    $this->write(fread($this->stream, $chunkSize));
                }
                fclose($this->stream);

                $deferred->resolve();
            } elseif (is_object($this->stream)) {
                if ($this->stream instanceof Stream) {
                    $this->stream->on(Stream::EVENT_CONTENT, function (Friday\Stream\Event\ContentEvent $event) {
                        $this->write($event->content);
                    });

                    $this->stream->on(Stream::EVENT_END, function (Friday\Stream\Event\Event $event) use ($deferred) {
                        $deferred->resolve();
                    });

                    $this->stream->on(Stream::EVENT_ERROR, function (Friday\Stream\Event\ErrorEvent $event) use ($deferred) {
                        $deferred->reject();
                    });
                }
            } else {
                $deferred->reject();
            }
        });
        return $deferred->promise();
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

}
