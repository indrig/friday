<?php

namespace Friday\FileSystem;

use Friday\Base\EventTrait;
use Friday\Stream\ReadableStreamInterface;
use Friday\Stream\Util;
use Friday\Stream\WritableStreamInterface;

class ObjectStream implements ReadableStreamInterface, WritableStreamInterface
{
    use EventTrait;

    protected $closed = false;

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
    public function write($data)
    {
        $this->trigger('data', array($data, $this));
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->close();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->trigger('end', array($this));
        $this->trigger('close', array($this));
        //$this->removeAllListeners();
    }
}
