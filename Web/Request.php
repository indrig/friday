<?php
namespace Friday\Web;

use Evenement\EventEmitter;
use Friday\Base\Component;
use Friday\Base\Exception\RuntimeException;
use Friday\Stream\ReadableStreamInterface;
use Friday\Stream\WritableStreamInterface;
use Friday\Stream\Util;

/**
 * Class Request
 * @package Friday\Web
 *
 * @property HeaderCollection $headers
 *
 */
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

    public $_method;

    public $_path;

    public $_queryString;

    public $_httpVersion;

    private $_remoteAddress;

    private $_get;

    private $_post;

    private $_rawBody;

    private $_contentType;

    private $_contentLength;
    /**
     * @var
     */
    private $_requestUri;

    public function getMethod()
    {
        return $this->_method;
    }

    public function getPath()
    {
        return $this->_path;
    }

    public function getQueryString()
    {
        return $this->_queryString;
    }

    public function getHttpVersion()
    {
        return $this->_httpVersion;
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

    public function pipe(WritableStreamInterface $destination, array $options = [])
    {
        Util::pipe($this, $destination, $options);

        return $destination;
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

    /**
     * @param $rawBody
     * @return $this
     */
    public function setRawBody($rawBody){
        $this->_rawBody = $rawBody;
        return $this;
    }

    /**
     * Returns POST parameter with a given name. If name isn't specified, returns an array of all POST parameters.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return array|mixed
     */
    public function post($name = null, $defaultValue = null){
        if($this->_post === null) {
            $this->prepareRawBodyParams();
        }

        if($name === null) {
            return $this->_post;
        } else {
            return isset($this->_post[$name]) ? $this->_post[$name] : $defaultValue;
        }
    }

    /**
     * Обрабатывает запрос rawBody и разберает его на массивы _post и _files
     *
     * @return bool
     */
    protected function prepareRawBodyParams(){
        $contentTypeValues = explode(';', $this->_contentType);
        $contentType       = reset($contentTypeValues);

        if($contentType === 'application/x-www-form-urlencoded') {
            parse_str($this->_rawBody, $arr);
            $this->_post = $arr;

            return true;
        } elseif ($contentType === 'multipart/form-data'){
            $post = [];

            if(preg_match('/boundary=(.*)$/', $this->_contentType, $matches)) {
                $boundary = $matches[1];

                // split content by boundary and get rid of last -- element
                $blocks = preg_split("/-+$boundary/", $this->_rawBody);

                array_pop($blocks);

                // loop data blocks
                foreach ($blocks as $id => $part)
                {
                    if (empty($part))
                        continue;

                    // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

                    //TODO: add files and array support
                    $part = ltrim($part, "\r\n");
                    list($partHeadersString, $body) = explode("\r\n\r\n", $part, 2);

                    $parHeaders = explode("\r\n", $partHeadersString);
                    $headers = array();
                    foreach ($parHeaders as $headerLine) {
                        list($name, $value) = explode(':', $headerLine);
                        $headers[strtolower($name)] = ltrim($value, ' ');
                    }
                    if (isset($headers['content-disposition'])) {
                        if(preg_match(
                            '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
                            $headers['content-disposition'],
                            $matches
                        )) {
                            list(, $paramType, $paramName) = $matches;
                            $paramValue = mb_substr($body, 0, strlen($body) - 2, '8bit');

                            if(isset($matches[4])) {
                                //TODO: add process files
                            } else {
                                if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) {
                                    $paramName      = substr($paramName, 0, strpos($paramName, '['));
                                    $keys           = array_merge(array($paramName), $matches[1]);
                                } else {
                                    $keys           = array($paramName);
                                }

                                $target         = &$post;

                                foreach ($keys as $index) {
                                    if ($index === '') {
                                        if (isset($target)) {
                                            if (is_array($target)) {
                                                $intKeys        = array_filter(array_keys($target), 'is_int');
                                                $index  = count($intKeys) ? max($intKeys)+1 : 0;
                                            } else {
                                                $target = array($target);
                                                $index  = 1;
                                            }
                                        } else {
                                            $target         = array();
                                            $index          = 0;
                                        }
                                    } elseif (isset($target[$index]) && !is_array($target[$index])) {
                                        $target[$index] = array($target[$index]);
                                    }

                                    $target         = &$target[$index];
                                }

                                if (is_array($target)) {
                                    $target[]   = $paramValue;
                                } else {
                                    $target     = $paramValue;
                                }
                            }
                        }
                    }
                }
            }

            $this->_post = $post;

            return true;
        } else {
            $this->_post = [];
        }

        return false;
    }

    /**
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the GET parameter name.
     * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
     * @return array|mixed the GET parameter value
     * @see getBodyParam()
     */
    public function get($name = null, $defaultValue = null){
        if($this->_get === null) {
            $this->_get = [];
        }

        if($name === null) {
            return $this->_get;
        } else {
            return isset($this->_get[$name]) ? $this->_get[$name] : $defaultValue;
        }
    }

    /**
     * @param $content
     * @return static
     */
    public static function createFromRequestContent($content){

        $headers = explode("\r\n", $content);

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

                    $request->_method     = strtoupper($requestMethod);
                    $request->_requestUri = $requestUri;

                    if(count($requestUriExplodes) === 2) {
                        list($path, $queryString) = $requestUriExplodes;
                        parse_str($queryString, $query);

                        $request->_path = $path;
                        $request->_get  = $query;
                        $request->_queryString = $path . '?' .$query;
                    } else {
                        $request->_path           = $requestUriExplodes[0];
                        $request->_queryString    = $request->_path;
                    }
                } else {
                    throw new RuntimeException('Headers not content http start connection data');
                }
            } else {
                $headerExplodes = explode(':', $string, 2);
                if(count($headerExplodes) === 2) {
                    $headerCollection->add(trim($headerExplodes[0]), trim($headerExplodes[1]));
                }
            }
        }

        $request->_headers          = $headerCollection;

        $request->_contentLength    = $headerCollection->get('content-length');
        $request->_contentType      = $headerCollection->get('content-type');

        return $request;
    }
}
