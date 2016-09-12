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

    public function feed(ContentEvent $event)
    {
        $content = $event->content;

        if (strlen($this->buffer) + strlen($content) > $this->maxSize) {
            $this->trigger('error', array(new OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));

            return;
        }

        $this->buffer .= $content;

        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            try {
                $this->parseAndTriggerRequest();
            } catch (Exception $exception) {
                $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                    'error' => $exception
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
