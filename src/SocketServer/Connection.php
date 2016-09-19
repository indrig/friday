<?php
namespace Friday\SocketServer;

use Friday\SocketServer\Event\ContentEvent;
use Friday\Stream\Stream;

class Connection extends Stream
{

    public function handleData($stream)
    {
        // Socket is raw, not using fread as it's interceptable by filters
        // See issues #192, #209, and #240
        $content = stream_socket_recvfrom($stream, $this->bufferSize);
        if ('' !== $content && false !== $content) {
            $this->trigger(static::EVENT_CONTENT, new ContentEvent(['content' => $content]));
        }

        if ('' === $content || false === $content || !is_resource($stream) || feof($stream)) {
            $this->end();
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            // http://chat.stackoverflow.com/transcript/message/7727858#7727858
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            stream_set_blocking($this->stream, false);
            fclose($this->stream);
        }
    }

    public function getRemoteAddress()
    {
        return $this->parseAddress(stream_socket_get_name($this->stream, true));
    }

    private function parseAddress($address)
    {
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
    }
}
