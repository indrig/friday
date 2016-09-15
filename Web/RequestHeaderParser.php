<?php
namespace Friday\Web;

use Exception;
use Friday\Helper\AliasHelper;
use Friday\Helper\FileHelper;
use Friday\Web\Event\ErrorEvent;
use Friday\Web\Event\RequestParsedEvent;
use Friday\SocketServer\Event\ContentEvent;
use Friday\Stream\Stream;
/**
 * @event headers
 * @event error
 */
class RequestHeaderParser extends Request
{
    const EVENT_ERROR = 'error';
    const EVENT_PARSED = 'parsed';

    const INPUT_PROCESS_FORMAT_NONE = 0;
    const INPUT_PROCESS_FORMAT_URLENCODED = 1;
    const INPUT_PROCESS_FORMAT_MULTIPART_FORM = 2;
    const INPUT_PROCESS_FORMAT_RAW = 3;

    private $inputProcessFormat;
    private $buffer = '';
    private $tmpBuffer = '';
    private $maxSize = 4096;
    private $contentType;
    private $contentLength;
    private $request;
    private $headEndPost;
    private $headersReaded = false;
    private $contentReaded = false;
    private $contentBytesReaded = 0;
    private $contentMineType    = '';
    private $contentParams      = [];
    private $boundary;

    public function feed(ContentEvent $event)
    {
        $content = $event->content;

       /* if (strlen($this->buffer) + strlen($content) > $this->maxSize) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new OverflowException("Maximum header size of {$this->maxSize} exceeded.")
            ]));
            return;
        }*/

        $this->buffer .= $content;

        if($this->headEndPost === null){


            if (false !== $pos = strpos($this->buffer, "\r\n\r\n")) {
                $this->headEndPost = $pos;

                try {
                    $request = $this->request = Request::createFromRequestContent(substr($this->buffer, 0, $pos));

                    $this->contentLength  = intval($request->headers->get('content-length', 0));
                    $this->contentType    = $request->headers->get('content-type');
                    $this->headersReaded  = true;

                    $this->buffer         = mb_substr($this->buffer, $pos+4, null, '8bit');
                    $content              = '';
                    if($this->contentType != null) {
                        $contentTypeArray = $this->parseHeaderValue($this->contentType);
                        $this->contentMineType = strtolower(reset($contentTypeArray));
                        $this->contentParams   = array_splice($contentTypeArray, 1);

                        if($this->contentMineType === 'application/x-www-form-urlencoded') {
                            $this->inputProcessFormat = static::INPUT_PROCESS_FORMAT_URLENCODED;
                        } elseif ($this->contentMineType === 'multipart/form-data'){
                            $this->inputProcessFormat = static::INPUT_PROCESS_FORMAT_MULTIPART_FORM;
                            if(preg_match('/^[\-A-Za-z0-9]+$/', $this->contentParams['boundary'])){
                                $this->boundary = $this->contentParams['boundary'];
                            }
                        } else {
                            $this->inputProcessFormat = static::INPUT_PROCESS_FORMAT_RAW;
                        }
                    }
                    if($this->contentLength === 0) {
                        $this->contentReaded = true;
                        $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                            'request' => $request
                        ]));
                        return;
                    } else {
                        $this->contentBytesReaded = mb_strlen($this->buffer, '8bit');
                    }
                } catch (Exception $exception) {
                    $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                        'error' => $exception
                    ]));
                    return;
                }
            }
        }

        if($this->headersReaded) {
            $this->contentBytesReaded += mb_strlen($content, '8bit');
            if ($this->contentLength <= $this->contentBytesReaded) {
                $this->request->_rawBody = mb_substr($this->buffer, 0, $this->contentLength, '8bit');

                if($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_URLENCODED) {

                    //
                    parse_str($this->request->_rawBody, $this->request->_post);

                } elseif ($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_MULTIPART_FORM) {

                    //TODO: user live parsing
                    $parts  = preg_split("/-+{$this->boundary}/", $this->buffer, -1,  PREG_SPLIT_NO_EMPTY);

                    foreach ($parts as $part) {
                        if(false === $partHeaderEndPosition = mb_strpos($part, "\r\n\r\n", 0, '8bit')){
                            continue;
                        }

                        $headersStrings    = mb_substr(ltrim($part), 0, $partHeaderEndPosition-2, '8bit');

                        $partHeaderLines   = explode("\r\n", $headersStrings);

                        $headers = [];
                        foreach ($partHeaderLines as $headerLine) {
                            list($name, $value) = explode(':', $headerLine, 2);
                            $headers[strtolower($name)] = ltrim($value, ' ');
                        }
                        if (isset($headers['content-disposition'])) {
                            $disposition = static::parseHeaderValue($headers['content-disposition']);

                            if (isset($disposition['name'])) {
                                $paramName = $disposition['name'];
                                $paramValue = mb_substr($part, $partHeaderEndPosition+4, -2, '8bit');
                                if(isset($disposition['filename'])) {
                                    //Это файил
                                    $file = new File();
                                    $file->filename = $disposition['filename'];
                                    if(isset($headers['content-type'])) {
                                        $contentType = static::parseHeaderValue($headers['content-type']);
                                        $file->contentType = reset($contentType);
                                    }
                                    $file->content = $paramValue;
                                    $file->size = mb_strlen($paramValue, '8bit');

                                    static::insetValueInArray($this->request->_files, $paramName, $file);

                                } else {
                                    //Это данные
                                    static::insetValueInArray($this->request->_post, $paramName, $paramValue);
                                }
                            }
                        }
                    }
                }

                $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                    'request' => $this->request
                ]));
            }
        }
    }

    protected function insetValueInArray(&$array, $paramName, &$paramValue){
        if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) {
            $paramName      = substr($paramName, 0, strpos($paramName, '['));
            $keys           = array_merge(array($paramName), $matches[1]);
        } else {
            $keys           = array($paramName);
        }

        $target         = &$array;

        foreach ($keys as $index) {
            if ($index === '') {
                if (isset($target)) {
                    if (is_array($target)) {
                        $intKeys        = array_filter(array_keys($target), 'is_int');
                        $index  = count($intKeys) ? max($intKeys)+1 : 0;
                    } else {
                        $target = array($target);
                        $index  = 1;
                    }
                } else {
                    $target         = array();
                    $index          = 0;
                }
            } elseif (isset($target[$index]) && !is_array($target[$index])) {
                $target[$index] = array($target[$index]);
            }

            $target         = &$target[$index];
        }

        if (is_array($target)) {
            $target[]   = $paramValue;
        } else {
            $target     = $paramValue;
        }
    }
    /**
     * @param $headerValue
     *
     * @return array
     */
    protected static function parseHeaderValue($headerValue){
        $result = [];
        $items = explode(';', $headerValue);

        foreach ($items as $index => $item){
            if($index === 0) {
                $result[] = $item;
            } else {
                $pair = explode('=', ltrim($item), 2);
                if(count($pair) === 2) {
                    $result[$pair[0]] = trim($pair[1], '"');
                }
            }
        }

        return $result;
    }

    /**
     *
     */
    protected function parseAndTriggerRequest()
    {
        $request = Request::createFromRequestContent($this->buffer);

        $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
            'request' => $request
        ]));
    }

}
