<?php
namespace Friday\Web;

use Friday\Base\Component;
use Friday\SocketServer\Connection;
use Friday\Stream\WritableStreamInterface;

class Response extends Component  implements WritableStreamInterface
{
    private $closed = false;
    private $writable = true;
    /**
     * @var Connection
     */
    private $_connection;
    private $headWritten = false;
    private $chunkedEncoding = true;

    public function __construct(Connection $connection, array $config = [])
    {
        $this->_connection = $connection;
        parent::__construct($config);
    }

    /**
     *
     */
    public function init(){
        $this->_connection->on('end', function () {
            $this->close();
        });

        $this->_connection->on('error', function ($error) {
            $this->trigger('error', array($error, $this));
            $this->close();
        });

        $this->_connection->on('drain', function () {
            $this->trigger('drain');
        });
    }
    public function isWritable()
    {
        return $this->writable;
    }

    public function writeContinue()
    {
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        $this->_connection->write("HTTP/1.1 100 Continue\r\n\r\n");
    }

    public function writeHead($status = 200, array $headers = array())
    {
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        if (isset($headers['Content-Length'])) {
            $this->chunkedEncoding = false;
        }

        if ($this->chunkedEncoding) {
            $headers['Transfer-Encoding'] = 'chunked';
        }

        $data = $this->formatHead($status, $headers);
        $this->connection->write($data);

        $this->headWritten = true;
    }

    private function formatHead($status, array $headers)
    {
        $status = (int) $status;
        $text = isset(ResponseCodes::$statusTexts[$status]) ? ResponseCodes::$statusTexts[$status] : '';
        $data = "HTTP/1.1 $status $text\r\n";

        foreach ($headers as $name => $value) {
            $name = str_replace(array("\r", "\n"), '', $name);

            foreach ((array) $value as $val) {
                $val = str_replace(array("\r", "\n"), '', $val);

                $data .= "$name: $val\r\n";
            }
        }
        $data .= "\r\n";

        return $data;
    }

    public function write($data)
    {
        if (!$this->headWritten) {
            throw new \Exception('Response head has not yet been written.');
        }

        if ($this->chunkedEncoding) {
            $len = strlen($data);
            $chunk = dechex($len)."\r\n".$data."\r\n";
            $flushed = $this->_connection->write($chunk);
        } else {
            $flushed = $this->_connection->write($data);
        }

        return $flushed;
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        if ($this->chunkedEncoding) {
            $this->_connection->write("0\r\n\r\n");
        }

        $this->trigger('end');
        $this->_connection->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->writable = false;
        $this->trigger('close');
        $this->_connection->close();
    }


}
