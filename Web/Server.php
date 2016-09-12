<?php
namespace Friday\Web;

use Friday\Web\Event\ErrorEvent;
use Friday\Web\Event\RequestEvent;
use Friday\Web\Event\RequestParsedEvent;
use Friday\SocketServer\Event\ConnectionEvent;
use Friday\Base\Component;
use Friday\SocketServer\Connection;
use Friday\SocketServer\Server as SocketServer;

class Server extends Component
{
    const EVENT_REQUEST = 'request';
    /**
     * @var SocketServer $_socket
     */
    private $_socket;

    private $_isRun = false;

    public $port = 8080;

    public $host = '127.0.0.1';


    public function init(){
        $this->_socket = new SocketServer();
        $this->_socket->on(SocketServer::EVENT_CONNECTION, function (ConnectionEvent $event) {

            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing
            $connection = $event->connection;

            $parser = new RequestHeaderParser();
            $parser->on(RequestHeaderParser::EVENT_PARSED, function (RequestParsedEvent $event) use ($connection, $parser) {
                // attach remote ip to the request as metadata

                $request = $event->request;
                $this->handleRequest($connection, $request);

                $connection->off(Connection::EVENT_CONTENT, array($parser, 'feed'));

                $connection->on('end', function () use ($request) {
                    $request->trigger('end');
                });
                $connection->on('data', function ($data) use ($request) {
                    $request->trigger('data', array($data));
                });
                $request->on('pause', function () use ($connection) {
                    $connection->trigger('pause');
                });
                $request->on('resume', function () use ($connection) {
                    $connection->trigger('resume');
                });
            })->on(RequestHeaderParser::EVENT_ERROR, function (ErrorEvent $event) use ($connection) {
                $connection->close();
            });

            $connection->on(Connection::EVENT_CONTENT, [$parser, 'feed']);
        });
    }

    public function handleRequest(Connection $connection, Request $request)
    {
        $response = new Response($connection);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }

        $this->trigger(Server::EVENT_REQUEST, new RequestEvent([
            'request' => $request,
            'response' => $response
        ]));
       // $request->emit('data', array($bodyBuffer));
    }

    public function run(){
        $this->_socket->listen($this->port, $this->host);
    }
}
