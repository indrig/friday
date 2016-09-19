<?php
namespace Friday\Stream;

use Friday;
use Friday\Base\Component;
use Friday\Stream\Event\Event;
use Friday\Stream\Event\ErrorEvent;
use Friday\Base\Exception\RuntimeException;

/**
 * Class Buffer
 * @package Friday\Stream
 */
class Buffer extends Component implements WritableStreamInterface
{
    const EVENT_FULL_DRAIN  = 'full-drain';
    public $stream;
    public $listening = false;
    public $softLimit = 2048;
    private $writable = true;

    private $data = '';
    private $lastError;

    /**
     *
     */
    public function init(){
        $this->lastErrorFlush();

    }

    /**
     * @return bool
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * @param $data
     * @return bool
     */
    public function write($data)
    {
        if (!$this->writable) {
            return false;
        }

        $this->data .= $data;

        if (!$this->listening && $this->data !== '') {
            $this->listening = true;

            Friday::$app->runLoop->addWriteStream($this->stream, array($this, 'handleWrite'));
        }

        $belowSoftLimit = strlen($this->data) < $this->softLimit;

        return $belowSoftLimit;
    }

    /**
     * @param null $data
     */
    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->writable = false;

        if ($this->listening) {
            $this->on('full-drain', array($this, 'close'));
        } else {
            $this->close();
        }
    }

    /**
     *
     */
    public function close()
    {
        $this->writable = false;
        $this->listening = false;
        $this->data = '';

        $this->trigger(static::EVENT_CLOSE, new Event());
    }

    /**
     *
     */
    public function handleWrite()
    {
        if (!is_resource($this->stream)) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new RuntimeException('Tried to write to invalid stream.')
            ]));

            return;
        }

        $this->lastErrorFlush();

        set_error_handler(array($this, 'errorHandler'));

        $sent = fwrite($this->stream, $this->data);

        restore_error_handler();

        // Only report errors if *nothing* could be sent.
        // Any hard (permanent) error will fail to send any data at all.
        // Sending excessive amounts of data will only flush *some* data and then
        // report a temporary error (EAGAIN) which we do not raise here in order
        // to keep the stream open for further tries to write.
        // Should this turn out to be a permanent error later, it will eventually
        // send *nothing* and we can detect this.
        if ($sent === 0 && $this->lastError['number'] > 0) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new RuntimeException(
                    $this->lastError['message'],
                    0,
                    $this->lastError['number'],
                    $this->lastError['file'],
                    $this->lastError['line']
                )
            ]));

            return;
        }

        if ($sent === 0) {
            $this->trigger(static::EVENT_ERROR, new ErrorEvent([
                'error' => new RuntimeException('Send failed')
            ]));
            return;
        }

        $len = strlen($this->data);
        $this->data = (string) substr($this->data, $sent);

        if ($len >= $this->softLimit && $len - $sent < $this->softLimit) {
            $this->trigger(static::EVENT_DRAIN, new Event());
        }

        if (0 === strlen($this->data)) {
            Friday::$app->runLoop->removeWriteStream($this->stream);
            $this->listening = false;

            $this->trigger(static::EVENT_FULL_DRAIN, new Event());
        }
    }

    private function errorHandler($errNo, $errStr, $errFile, $errLine)
    {
        $this->lastError['number']  = $errNo;
        $this->lastError['message'] = $errStr;
        $this->lastError['file']    = $errFile;
        $this->lastError['line']    = $errLine;
    }

    private function lastErrorFlush() {
        $this->lastError = array(
            'number'  => 0,
            'message' => '',
            'file'    => '',
            'line'    => 0,
        );
    }
}
