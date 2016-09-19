<?php

namespace Friday\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data
 * @event end
 * @event error
 * @event close
 */
interface ReadableStreamInterface extends StreamInterface
{
    /**
     * @return bool
     */
    public function isReadable();

    /**
     * @return void
     */
    public function pause();

    /**
     * @return void
     */
    public function resume();

    /**
     * @param WritableStreamInterface $destination
     * @param array $options
     * @return mixed
     */
    public function pipe(WritableStreamInterface $destination, array $options = []);

    /**
     * @return void
     */
    public function close();
}
