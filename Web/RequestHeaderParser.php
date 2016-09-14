<?php
namespace Friday\Web;

use Exception;
use Friday\Web\Event\ErrorEvent;
use Friday\Web\Event\RequestParsedEvent;
use Friday\Base\Component;
use Friday\SocketServer\Event\ContentEvent;
use Friday\Base\Exception\OverflowException;

/**
 * @event headers
 * @event error
 */
class RequestHeaderParser extends Component
{
    const EVENT_ERROR = 'error';
    const EVENT_PARSED = 'parsed';

    const INPUT_PROCESS_FORMAT_NONE = 0;
    const INPUT_PROCESS_FORMAT_URLENCODED = 1;
    const INPUT_PROCESS_FORMAT_MULTIPART_FORM = 2;
    const INPUT_PROCESS_FORMAT_RAW = 3;

    private $inputProcessFormat;
    private $buffer = '';
    private $maxSize = 4096;
    private $contentType;
    private $contentLength;
    private $request;
    private $headEndPost;
    private $headersReaded = false;
    private $contentReaded = false;
    private $contentBytesReaded = 0;
    private $contentMineType    = '';
    private $contentParams      = '';


    public function feed(ContentEvent $event)
    {
        $content = $event->content;

        if (strlen($this->buffer) + strlen($content) > $this->maxSize) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new OverflowException("Maximum header size of {$this->maxSize} exceeded.")
            ]));
            return;
        }



        if($this->headEndPost === null){
            $this->buffer .= $content;

            if (false !== $pos = strpos($this->buffer, "\r\n\r\n")) {
                $this->headEndPost = $pos;

                try {
                    $request = $this->request = Request::createFromRequestContent(substr($this->buffer, 0, $pos));

                    $this->contentLength  = intval($request->headers->get('content-length', 0));
                    $this->contentType    = $request->headers->get('content-type');
                    $this->headersReaded  = true;
                    $this->buffer         = mb_substr($this->buffer, $pos+4, null, '8bit');

                    if($this->contentType != null) {
                        $contentTypeArray = $this->parseHeaderValue($this->contentType);
                        $this->contentMineType = strtolower(reset($contentTypeArray));
                        $this->contentParams   = array_splice($contentTypeArray, 1);

                        if($this->contentMineType === 'application/x-www-form-urlencoded') {
                            $this->inputProcessFormat = static::INPUT_PROCESS_FORMAT_URLENCODED;
                        } elseif ($this->contentMineType === 'multipart/form-data'){
                            $this->inputProcessFormat = static::INPUT_PROCESS_FORMAT_MULTIPART_FORM;
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
            if($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_URLENCODED) {
                $this->buffer             .= $content;
                $this->contentBytesReaded = mb_strlen($content, '8bit');

                if($this->contentLength <= $this->contentBytesReaded) {
                    $urlencoded = mb_substr($this->buffer, 0, $this->contentLength, '8bit');
                    parse_str($urlencoded, $array);

                    $this->request->setPost($array);

                    var_dump($array);
                    $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                        'request' => $this->request
                    ]));

                    return;
                }
            //Читаем частями
            } elseif ($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_MULTIPART_FORM) {
                $this->buffer             .= $content;
                $this->contentBytesReaded = mb_strlen($content, '8bit');


            //Читаем все
            } elseif ($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_RAW) {
                $this->buffer             .= $content;
                $this->contentBytesReaded = mb_strlen($content, '8bit');

                $rawBody = mb_substr($this->buffer, 0, $this->contentLength, '8bit');

                $this->request->setRawBody($rawBody);

                $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                    'request' => $this->request
                ]));

                return;
            }
        }

    }

    /**
     * @param $headerValue
     *
     * @return array
     */
    protected function parseHeaderValue($headerValue){
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

    protected function parseAndTriggerRequest()
    {
        $request = Request::createFromRequestContent($this->buffer);

        $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
            'request' => $request
        ]));
    }

}
