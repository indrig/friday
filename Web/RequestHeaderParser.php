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
    /**
     * @var MultiPart
     */
    private $lastPart;
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
            if($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_URLENCODED) {
                $this->contentBytesReaded += mb_strlen($content, '8bit');

                if($this->contentLength <= $this->contentBytesReaded) {
                    $urlencoded = mb_substr($this->buffer, 0, $this->contentLength, '8bit');
                    parse_str($urlencoded, $array);

                    $this->request->setPost($array);

                    $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                        'request' => $this->request
                    ]));

                    return;
                }
            //Читаем частями
            } elseif ($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_MULTIPART_FORM) {
                $this->contentBytesReaded += mb_strlen($content, '8bit');

                if(isset($this->boundary)) {

                    $offset = 0;
                    $parts      = preg_split("/-+{$this->boundary}/", $this->buffer, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    $countParts = count($parts);

                    foreach ($parts as $partIndex => $part) {
                        list($body, $offset) = $part;

                        if($partIndex === 0) {
                            if($this->lastPart !== null) {
                                $part = $this->lastPart;
                                if($countParts > 1) {
                                    //Конец
                                    if($part->isFile){
                                        $this->buffer = mb_substr($this->buffer, $offset, null, '8bit');
                                    } else {
                                        $part->valueFromBuffer($part);
                                    }
                                } else {
                                    //Промежуток
                                    if($part->isFile){
                                        $this->buffer = mb_substr($this->buffer, $offset, null, '8bit');
                                    } else {
                                        //
                                    }
                                }
                            } else {

                            }
                        } elseif ($partIndex < $countParts - 1) {
                            //Целый
                            if((null !== $part = MultiPart::create($body, true)) && !$part->hasErrors){
                                if($part->isFile){

                                } else {
                                    $this->insetValueInArray($this->request->_post, $part->name, $part->value);
                                }

                                $lastPart = null;
                            }
                        } else {
                            if((null !== $part = MultiPart::create($body, false)) && !$part->hasErrors){
                                $this->lastPart = $part;

                                if($part->isFile){
                                    $this->buffer = mb_substr($this->buffer, $offset, null, '8bit');
                                } else {
                                    //$this->insetValueInArray($this->request->_post, $part->name, $part->value);
                                }
                            }
                        }
                    }

                    /* $parts = preg_split("/-+{$this->contentParams['boundary']}/", $this->buffer);

                     $countParts = count($parts);

                     foreach ($parts as $partIndex => $part) {
                         $part           = ltrim($part, "\r\n");
                         $partPackage    = explode("\r\n\r\n", $part, 2);

                         //Заголовки прочитаны
                         if(count($partPackage) === 2) {
                             //Получаем заголовки
                             $partHeadersString  = $partPackage[0];
                             $partHeaderLines         = explode("\r\n", $partHeadersString);

                             $this->lastPartHeaders = [];
                             foreach ($partHeaderLines as $headerLine) {
                                 list($name, $value) = explode(':', $headerLine);
                                 $this->lastPartHeaders[strtolower($name)] = ltrim($value, ' ');
                             }

                             if (isset($this->lastPartHeaders['content-disposition'])) {
                                 $disposition = $this->parseHeaderValue($this->lastPartHeaders['content-disposition']);
                                 if(isset($disposition['name'])){
                                     $paramName = $disposition['name'];
                                     if(isset($disposition['filename'])) {
                                         //Это фаил
                                         if($partIndex < $countParts - 1){
                                             //Получены все данные

                                             $this->lastPartHeaders = null;
                                         } else {
                                             //Получена часть данных
                                         }
                                     } else {
                                         if($partIndex < $countParts - 1){
                                             //Получены все данные
                                             $paramValue = mb_substr($partPackage[1], 0, mb_strlen($partPackage[1], '8bit') - 2, '8bit');
                                             $this->insetValueInArray($this->request->_post, $paramName, $paramValue);

                                             $this->lastPartHeaders = null;
                                         } else {
                                             //Получена часть данных
                                         }
                                     }
                                 }
                             }
                         }
                     }*/
                }

                if ($this->contentLength <= $this->contentBytesReaded) {
                    var_dump($this->request->_post);
                    $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                        'request' => $this->request
                    ]));
                }
                //Читаем все
                //} elseif ($this->inputProcessFormat === static::INPUT_PROCESS_FORMAT_RAW) {
            } else {
                $this->contentBytesReaded += mb_strlen($content, '8bit');

                if($this->contentLength <= $this->contentBytesReaded) {
                    $rawBody = mb_substr($this->buffer, 0, $this->contentLength, '8bit');

                    $this->request->setRawBody($rawBody);

                    $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                        'request' => $this->request
                    ]));
                }
                return;
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
