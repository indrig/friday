<?php

namespace Friday\Stream;

use Friday\Base\EventTrait;
use Friday\Stream\Event\PipeEvent;

class CompositeStream implements DuplexStreamInterface
{
    use EventTrait;
    /**
     * @var ReadableStream
     */
    protected $readable;

    /**
     * @var WritableStream
     */
    protected $writable;
    /**
     * @var ReadableStreamInterface
     */
    protected $pipeSource;

    public function __construct(ReadableStreamInterface $readable, WritableStreamInterface $writable)
    {
        $this->readable = $readable;
        $this->writable = $writable;

        Util::forwardEvents($this->readable, $this, array('data', 'end', 'error', 'close'));
        Util::forwardEvents($this->writable, $this, array('drain', 'error', 'close', 'pipe'));

        $this->readable->on(ReadableStreamInterface::EVENT_CLOSE, [$this, 'close']);
        $this->writable->on(ReadableStreamInterface::EVENT_CLOSE, [$this, 'close']);

        $this->on(static::EVENT_PIPE, array($this, 'handlePipeEvent'));
    }

    public function handlePipeEvent(PipeEvent $event)
    {
        $this->pipeSource = $event->source;
    }

    public function isReadable()
    {
        return $this->readable->isReadable();
    }

    public function pause()
    {
        if ($this->pipeSource) {
            $this->pipeSource->pause();
        }

        $this->readable->pause();
    }

    public function resume()
    {
        if ($this->pipeSource) {
            $this->pipeSource->resume();
        }

        $this->readable->resume();
    }

    public function pipe(WritableStreamInterface $destination, array $options = array()) : WritableStreamInterface
    {
        Util::pipe($this, $destination, $options);

        return $destination;
    }

    public function isWritable()
    {
        return $this->writable->isWritable();
    }

    public function write($data)
    {
        return $this->writable->write($data);
    }

    public function end($data = null)
    {
        $this->writable->end($data);
    }

    public function close()
    {
        $this->pipeSource = null;

        $this->readable->close();
        $this->writable->close();
    }
}
