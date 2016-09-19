<?php
namespace Friday\Stream;

/**
 * @event drain
 * @event error
 * @event close
 * @event pipe
 */
interface WritableStreamInterface extends StreamInterface
{
    /**
     * @return bool
     */
    public function isWritable();

    /**
     * @param $data
     * @return bool
     */
    public function write($data);

    public function end($data = null);

    public function close();
}
