<?php
namespace Friday\SocketServer;

use Friday;
use Friday\Base\Component;
use Friday\SocketServer\Event\ConnectionEvent;
use Friday\SocketServer\Event\ErrorEvent;
use Friday\SocketServer\Exception\ConnectionException;

/**
 * Class Server
 *
 * @package Friday\SocketServer
 */
class Server extends Component
{
    const EVENT_ERROR = 'error';

    const EVENT_CONNECTION = 'connection';
    /**
     * @var resource
     */
    private $_socket;

    /**
     * @param int $port
     * @param string $host
     * @return $this
     */
    public function listen(int $port, string $host = '127.0.0.1')
    {
        if (strpos($host, ':') !== false) {
            // enclose IPv6 addresses in square brackets before appending port
            $host = '[' . $host . ']';
        }

        $this->_socket = @stream_socket_server("tcp://$host:$port", $errNo, $errStr);
        if (false === $this->_socket) {
            $message = "Could not bind to tcp://$host:$port: $errStr";
            throw new ConnectionException($message, $errNo);
        }
        stream_set_blocking($this->_socket, 0);
        Friday::$app->getLooper()->addReadStream($this->_socket, function ($socket) {
            $newSocket = @stream_socket_accept($socket);
            if (false === $newSocket) {
                $this->trigger(static::EVENT_ERROR, new ErrorEvent());
                return;
            }
            $this->handleConnection($newSocket);
        });

        return $this;
    }

    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $connection = $this->createConnection($socket);

        $this->trigger(static::EVENT_CONNECTION, new ConnectionEvent([
            'connection' => $connection
        ]));

    }

    public function getPort() : int
    {
        $name = stream_socket_get_name($this->master, false);

        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        Friday::$app->getLooper()->removeStream($this->_socket);
        fclose($this->_socket);
    }

    /**
     * @param resource $socket
     * @return Connection
     */
    public function createConnection($socket)
    {
        return new Connection(['stream' => $socket]);
    }
}
