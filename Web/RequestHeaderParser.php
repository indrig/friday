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

    private $buffer = '';
    private $maxSize = 4096;
    private $contentType;
    private $contentLength;
    private $request;
    private $headEndPost;
    private $headersReaded = false;
    private $contentReaded = false;
    public function feed(ContentEvent $event)
    {
        $content = $event->content;

        if (strlen($this->buffer) + strlen($content) > $this->maxSize) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new OverflowException("Maximum header size of {$this->maxSize} exceeded.")
            ]));
            return;
        }

        $this->buffer .= $content;

        if($this->headEndPost === null){
            if (false !== $pos = strpos($this->buffer, "\r\n\r\n")) {
                $this->headEndPost = $pos;

                try {
                    $request = $this->request = Request::createFromRequestContent(substr($this->buffer, 0, $pos));

                    $this->contentLength  = intval($request->headers->get('content-length', 0));
                    $this->contentType    = $request->headers->get('content-type');
                    $this->headersReaded = true;
                    if($this->contentLength === 0) {
                        $this->contentReaded = true;
                        $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                            'request' => $request
                        ]));
                    }
                } catch (Exception $exception) {
                    $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                        'error' => $exception
                    ]));
                }
            }
        }

        if($this->request !== null && $this->contentLength > 0) {
            $readedBytes = mb_strlen($this->buffer, '8bit') - $this->headEndPost - 4;

            if($readedBytes >= $this->contentLength) {
                $this->contentReaded = true;

                $this->request->setRawBody(mb_substr($this->buffer, $this->headEndPost + 4, $readedBytes));

                $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
                    'request' => $this->request
                ]));
            }
        }
    }

    protected function parseAndTriggerRequest()
    {
        $request = Request::createFromRequestContent($this->buffer);

        $this->trigger(static::EVENT_PARSED, new RequestParsedEvent([
            'request' => $request
        ]));
    }

}
