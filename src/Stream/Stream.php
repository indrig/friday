<?php
namespace Friday\Stream;

use Friday;
use Friday\Base\Component;

use Friday\Base\Exception\InvalidArgumentException;
use Friday\Stream\Event\Event;
use Friday\Stream\Event\ContentEvent;
use Friday\Stream\Event\ErrorEvent;
use Friday\Base\Exception\RuntimeException;

/**
 * Class Stream
 * @package Friday\Stream
 */
class Stream extends Component implements DuplexStreamInterface
{
    const EVENT_ERROR   = 'error';
    const EVENT_CONTENT    = 'content';
    const EVENT_CLOSE   = 'close';
    const EVENT_END     = 'end';
    const EVENT_DRAIN     = 'drain';

    /**
     * @var int
     */
    public $bufferSize = 4096;

    /**
     * @var resource
     */
    public $stream;

    /**
     * @var bool
     */
    protected $readable = true;

    /**
     * @var bool
     */
    protected $writable = true;

    /**
     * @var bool
     */
    protected $closing = false;

    /**
     * @var Buffer
     */
    protected $buffer;

    public function init(){
        if (!is_resource($this->stream) || get_resource_type($this->stream) !== "stream") {
            throw new InvalidArgumentException('Stream parameter must be a valid stream resource');
        }

        stream_set_blocking($this->stream, 0);

        // Use unbuffered read operations on the underlying stream resource.
        // Reading chunks from the stream may otherwise leave unread bytes in
        // PHP's stream buffers which some event loop implementations do not
        // trigger events on (edge triggered).
        // This does not affect the default event loop implementation (level
        // triggered), so we can ignore platforms not supporting this (HHVM).
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->stream, 0);
        }

        $this->buffer = new Buffer(['stream' => $this->stream]);
        $this->buffer->on(Buffer::EVENT_ERROR, function ($error) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => $error
            ]));
            $this->close();
        });

        $this->buffer->on(Buffer::EVENT_DRAIN, function ()  {
            $this->trigger(static::EVENT_DRAIN, new Event());
        });

        $this->resume();
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function pause()
    {
        $this->loop->removeReadStream($this->stream);
    }

    public function resume()
    {
        if ($this->readable) {
            Friday::$app->runLoop->addReadStream($this->stream, array($this, 'handleData'));
        }
    }

    public function write($data)
    {
        if (!$this->writable) {
            return false;
        }

        return $this->buffer->write($data);
    }

    public function close()
    {
        if (!$this->writable && !$this->closing) {
            return;
        }

        $this->closing = false;

        $this->readable = false;
        $this->writable = false;

        $this->trigger(static::EVENT_END, new Event());
        $this->trigger(static::EVENT_CLOSE, new Event());

        Friday::$app->runLoop->removeStream($this->stream);


        $this->handleClose();
    }

    public function end($data = null)
    {
        if (!$this->writable) {
            return;
        }

        $this->closing = true;

        $this->readable = false;
        $this->writable = false;

        $this->buffer->on('close', array($this, 'close'));

        $this->buffer->end($data);
    }

    /**
     * @param WritableStreamInterface $destination
     * @param array $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $destination, array $options = []) : WritableStreamInterface
    {
        Util::pipe($this, $destination, $options);

        return $destination;
    }

    public function handleData($stream)
    {
        $error = null;
        set_error_handler(function ($errNo, $errStr, $errFile, $errLine) use (&$error) {
            $error = new Friday\Base\Exception\RuntimeException(
                $errStr,
                0,
                $errNo,
                $errFile,
                $errLine
            );
        });

        $data = fread($stream, $this->bufferSize);

        restore_error_handler();

        if ($error !== null) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => $error
            ]));
            $this->close();
            return;
        }

        if ($data !== '') {
            $this->trigger('data', new ContentEvent([
                'content' => $data
            ]));
        }

        if (!is_resource($stream) || feof($stream)) {
            $this->end();
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function getBuffer()
    {
        return $this->buffer;
    }
}
