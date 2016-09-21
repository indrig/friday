<?php
namespace Friday\Cache;
use Friday\Helper\PromiseHelper;
use Friday\Promise\PromiseInterface;

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
    public function exists($key) : PromiseInterface
    {
        $key = $this->buildKey($key);

        return PromiseHelper::resolve(isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true)));
    }
    /**
     * @inheritdoc
     */
    protected function getValue($key) : PromiseInterface
    {
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return PromiseHelper::resolve($this->_cache[$key][0]);
        } else {
            return PromiseHelper::resolve(false);
        }
    }
    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $duration) : PromiseInterface
    {
        $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
        return PromiseHelper::resolve(true);
    }
    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $duration) : PromiseInterface
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
    protected function deleteValue($key) : PromiseInterface
    {
        unset($this->_cache[$key]);
        return PromiseUtil::resolve(true);
    }
    /**
     * @inheritdoc
     */
    protected function flushValues() : PromiseInterface
    {
        $this->_cache = [];
        return PromiseUtil::resolve();
    }
}