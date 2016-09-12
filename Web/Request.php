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
    public $method;
    public $path;
    public $query;
    public $httpVersion;
    public $headers;

    // metadata, implicitly added externally
    public $remoteAddress;

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
        $this->trigger('pause');
    }

    public function resume()
    {
        $this->trigger('resume');
    }

    public function close()
    {
        $this->readable = false;
        $this->trigger('end');
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
