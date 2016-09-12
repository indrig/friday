<?php
namespace Friday\Web;

use Evenement\EventEmitter;
use Friday\Base\Component;
use Friday\Stream\ReadableStreamInterface;
use Friday\Stream\WritableStreamInterface;
use Friday\Stream\Util;

class Request extends Component implements ReadableStreamInterface
{
    /**
     * @var HeaderCollection
     */
    private $_headers;

    /**
     * @var CookieCollection Collection of request cookies.
     */
    private $_cookies;

    private $_readable = true;

    public $method;

    public $path;

    public $_queryString;

    public $httpVersion;

    private $_remoteAddress;

    private $_rawBody;

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


    public function expectsContinue()
    {
        return isset($this->headers['Expect']) && '100-continue' === $this->headers['Expect'];
    }

    public function isReadable()
    {
        return $this->_readable;
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
        $this->_readable = false;
        $this->trigger('end');
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    /**
     * @return null|HeaderCollection
     */
    public function getHeaders(){
        return $this->_headers;
    }


    /**
     * @return string
     */
    public function getRawBody(){
        return $this->_rawBody === null ? '' : $this->_rawBody;
    }

    public static function createFromRequestContent($content){
        list($headers, $rawBody) = explode("\r\n\r\n", $content, 2);

        $headers = explode("\r\n", $headers);

        $method = null;
        $path   = null;
        $query  = null;

        $request = new static();
        $headerCollection = new HeaderCollection();
        foreach ($headers as $lineNo => $string) {
            if($lineNo === 0) {
                if(preg_match('/^(\S+) (.*?) (\S+)$/m', $string, $matches)){
                    $requestMethod  = $matches[1];
                    $requestUri     = $matches[2];
                    $requestUriExplodes = explode('?', $requestUri, 2);

                    if(count($requestUriExplodes) === 2) {
                        list($path, $queryString) = $requestUriExplodes;
                        parse_str($queryString, $get);

                    } else {
                        $path           = $requestUriExplodes[0];
                        $queryString    = '';
                    }
                }
            } else {
                $headerExplodes = explode(':', $string, 2);
                if(count($headerExplodes) === 2) {
                    $headerCollection->add(trim($headerExplodes[0]), trim($headerExplodes[1]));
                }
            }
        }


        $request->_headers = $headerCollection;
        $request->_rawBody          = $rawBody;

        return $request;
    }
}
