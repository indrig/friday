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

    private $_get = [];

    private $_post = [];

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

    /**
     * @param $rawBody
     * @return $this
     */
    public function setRawBody($rawBody){
        $this->_rawBody = $rawBody;
        return $this;
    }

    /**
     * @param $post
     * @return $this
     */
    public function setPost(array $post){
        $this->_post = $post;
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
            $contentTypeValues = explode(';', $this->_contentType);
            $contentType       = reset($contentTypeValues);

            if($contentType === 'application/x-www-form-urlencoded') {
                parse_str($this->_rawBody, $arr);
                $this->_post = $arr;
            } elseif ($contentType === 'multipart/form-data'){
                $post = [];

                if(preg_match('/boundary=(.*)$/', $this->_contentType, $matches)) {
                    $boundary = $matches[1];

                    // split content by boundary and get rid of last -- element
                    $blocks = preg_split("/-+$boundary/", $this->_rawBody);

                    array_pop($blocks);

                    // loop data blocks
                    foreach ($blocks as $id => $block)
                    {
                        if (empty($block))
                            continue;

                        // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

                        //TODO: add files and array support

                        // parse uploaded files
                        if (strpos($block, 'application/octet-stream') !== FALSE)
                        {
                            // match "name", then everything after "stream" (optional) except for prepending newlines
                            //preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                        }
                        // parse all other fields
                        else
                        {
                            // match "name" and optional value in between newline sequences
                            if(preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches)) {
                               // $post[$matches[1]] = $matches[2];


                               // parse_str($matches[0].'=' .$matches[2], $a);
                               // var_dump([$matches[0].'=' .$matches[2]]);
                            }
                        }

                    }
                }
                //var_dump($post);

                $this->_post = $post;
            } else {
                $this->_post = [];
            }
        }

        if($name === null) {
            return $this->_post;
        } else {
            return isset($this->_post[$name]) ? $this->_post[$name] : $defaultValue;
        }
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
                    } else {
                        $request->_path           = $requestUriExplodes[0];
                        $request->_queryString    = '';
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
