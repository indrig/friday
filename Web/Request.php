<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Component;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Base\Exception\RuntimeException;
use Friday\Promise\Deferred;
use Friday\Stream\ReadableStreamInterface;
use Friday\Stream\WritableStreamInterface;
use Friday\Stream\Util;
use Friday\Web\HttpException\NotFoundHttpException;
use Throwable;

/**
 *
 * Class Request
 * @package Friday\Web
 *
 * @property HeaderCollection $headers
 * @property bool $isSecureConnection
 * @property null|string $host
 * @property ConnectionContext $connectionContext
 * @property bool $isAjax
 */
class Request extends Component implements ReadableStreamInterface
{
    /**
     * The name of the HTTP header for sending CSRF token.
     */
    const CSRF_HEADER = 'X-CSRF-Token';
    /**
     * The length of the CSRF token mask.
     */
    const CSRF_MASK_LENGTH = 8;

    /**
     * @var boolean whether to enable CSRF (Cross-Site Request Forgery) validation. Defaults to true.
     * When CSRF validation is enabled, forms submitted to an Yii Web application must be originated
     * from the same application. If not, a 400 HTTP exception will be raised.
     *
     * Note, this feature requires that the user client accepts cookie. Also, to use this feature,
     * forms submitted via POST method must contain a hidden input whose name is specified by [[csrfParam]].
     * You may use [[\yii\helpers\Html::beginForm()]] to generate his hidden input.
     *
     * In JavaScript, you may get the values of [[csrfParam]] and [[csrfToken]] via `yii.getCsrfParam()` and
     * `yii.getCsrfToken()`, respectively. The [[\yii\web\YiiAsset]] asset must be registered.
     * You also need to include CSRF meta tags in your pages by using [[\yii\helpers\Html::csrfMetaTags()]].
     *
     * @see Controller::enableCsrfValidation
     * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
     */
    public $enableCsrfValidation = true;
    /**
     * @var string the name of the token used to prevent CSRF. Defaults to '_csrf'.
     * This property is used only when [[enableCsrfValidation]] is true.
     */
    public $csrfParam = '_csrf';
    /**
     * @var array the configuration for creating the CSRF [[Cookie|cookie]]. This property is used only when
     * both [[enableCsrfValidation]] and [[enableCsrfCookie]] are true.
     */
    public $csrfCookie = ['httpOnly' => true];
    /**
     * @var boolean whether to use cookie to persist CSRF token. If false, CSRF token will be stored
     * in session under the name of [[csrfParam]]. Note that while storing CSRF tokens in session increases
     * security, it requires starting a session for every page, which will degrade your site performance.
     */
    public $enableCsrfCookie = true;
    /**
     * @var boolean whether cookies should be validated to ensure they are not tampered. Defaults to true.
     */
    public $enableCookieValidation = false;
    /**
     * @var string a secret key used for cookie validation. This property must be set if [[enableCookieValidation]] is true.
     */
    public $cookieValidationKey;

    /**
     * @var HeaderCollection
     */
    protected $_headers;

    /**
     * @var CookieCollection Collection of request cookies.
     */
    protected $_cookies;

    protected $_readable = true;

    protected $_method;

    protected $_path;

    protected $_queryString;

    protected $_httpVersion;

    protected $_remoteAddress;

    /**
     * @var array
     */
    protected $_get = [];

    /**
     * @var array
     */
    protected $_post = [];

    /**
     * @var array
     */
    protected $_files = [];

    /**
     * @var string|null
     */
    protected $_rawBody;
    /**
     * @var string|null
     */
    protected $_contentType;
    /**
     * @var int
     */
    protected $_contentLength;
    /**
     * @var string
     */
    protected $_url;

    /**
     * @var string
     */
    protected $_pathInfo;


    /**
     * @return ConnectionContext|null
     */
    protected $_connectionContext;

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
                    $request->_url      = $requestUri;

                    if(count($requestUriExplodes) === 2) {
                        list($path, $queryString) = $requestUriExplodes;
                        parse_str($queryString, $query);

                        $request->_path = $path;
                        $request->_get  = $query;
                        $request->_queryString      = $queryString;
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

    /**
     * Return if the request is sent via secure channel (https).
     * @return boolean if the request is sent via secure channel (https)
     */
    public function getIsSecureConnection()
    {
        return (null !== $forwardedProto = $this->_headers->get('x-forwarded-proto')) && strcasecmp($forwardedProto, 'https') === 0;
    }
    /**
     * Return if the request is sent via secure channel (https).
     * @return null|string
     */
    public function getHost()
    {
        if(null !== $host = $this->_headers->get('host')) {
            return $host;
        } else {
            return (null !== $forwardedHost = $this->_headers->get('x-forwarded-host')) ? $forwardedHost : null;

        }
    }
    private $_hostInfo;

    /**
     * Returns the schema and host part of the current request URL.
     * The returned URL does not have an ending slash.
     * By default this is determined based on the user request information.
     * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
     * @return string schema and hostname part (with port number if needed) of the request URL (e.g. `http://www.yiiframework.com`),
     * null if can't be obtained from `$_SERVER` and wasn't set.
     * @see setHostInfo()
     */
    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';
            $this->_hostInfo = $http . '://' . $this->getHost();
        }

        return $this->_hostInfo;
    }

    /**
     * Sets the schema and host part of the application URL.
     * This setter is provided in case the schema and hostname cannot be determined
     * on certain Web servers.
     * @param string $value the schema and host part of the application URL. The trailing slashes will be removed.
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }

    /**
     * Returns the currently requested relative URL.
     * This refers to the portion of the URL that is after the [[hostInfo]] part.
     * It includes the [[queryString]] part if any.
     * @return string the currently requested relative URL. Note that the URI returned is URL-encoded.
     * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Returns the currently requested absolute URL.
     * This is a shortcut to the concatenation of [[hostInfo]] and [[url]].
     * @return string the currently requested absolute URL.
     */
    public function getAbsoluteUrl()
    {
        return $this->getHostInfo() . $this->getUrl();
    }

    /**
     * Returns the path info of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting and ending slashes are both removed.
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is already URL-decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    public function getPathInfo()
    {
        if ($this->_pathInfo === null) {
            $this->_pathInfo = $this->resolvePathInfo();
        }

        return $this->_pathInfo;
    }

    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string $value the path info of the current request
     */
    public function setPathInfo($value)
    {
        $this->_pathInfo = $value === null ? null : ltrim($value, '/');
    }

    /**
     * Resolves the path info part of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting slashes are both removed (ending slashes will be kept).
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    protected function resolvePathInfo()
    {
        $pathInfo = $this->getUrl();
        if (($pos = strpos($pathInfo, '?')) !== false) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = urldecode($pathInfo);

        // try to encode in UTF8 if not so
        // http://w3.org/International/questions/qa-forms-utf-8.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }

        /*$scriptUrl = $this->getScriptUrl();
        $baseUrl = $this->getBaseUrl();
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new InvalidConfigException('Unable to determine the path info of the current request.');
        }
*/
        if (substr($pathInfo, 0, 1) === '/') {
            $pathInfo = substr($pathInfo, 1);
        }

        return (string) $pathInfo;
    }

    /**
     * Resolves the current request into a route and the associated parameters.
     *
     * @return Friday\Promise\Promise
     */
    public function resolve()
    {
        $deferred = new Deferred();

        $this->connectionContext->post(function () use ($deferred){
            try {
                $result = Friday::$app->urlManager->parseRequest($this);
                if ($result !== false) {
                    list ($route, $params) = $result;

                    $this->_get = $params + $this->_get;

                    $deferred->resolve([$route, $this->_get]);
                } else {
                    $deferred->reject(new NotFoundHttpException(Friday::t('Page not found.')));
                }
            }catch (Throwable $e) {
                $deferred->reject($e);
            }

        });

        return $deferred->promise();
    }

    /**
     * @param $connectionContext
     * @return $this
     */
    public function setConnectionContext($connectionContext){
        $this->_connectionContext = $connectionContext;
        return $this;
    }

    /**
     * @return ConnectionContext
     */
    public function getConnectionContext(){
        return $this->_connectionContext;
    }

    public function getIsAjax(){
        return $this->headers->get('x-requested-with') === 'XMLHttpRequest';
    }

    public function getBaseUrl (){
        return '/';
    }

    public function getScriptUrl(){
        return '/index.php';
    }
}
