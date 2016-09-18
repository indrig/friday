<?php

namespace Friday\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data
 * @event end
 * @event error
 * @event close
 */
interface ReadableStreamInterface
{
    public function isReadable();
    public function pause();
    public function resume();
    public function pipe(WritableStreamInterface $dest, array $options = array());
    public function close();
}
