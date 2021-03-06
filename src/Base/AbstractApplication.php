<?php
namespace Friday\Base;

use Friday;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Helper\AliasHelper;
use Friday\Helper\Json;
use Friday\Stream\Stream;

/**
 * Class AbstractApplication
 * @package Friday\Base
 *
 * @property Security $security
 * @property Friday\Db\Adapter $db
 * @property Friday\I18n\I18n
 * @property AbstractErrorHandler $errorHandler
 * @property \Friday\Cache\AbstractCache $cache
 *
 * @property Friday\Web\ConnectionContext|ContextInterface|null $currentContext
 * @property string $timeZone
 * @property Friday\I18n\Formatter $formatter
 */
class AbstractApplication extends Module {
    /**
     * @var string the application name.
     */
    public $name = 'My Application';
    /**
     * @var string the version of this application.
     */
    public $version = '1.0';
    /**
     * @var string the charset currently used for the application.
     */
    public $charset = 'UTF-8';
    /**
     * @var string the language that is meant to be used for end users. It is recommended that you
     * use [IETF language tags](http://en.wikipedia.org/wiki/IETF_language_tag). For example, `en` stands
     * for English, while `en-US` stands for English (United States).
     * @see sourceLanguage
     */
    public $language = 'en-US';
    /**
     * @var string the language that the application is written in. This mainly refers to
     * the language that the messages and view files are written in.
     * @see language
     */
    public $sourceLanguage = 'en-US';

    /**
     * @var \Friday\Base\Module[]
     */
    public $loadedModules = [];

    /**
     * @var string
     */
    private $_runtimePath;

    /**
     * @var string
     */
    private $_vendorPath;

    /**
     * @var Looper
     */
    private $_looper;

    /**
     * @var ContextInterface
     */
    protected $_context;

    /**
     * @var Friday\Stream\Stream|null
     */
    protected $_stdIn;
    /**
     * @var Friday\Stream\Stream|null
     */
    protected $_stdOut;
    /**
     * @var Friday\Stream\Stream|null
     */
    protected $_stdErr;
    /**
     * @var string
     */
    public $controllerNamespace = 'Application\Controller';

    /**
     * @var array list of components that should be run during the application [[bootstrap()|bootstrapping process]].
     *
     * Each component may be specified in one of the following formats:
     *
     * - an application component ID as specified via [[components]].
     * - a module ID as specified via [[modules]].
     * - a class name.
     * - a configuration array.
     *
     * During the bootstrapping process, each component will be instantiated. If the component class
     * implements [[BootstrapInterface]], its [[BootstrapInterface::bootstrap()|bootstrap()]] method
     * will be also be called.
     */
    public $bootstrap = [];

    /**
     * Constructor.
     * @param array $config name-value pairs that will be used to initialize the object properties.
     * Note that the configuration must contain both [[id]] and [[basePath]].
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function __construct(array $config = [])
    {
        Friday::$app = $this;
        static::setInstance($this);

        $this->preInit($config);

        $this->registerErrorHandler($config);

        Component::__construct($config);

    }

    /**
     * Pre-initializes the application.
     * This method is called at the beginning of the application constructor.
     * It initializes several important application properties.
     * If you override this method, please make sure you call the parent implementation.
     * @param array $config the application configuration
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function preInit(&$config)
    {
        if (!isset($config['id'])) {
            throw new InvalidConfigException('The "id" configuration for the Application is required.');
        }
        if (isset($config['basePath'])) {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        } else {
            throw new InvalidConfigException('The "basePath" configuration for the Application is required.');
        }

        if (isset($config['vendorPath'])) {
            $this->setVendorPath($config['vendorPath']);
            unset($config['vendorPath']);
        } else {
            // set "@vendor"
            $this->getVendorPath();
        }
        if (isset($config['runtimePath'])) {
            $this->setRuntimePath($config['runtimePath']);
            unset($config['runtimePath']);
        } else {
            // set "@runtime"
            $this->getRuntimePath();
        }

        if (isset($config['timeZone'])) {
            $this->setTimeZone($config['timeZone']);
            unset($config['timeZone']);
        } elseif (!ini_get('date.timezone')) {
            $this->setTimeZone('UTC');
        }

        // merge core components with custom components
        foreach ($this->coreComponents() as $id => $component) {
            if (!isset($config['components'][$id])) {
                $config['components'][$id] = $component;
            } elseif (is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->bootstrap();

      /*  $this->_stdIn = Friday::createObject([
            'class' => Stream::class,
            'stream' => fopen('php://stdin', 'r')
        ]);

        $this->_stdOut = Friday::createObject([
            'class' => Stream::class,
            'stream' => fopen('php://stdout', 'w')
        ]);

        $this->_stdErr = Friday::createObject([
            'class' => Stream::class,
            'stream' => fopen('php://stderr', 'w')
        ]);

        $this->_stdIn->on(Stream::EVENT_CONTENT, [$this, 'onInContent']);*/
    }
    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function getRuntimePath()
    {
        if ($this->_runtimePath === null) {
            $this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
        }

        return $this->_runtimePath;
    }

    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function setRuntimePath($path)
    {
        $this->_runtimePath = AliasHelper::getAlias($path);
        AliasHelper::setAlias('@runtime', $this->_runtimePath);
    }

    /**
     * Returns the directory that stores vendor files.
     * @return string the directory that stores vendor files.
     * Defaults to "vendor" directory under [[basePath]].
     */
    public function getVendorPath()
    {
        if ($this->_vendorPath === null) {
            $this->setVendorPath($this->getBasePath() . DIRECTORY_SEPARATOR . 'vendor');
        }

        return $this->_vendorPath;
    }

    /**
     * Sets the directory that stores vendor files.
     * @param string $path the directory that stores vendor files.
     */
    public function setVendorPath($path)
    {
        $this->_vendorPath = AliasHelper::getAlias($path);
        AliasHelper::setAlias('@vendor', $this->_vendorPath);
      //  AliasHelper::setAlias('@bower', $this->_vendorPath . DIRECTORY_SEPARATOR . 'bower');
      //  AliasHelper::setAlias('@npm', $this->_vendorPath . DIRECTORY_SEPARATOR . 'npm');
    }

    /**
     * Returns the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_get().
     * If time zone is not configured in php.ini or application config,
     * it will be set to UTC by default.
     * @return string the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-get.php
     */
    public function getTimeZone()
    {
        return date_default_timezone_get();
    }

    /**
     * Sets the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_set().
     * Refer to the [php manual](http://www.php.net/manual/en/timezones.php) for available timezones.
     * @param string $value the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-set.php
     */
    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }

    /**
     * Returns the configuration of core application components.
     * @see set()
     */
    public function coreComponents()
    {
        return [
            'security' => ['class' => 'Friday\Base\Security'],
            'log' => ['class' => 'Friday\Log\Dispatcher'],
            'i18n' => ['class' => 'Friday\I18n\I18n'],
            'formatter' => ['class' => 'Friday\I18n\Formatter'],
        ];
    }


    /**
     * Returns the error handler component.
     * @return \Friday\Web\ErrorHandler|\Friday\Console\ErrorHandler|Component the error handler application component.
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }
    /**
     * Registers the errorHandler component as a PHP error handler.
     * @param array $config application config
     */
    protected function registerErrorHandler(&$config)
    {
        if (FRIDAY_ENABLE_ERROR_HANDLER) {
            if (!isset($config['components']['errorHandler']['class'])) {
                echo "Error: no errorHandler component is configured.\n";
                exit(1);
            }
            $this->set('errorHandler', $config['components']['errorHandler']);
            unset($config['components']['errorHandler']);
            $this->getErrorHandler()->register();
        }
    }

    /**
     * Sets the root directory of the application and the @app alias.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the application.
     * @property string the root directory of the application.
     * @throws InvalidArgumentException if the directory does not exist.
     */
    public function setBasePath($path)
    {
        parent::setBasePath($path);
        AliasHelper::setAlias('@root', $this->getBasePath());
        AliasHelper::setAlias('@webroot', $this->getBasePath() . '/web');
        AliasHelper::setAlias('@web', '/');
        AliasHelper::setAlias('@src', $this->getBasePath() . '/src');

        AliasHelper::setAlias('@Application', $this->getBasePath() . '/src');
    }

    /**
     * Initializes extensions and executes bootstrap components.
     * This method is called by [[init()]] after the application has been fully configured.
     * If you override this method, make sure you also call the parent implementation.
     */
    protected function bootstrap()
    {

        foreach ($this->bootstrap as $class) {
            $component = null;
            if (is_string($class)) {
                if ($this->has($class)) {
                    $component = $this->get($class);
                } elseif ($this->hasModule($class)) {
                    $component = $this->getModule($class);
                } elseif (strpos($class, '\\') === false) {
                    throw new InvalidConfigException("Unknown bootstrapping component ID: $class");
                }
            }
            if (!isset($component)) {
                $component = Friday::createObject($class);
            }

            if ($component instanceof BootstrapInterface) {
                Friday::trace('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $component->bootstrap($this);
            } else {
                Friday::trace('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }

    /**
     * @return Security|Component
     */
    public function getSecurity(){
        return $this->get('security');
    }

    /**
     * @return Friday\Db\Adapter|Component
     */
    public function getDb(){
        return $this->get('db');
    }

    /**
     * @return Friday\Cache\AbstractCache|Component
     */
    public function getCache(){
        return $this->get('cache');
    }

    /**
     * @return Looper
     */
    public function getLooper(){
        if($this->_looper === null) {
            $this->_looper = new Looper();
        }
        return $this->_looper;
    }

    /**
     * @return Friday\I18n\I18n|Component
     */
    public function getI18n(){
        return $this->get('i18n');
    }

    /**
     * @return Friday\I18n\Formatter|Component
     */
    public function getFormatter(){
        return $this->get('formatter');
    }

    /**
     * @param ContextInterface|Friday\Web\ConnectionContext $connectionContext
     * @return $this
     */
    public function setContext($connectionContext)
    {
        $this->_context = $connectionContext;
        return $this;
    }

    /**
     * @return ContextInterface|Friday\Web\ConnectionContext
     */
    public function getContext()
    {
        return $this->_context;
    }


    protected $_rpcCommands = [];

    public function commandCall($method, $params){
        $deferred = new Deferred();
        if($this->_stdOut === null){
            throw new Friday\Base\Exception\RuntimeException('Std out not exists.');
        }

        do{
            $id = $this->getSecurity()->generateGuid();
        }while(isset($this->_rpcCommands[$id]));

        $json = Json::encode([
            'id'        => $id,
            'method'    => $method,
            'params'    => $params,

        ]);
        $microtime = microtime(true);
        $this->_rpcCommands[$id] = [$deferred, $microtime];

        $this->_stdOut->write($json);
        return $deferred->awaitable();
    }

    /**
     * @param Friday\Stream\Event\ContentEvent $contentEvent
     */
    public function onInContent(Friday\Stream\Event\ContentEvent $contentEvent){
        try{
            $result = Json::decode($contentEvent->content);
            if(is_array($result) && isset($result['id']) && (is_int($result['id']) || is_string($result['id']))){
                $id = $result['id'];

                if($this->_rpcCommands[$id]){
                    /**
                     * @var Deferred $deferred
                     */
                    list($deferred) = $this->_rpcCommands[$id];

                    if(isset($result['result'])) {
                        $deferred->result($result['result']);
                    } else {
                        $deferred->exception(array_key_exists('error', $result) ? $result['error'] : null);
                    }
                }
            }
        }catch (InvalidArgumentException $exception){
            Friday::error($exception);
        }
    }

    /**
     * Returns an ID that uniquely identifies this module among all modules within the current application.
     * Since this is an application instance, it will always return an empty string.
     * @return string the unique ID of the module.
     */
    public function getUniqueId()
    {
        return '';
    }
}