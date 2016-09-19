<?php
namespace Friday\Cache;
use Friday\Promise\ExtendedPromiseInterface;
use Friday\Promise\Util as PromiseUtil;
/**
 * ArrayCache provides caching for the current request only by storing the values in an array.
 *
 * See [[Cache]] for common cache operations that ArrayCache supports.
 *
 * Unlike the [[Cache]], ArrayCache allows the expire parameter of [[set]], [[add]], [[multiSet]] and [[multiAdd]] to
 * be a floating point number, so you may specify the time in milliseconds (e.g. 0.1 will be 100 milliseconds).
 */
class ArrayCache extends AbstractCache
{
    private $_cache;
    /**
     * @inheritdoc
     */
    public function exists($key) : ExtendedPromiseInterface
    {
        $key = $this->buildKey($key);

        return PromiseUtil::resolve(isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true)));
    }
    /**
     * @inheritdoc
     */
    protected function getValue($key) : ExtendedPromiseInterface
    {
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return PromiseUtil::resolve($this->_cache[$key][0]);
        } else {
            return PromiseUtil::resolve(false);
        }
    }
    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $duration) : ExtendedPromiseInterface
    {
        $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
        return PromiseUtil::resolve(true);
    }
    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $duration) : ExtendedPromiseInterface
    {
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return PromiseUtil::resolve(true);
        } else {
            $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
            return PromiseUtil::resolve(true);
        }
    }
    /**
     * @inheritdoc
     */
    protected function deleteValue($key) : ExtendedPromiseInterface
    {
        unset($this->_cache[$key]);
        return PromiseUtil::resolve(true);
    }
    /**
     * @inheritdoc
     */
    protected function flushValues() : ExtendedPromiseInterface
    {
        $this->_cache = [];
        return PromiseUtil::resolve();
    }
}