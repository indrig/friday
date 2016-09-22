<?php
namespace Friday\Cache;

use Friday\Base\Awaitable;
use Friday\Base\Deferred;

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
    public function exists($key) : Awaitable
    {
        $deferred = new Deferred();
        $key = $this->buildKey($key);

        $deferred->result(isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true)));

        return $deferred->awaitable();
    }
    /**
     * @inheritdoc
     */
    protected function getValue($key) : Awaitable
    {
        $deferred = new Deferred();;
        $deferred->result(true);
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            $deferred->result($this->_cache[$key][0]);
        } else {
            $deferred->result(false);
        }



        return $deferred->awaitable();
    }
    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $duration) : Awaitable
    {
        $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
        $deferred = new Deferred();;
        $deferred->result(true);

        return $deferred->awaitable();
    }
    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $duration) : Awaitable
    {
        $deferred = new Deferred();;
        $deferred->result();

        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            $deferred->result(false);
        } else {
            $this->_cache[$key] = [$value, $duration === 0 ? 0 : microtime(true) + $duration];
            $deferred->result(true);
        }
        return $deferred->awaitable();
    }
    /**
     * @inheritdoc
     */
    protected function deleteValue($key) : Awaitable
    {
        unset($this->_cache[$key]);

        $deferred = new Deferred();;
        $deferred->result(true);

        return $deferred->awaitable();
    }
    /**
     * @inheritdoc
     */
    protected function flushValues() : Awaitable
    {
        $this->_cache = [];
        $deferred = new Deferred();;
        $deferred->result();

        return $deferred->awaitable();
    }
}