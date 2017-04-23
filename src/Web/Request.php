<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Component;
use Friday\Base\Deferred;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Base\Exception\RuntimeException;
use Friday\Helper\StringHelper;
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
 * @property bool $isPjax
 * @property bool $isPost
 * @property bool $isGet
 */
class Request extends Component implements ReadableStreamInterface
{
    use ConnectionContextTrait;
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
     * @var string
     */
    private $_csrfToken;

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
     * @var array the parsers for converting the raw HTTP request body into [[bodyParams]].
     * The array keys are the request `Content-Types`, and the array values are the
     * corresponding configurations for [[Yii::createObject|creating the parser objects]].
     * A parser must implement the [[RequestParserInterface]].
     *
     * To enable parsing for JSON requests you can use the [[JsonParser]] class like in the following example:
     *
     * ```
     * [
     *     'application/json' => 'yii\web\JsonParser',
     * ]
     * ```
     *
     * To register a parser for parsing all request types you can use `'*'` as the array key.
     * This one will be used as a fallback in case no other types match.
     *
     * @see getBodyParams()
     */
    public $parsers = [];

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
     * @var
     */
    private $_bodyParams;

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
     * @return Awaitable
     */
    public function resolve() : Awaitable
    {
        $deferred = new Deferred();

        $this->connectionContext->task(function () use ($deferred){
            try {
                $result = Friday::$app->urlManager->parseRequest($this);
                if ($result !== false) {
                    list ($route, $params) = $result;

                    $this->_get = $params + $this->_get;

                    $deferred->result([$route, $this->_get]);
                } else {
                    $deferred->exception(new NotFoundHttpException(Friday::t('friday','Page not found.')));
                }
            }catch (Throwable $e) {
                $deferred->exception($e);
            }

        });

        return $deferred->awaitable();
    }

    public function getIsAjax(){
        return $this->headers->get('x-requested-with') === 'XMLHttpRequest';
    }

    public function getIsPjax(){
        return !empty($this->headers->get('x-pjax'));
    }

    public function getBaseUrl (){
        return '';
    }

    public function getScriptUrl(){
        return '/index.php';
    }

    public function getUserIP(){
        return '';
    }

    /**
     * Returns the cookie collection.
     * Through the returned cookie collection, you may access a cookie using the following syntax:
     *
     * ```php
     * $cookie = $request->cookies['name']
     * if ($cookie !== null) {
     *     $value = $cookie->value;
     * }
     *
     * // alternatively
     * $value = $request->cookies->getValue('name');
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection($this->loadCookies(), [
                'readOnly' => true,
            ]);
        }

        return $this->_cookies;
    }

    /**
     * Converts `$_COOKIE` into an array of [[Cookie]].
     * @return array the cookies obtained from request
     * @throws InvalidConfigException if [[cookieValidationKey]] is not set when [[enableCookieValidation]] is true
     */
    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            $inputCookies = $this->getHeaders()->get('cookie', [], false);
            foreach ($inputCookies as $inputCookie) {
                $cookiePairs = explode(';', $inputCookie);
                foreach ($cookiePairs as $cookiePair) {
                    $cookiePair = trim($cookiePair);
                    $cookiePair = explode('=', $cookiePair, 2);
                    if(count($cookiePair) === 2) {
                        list($name, $value) = $cookiePair;

                        if (!is_string($value)) {
                            continue;
                        }
                        $data = Friday::$app->security->validateData($value, $this->cookieValidationKey);
                        if ($data === false) {
                            continue;
                        }
                        $data = @unserialize($data);
                        if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                            $cookies[$name] = new Cookie([
                                'name' => $name,
                                'value' => $data[1],
                                'expire' => null,
                            ]);
                        }
                    }
                }
            }
        } else {
            $inputCookies = $this->getHeaders()->get('cookie', [], false);
            foreach ($inputCookies as $inputCookie) {
                $cookiePairs = explode(';', $inputCookie);
                foreach ($cookiePairs as $cookiePair) {
                    $cookiePair = trim($cookiePair);
                    $cookiePair = explode('=', $cookiePair, 2);
                    if (count($cookiePair) === 2) {
                        list($name, $value) = $cookiePair;

                        $cookies[$name] = new Cookie([
                            'name' => $name,
                            'value' => $value,
                            'expire' => null,
                        ]);
                    }
                }
            }
        }

        return $cookies;
    }

    /**
     * Performs the CSRF validation.
     *
     * This method will validate the user-provided CSRF token by comparing it with the one stored in cookie or session.
     * This method is mainly called in [[Controller::beforeAction()]].
     *
     * Note that the method will NOT perform CSRF validation if [[enableCsrfValidation]] is false or the HTTP method
     * is among GET, HEAD or OPTIONS.
     *
     * @param string $token the user-provided CSRF token to be validated. If null, the token will be retrieved from
     * the [[csrfParam]] POST field or HTTP header.
     * This parameter is available since version 2.0.4.
     * @return boolean whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
     */
    public function validateCsrfToken($token = null)
    {
        $method = $this->getMethod();
        // only validate CSRF token on non-"safe" methods http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.1.1
        if (!$this->enableCsrfValidation || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $trueToken = $this->loadCsrfToken();

        if ($token !== null) {
            return $this->validateCsrfTokenInternal($token, $trueToken);
        } else {
            return $this->validateCsrfTokenInternal($this->getBodyParam($this->csrfParam), $trueToken)
            || $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
        }
    }

    public function getContentType(){
        return $this->_contentType;
    }
    /**
     * Returns the request parameters given in the request body.
     *
     * Request parameters are determined using the parsers configured in [[parsers]] property.
     * If no parsers are configured for the current [[contentType]] it uses the PHP function `mb_parse_str()`
     * to parse the [[rawBody|request body]].
     * @return array the request parameters given in the request body.
     * @throws InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
     * @see getMethod()
     * @see getBodyParam()
     * @see setBodyParams()
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($_POST[$this->methodParam])) {
                $this->_bodyParams = $_POST;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            $contentType = $this->getContentType();
            if (($pos = strpos($this->_contentType, ';')) !== false) {
                // e.g. application/json; charset=UTF-8
                $contentType = substr($contentType, 0, $pos);
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Friday::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the Friday\\Web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Friday::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The fallback request parser is invalid. It must implement the Friday\\Web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $this->post();
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * Returns the named request body parameter value.
     * If the parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return mixed the parameter value
     * @see getBodyParams()
     * @see setBodyParams()
     */
    public function getBodyParam($name, $defaultValue = null)
    {
        $params = $this->getBodyParams();

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    /**
     * Validates CSRF token
     *
     * @param string $token
     * @param string $trueToken
     * @return boolean
     */
    private function validateCsrfTokenInternal($token, $trueToken)
    {
        if (!is_string($token)) {
            return false;
        }

        $token = base64_decode(str_replace('.', '+', $token));
        $n = StringHelper::byteLength($token);
        if ($n <= static::CSRF_MASK_LENGTH) {
            return false;
        }
        $mask = StringHelper::byteSubstr($token, 0, static::CSRF_MASK_LENGTH);
        $token = StringHelper::byteSubstr($token, static::CSRF_MASK_LENGTH, $n - static::CSRF_MASK_LENGTH);
        $token = $this->xorTokens($mask, $token);

        return $token === $trueToken;
    }

    /**
     * Loads the CSRF token from cookie or session.
     * @return string the CSRF token loaded from cookie or session. Null is returned if the cookie or session
     * does not have CSRF token.
     */
    protected function loadCsrfToken()
    {
        if ($this->enableCsrfCookie) {
            return $this->getCookies()->getValue($this->csrfParam);
        } else {
            return $this->connectionContext->session->get($this->csrfParam);
        }
    }

    /**
     * @return string the CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned if no such header is sent.
     */
    public function getCsrfTokenFromHeader()
    {
        return $this->getHeaders()->get(static::CSRF_HEADER);
    }

    /**
     * Returns the XOR result of two strings.
     * If the two strings are of different lengths, the shorter one will be padded to the length of the longer one.
     * @param string $token1
     * @param string $token2
     * @return string the XOR result
     */
    private function xorTokens($token1, $token2)
    {
        $n1 = StringHelper::byteLength($token1);
        $n2 = StringHelper::byteLength($token2);
        if ($n1 > $n2) {
            $token2 = str_pad($token2, $n1, $token2);
        } elseif ($n1 < $n2) {
            $token1 = str_pad($token1, $n2, $n1 === 0 ? ' ' : $token1);
        }

        return $token1 ^ $token2;
    }

    /**
     * Returns the token used to perform CSRF validation.
     *
     * This token is generated in a way to prevent [BREACH attacks](http://breachattack.com/). It may be passed
     * along via a hidden field of an HTML form or an HTTP header value to support CSRF validation.
     * @param boolean $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return string the token used to perform CSRF validation.
     */
    public function getCsrfToken($regenerate = false)
    {
        if ($this->_csrfToken === null || $regenerate) {
            if ($regenerate || ($token = $this->loadCsrfToken()) === null) {
                $token = $this->generateCsrfToken();
            }
            // the mask doesn't need to be very random
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';
            $mask = substr(str_shuffle(str_repeat($chars, 5)), 0, static::CSRF_MASK_LENGTH);
            // The + sign may be decoded as blank space later, which will fail the validation
            $this->_csrfToken = str_replace('+', '.', base64_encode($mask . $this->xorTokens($token, $mask)));
        }

        return $this->_csrfToken;
    }


    /**
     * Generates  an unmasked random token used to perform CSRF validation.
     * @return string the random token for CSRF validation.
     */
    protected function generateCsrfToken()
    {
        $token = Friday::$app->getSecurity()->generateRandomString();
        if ($this->enableCsrfCookie) {
            $cookie = $this->createCsrfCookie($token);

            $this->getResponse()->getCookies()->add($cookie);
        } else {
            $this->getSession()->set($this->csrfParam, $token);
        }
        return $token;
    }

    /**
     * Creates a cookie with a randomly generated CSRF token.
     * Initial values specified in [[csrfCookie]] will be applied to the generated cookie.
     * @param string $token the CSRF token
     * @return Cookie the generated cookie
     * @see enableCsrfValidation
     */
    protected function createCsrfCookie($token)
    {
        $options = $this->csrfCookie;
        $options['name'] = $this->csrfParam;
        $options['value'] = $token;
        return new Cookie($options);
    }

    public function getIsPost(){
        return $this->_method === 'POST';
    }

    public function getIsGet(){
        return $this->_method === 'POST';
    }
}
