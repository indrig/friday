<?php
use Friday\Base\Component;
use Friday\Base\Exception\InvalidConfigException;
use Friday\Base\Exception\UnknownClassException;
use Friday\Di\Container;
use Friday\Helper\AliasHelper;
/**
 * Gets the application start timestamp.
 */
defined('FRIDAY_BEGIN_TIME') || define('FRIDAY_BEGIN_TIME', microtime(true));
/**
 * This constant defines the framework installation directory.
 */
defined('FRIDAY_PATH') || define('FRIDAY_PATH', __DIR__);
/**
 * This constant defines whether the application should be in debug mode or not. Defaults to false.
 */
defined('FRIDAY_DEBUG') || define('FRIDAY_DEBUG', false);
/**
 * This constant defines in which environment the application is running. Defaults to 'prod', meaning production environment.
 * You may define this constant in the bootstrap script. The value could be 'prod' (production), 'dev' (development), 'test', 'staging', etc.
 */
defined('FRIDAY_ENV') || define('FRIDAY_ENV', 'prod');
/**
 * Whether the the application is running in production environment
 */
defined('FRIDAY_ENV_PROD') || define('FRIDAY_ENV_PROD', FRIDAY_ENV === 'prod');
/**
 * Whether the the application is running in development environment
 */
defined('FRIDAY_ENV_DEV') || define('FRIDAY_ENV_DEV', FRIDAY_ENV === 'dev');
/**
 * Whether the the application is running in testing environment
 */
defined('FRIDAY_ENV_TEST') || define('FRIDAY_ENV_TEST', FRIDAY_ENV === 'test');

/**
 * This constant defines whether error handling should be enabled. Defaults to true.
 */
defined('FRIDAY_ENABLE_ERROR_HANDLER') || define('FRIDAY_ENABLE_ERROR_HANDLER', true);

/**
 * Class AbstractFriday
 */
abstract class AbstractFriday{
    /**
     * @var Container the dependency injection (DI) container used by [[createObject()]].
     * You may use [[Container::set()]] to set up the needed dependencies of classes and
     * their initial property values.
     * @see createObject()
     * @see Container
     */
    public static $container;

    /**
     * @var array class map used by the Yii autoloading mechanism.
     * The array keys are the class names (without leading backslashes), and the array values
     * are the corresponding class file paths (or path aliases). This property mainly affects
     * how [[autoload()]] works.
     * @see autoload()
     */
    public static $classMap = [];

    /**
     * @var \Friday\Base\AbstractApplication
     */
    public static $app;

    /**
     * Class autoload loader.
     * This method is invoked automatically when PHP sees an unknown class.
     * The method will attempt to include the class file according to the following procedure:
     *
     * 1. Search in [[classMap]];
     * 2. If the class is namespaced (e.g. `yii\base\Component`), it will attempt
     *    to include the file associated with the corresponding path alias
     *    (e.g. `@yii/base/Component.php`);
     *
     * This autoloader allows loading classes that follow the [PSR-4 standard](http://www.php-fig.org/psr/psr-4/)
     * and have its top-level namespace or sub-namespaces defined as path aliases.
     *
     * Example: When aliases `@yii` and `@yii/bootstrap` are defined, classes in the `yii\bootstrap` namespace
     * will be loaded using the `@yii/bootstrap` alias which points to the directory where bootstrap extension
     * files are installed and all classes from other `yii` namespaces will be loaded from the yii framework directory.
     *
     * Also the [guide section on autoloading](guide:concept-autoloading).
     *
     * @param string $className the fully qualified class name without a leading backslash "\"
     * @throws UnknownClassException if the class does not exist in the class file
     */
    public static function autoload($className)
    {
        if (isset(static::$classMap[$className])) {
            $classFile = static::$classMap[$className];
            if ($classFile[0] === '@') {
                $classFile = AliasHelper::getAlias($classFile);
            }
        } elseif (strpos($className, '\\') !== false) {
            $classFile = AliasHelper::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);

            if ($classFile === false || !is_file($classFile)) {
                return;
            }
        } else {
            return;
        }

        include($classFile);

        if (FRIDAY_DEBUG && !class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new UnknownClassException("Unable to find '$className' in file: $classFile. Namespace missing?");
        }
    }

    /**
     * Creates a new object using the given configuration.
     *
     * You may view this method as an enhanced version of the `new` operator.
     * The method supports creating an object based on a class name, a configuration array or
     * an anonymous function.
     *
     * Below are some usage examples:
     *
     * ```php
     * // create an object using a class name
     * $object = Yii::createObject('Friday\Db\Connection');
     *
     * // create an object using a configuration array
     * $object = Yii::createObject([
     *     'class' => 'Friday\Db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // create an object with two constructor parameters
     * $object = \Yii::createObject('MyClass', [$param1, $param2]);
     * ```
     *
     * Using [[\yii\di\Container|dependency injection container]], this method can also identify
     * dependent objects, instantiate them and inject them into the newly created object.
     *
     * @param string|array|callable $type the object type. This can be specified in one of the following forms:
     *
     * - a string: representing the class name of the object to be created
     * - a configuration array: the array must contain a `class` element which is treated as the object class,
     *   and the rest of the name-value pairs will be used to initialize the corresponding object properties
     * - a PHP callable: either an anonymous function or an array representing a class method (`[$class or $object, $method]`).
     *   The callable should return a new instance of the object being created.
     *
     * @param array $params the constructor parameters
     * @return Component the created object
     * @throws InvalidConfigException if the configuration is invalid.
     * @see \Friday\Di\Container
     */
    public static function createObject($type, array $params = []) : Component
    {
        if (is_string($type)) {
            return static::$container->get($type, $params);
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            return static::$container->get($class, $params, $type);
        } elseif (is_callable($type, true)) {
            return static::$container->invoke($type, $params);
        } elseif (is_array($type)) {
            throw new InvalidConfigException('Object configuration must be an array containing a "class" element.');
        } else {
            throw new InvalidConfigException('Unsupported configuration type: ' . gettype($type));
        }
    }
}