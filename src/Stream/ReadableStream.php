<?php

namespace Friday\Stream;

use Friday\Base\EventTrait;

class ReadableStream implements ReadableStreamInterface
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

    public function pipe(WritableStreamInterface $destination, array $options = array())
    {
        Util::pipe($this, $destination, $options);

        return $destination;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->trigger(static::EVENT_END);
        $this->trigger(static::EVENT_CLOSE);

        $this->clearEvents();
    }
}
