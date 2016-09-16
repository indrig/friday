<?php
namespace Friday\Stream;

/**
 * @event drain
 * @event error
 * @event close
 * @event pipe
 */
interface WritableStreamInterface
{
    public function isWritable();
    public function write($data);
    public function end($data = null);
    public function close();
}
