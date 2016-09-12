<?php
namespace Friday\Web;

use Evenement\EventEmitter;
use Friday\Base\Component;
use Friday\Stream\ReadableStreamInterface;
use Friday\Stream\WritableStreamInterface;
use Friday\Stream\Util;

class Request extends Component implements ReadableStreamInterface
{
    private $readable = true;
    private $method;
    private $path;
    private $query;
    private $httpVersion;
    private $headers;

    // metadata, implicitly added externally
    public $remoteAddress;

    public function __construct($method, $path, $query = array(), $httpVersion = '1.1', $headers = array())
    {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
        $this->httpVersion = $httpVersion;
        $this->headers = $headers;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getHttpVersion()
    {
        return $this->httpVersion;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function expectsContinue()
    {
        return isset($this->headers['Expect']) && '100-continue' === $this->headers['Expect'];
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function pause()
    {
        $this->emit('pause');
    }

    public function resume()
    {
        $this->emit('resume');
    }

    public function close()
    {
        $this->readable = false;
        $this->emit('end');
        $this->removeAllListeners();
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}