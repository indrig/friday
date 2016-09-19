<?php

namespace Friday\Stream;


use Friday\Base\EventTrait;

class WritableStream implements WritableStreamInterface
{
    use EventTrait;

    protected $closed = false;

    public function write($data)
    {
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
        $this->trigger(static::EVENT_END);
        $this->trigger(static::EVENT_CLOSE);
    }
}
