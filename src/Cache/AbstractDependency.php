<?php
namespace Friday\Cache;

use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;

/**
 * Dependency is the base class for cache dependency classes.
 *
 * Child classes should override its [[generateDependencyData()]] for generating
 * the actual dependency data.
 */
abstract class AbstractDependency extends BaseObject
{
    /**
     * @var mixed the dependency data that is saved in cache and later is compared with the
     * latest dependency data.
     */
    public $data;
    /**
     * @var boolean whether this dependency is reusable or not. True value means that dependent
     * data for this cache dependency will be generated only once per request. This allows you
     * to use the same cache dependency for multiple separate cache calls while generating the same
     * page without an overhead of re-evaluating dependency data each time. Defaults to false.
     */
    public $reusable = false;
    /**
     * @var array static storage of cached data for reusable dependencies.
     */
    private static $_reusableData = [];
    /**
     * Evaluates the dependency by generating and saving the data related with dependency.
     * This method is invoked by cache before writing data into it.
     * @param AbstractCache $cache the cache component that is currently evaluating this dependency
     */
    public function evaluateDependency($cache)
    {
        if ($this->reusable) {
            $hash = $this->generateReusableHash();
            if (!array_key_exists($hash, self::$_reusableData)) {
                self::$_reusableData[$hash] = $this->generateDependencyData($cache);
            }
            $this->data = self::$_reusableData[$hash];
        } else {
            $this->data = $this->generateDependencyData($cache);
        }
    }
    /**
     * Returns a value indicating whether the dependency has changed.
     * @param AbstractCache $cache the cache component that is currently evaluating this dependency
     * @return Awaitable
     */
    public function getHasChanged($cache) : Awaitable
    {
        $deferred = new Deferred();

        if ($this->reusable) {
            $hash = $this->generateReusableHash();
            if (!array_key_exists($hash, self::$_reusableData)) {
                $this->generateDependencyData($cache)->then(function($data) use ($deferred, $hash){
                    self::$_reusableData[$hash] = $data;

                    $deferred->result($data !== $this->data);
                });
            } else {
                $deferred->result(self::$_reusableData[$hash] !== $this->data);
            }
        } else {
            $this->generateDependencyData($cache)->then(function($data) use($deferred) {
                $deferred->result($data !== $this->data);
            });
        }

        return $deferred->awaitable();
    }
    /**
     * Resets all cached data for reusable dependencies.
     */
    public static function resetReusableData()
    {
        self::$_reusableData = [];
    }
    /**
     * Generates a unique hash that can be used for retrieving reusable dependency data.
     * @return string a unique hash value for this cache dependency.
     * @see reusable
     */
    protected function generateReusableHash()
    {
        $data = $this->data;
        $this->data = null;
        $key = sha1(serialize($this));
        $this->data = $data;
        return $key;
    }
    /**
     * Generates the data needed to determine if dependency has been changed.
     * Derived classes should override this method to generate the actual dependency data.
     * @param AbstractCache $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    abstract protected function generateDependencyData($cache) : Awaitable;
}