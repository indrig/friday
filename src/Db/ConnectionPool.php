<?php
namespace Friday\Db;

use ArrayIterator;
use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use SplObjectStorage;
use Countable;
use IteratorAggregate;
use Throwable;
use Traversable;

/**
 * Class ConnectionPool
 * @package Friday\Db
 */
class ConnectionPool extends BaseObject implements Countable, IteratorAggregate {
    /**
     * @var Adapter
     */
    public $adapter;

    /**
     * @var SplObjectStorage
     */
    private $busyPool;

    /**
     * @var SplObjectStorage
     */
    private $availablePool;

    /**
     * Count of maximum connection in pull
     *
     * @var int
     */
    private $maxConnections = 10;

    /**
     * @var array
     */
    private $waiting = [];

    public function init()
    {
        parent::init();

        $this->busyPool = new SplObjectStorage();
        $this->availablePool = new SplObjectStorage();
    }

    /**
     * @return Awaitable
     */
    public function getConnection() : Awaitable{
        $deferred = new Deferred();

        if($this->count() >= $this->maxConnections) {
            $this->waiting[] = $deferred;
            return $deferred->awaitable();
        }

        if($this->availablePool->count() > 0){
            $this->availablePool->rewind();
            $connection = $this->availablePool->current();

            $this->availablePool->detach($connection);
            $this->busyPool->attach($connection);

            $deferred->result($connection);
        } else {
            try {
                /**
                 * @var AbstractConnection $connection
                 */
                $connection = $this->adapter->getClient()->createConnection([
                    'adapter' => $this->adapter
                ]);

                $this->busyPool->attach($connection);
                $deferred->result($connection);
            }catch (Throwable $throwable) {
                $deferred->exception($throwable);
            }
        }

        return $deferred->awaitable();
    }

    public function free(AbstractConnection $connection, $remove){
        if($remove) {
            $this->busyPool->detach($connection);
        }

        if (!empty($this->waiting)) {
            $key = key($this->waiting);
            $deferred = $this->waiting[$key];
            unset($this->waiting[$key]);

            $this->getConnection()->await(function($conn) use($deferred){
                $deferred->resolve($conn);
            });
        } else {
            $this->availablePool->attach($connection);
        }
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return $this->busyPool->count() + $this->availablePool->count();
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        $result = [];
        foreach ($this->busyPool as $connection){
            $result[] = $connection;
        }
        foreach ($this->availablePool as $connection){
            $result[] = $connection;
        }
        return new ArrayIterator($result);
    }
}