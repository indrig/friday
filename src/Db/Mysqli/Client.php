<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Deferred;
use Friday\Base\Task;
use Friday\Db\ClientInterface;
use Friday\Db\Exception\Exception;

class Client implements ClientInterface{

    /**
     * @var Deferred[]
     */
    private static $poolDeferred   = [];

    /**
     * @var Statement[]
     */
    private static $poolStatement  = [];

    /**
     * @var \mysqli[]
     */
    private static $poolResource   = [];

    /**
     * @var null|Task
     */
    private static $poolTask       = null;

    public function createSchema(array $config = []){
        if(!isset($config['class'])) {
            $config['class'] = __NAMESPACE__ . '\Schema';
        }
        return Friday::createObject($config);
    }

    public function createConnection(array $config = []){
        if(!isset($config['class'])) {
            $config['class'] = __NAMESPACE__ . '\Connection';
        }
        return Friday::createObject($config);
    }

    public static function addQueryPoolAwait(Statement $statement, Deferred $deferred){
        $connection = $statement->getConnection();
        $resource   = $connection->getResource();
        $id         = spl_object_hash($resource);

        static::$poolDeferred[$id]  = $deferred;
        static::$poolStatement[$id] = $statement;
        static::$poolResource[$id]  = $resource;

        if(static::$poolTask === null) {
            static::$poolTask = Friday::$app->getLooper()->taskPeriodic(function (Task $task){
                $links = $errors = $reject = static::$poolResource;
                mysqli_poll($links, $errors, $reject, 0); // don't wait, just check
                $each = array('links' => $links, 'errors' => $errors, 'reject' => $reject) ;
                foreach($each as $type => $resources) {
                    /**
                     * @var \mysqli $resources[]
                     * @var \mysqli $resource
                     */
                    foreach($resources as $resource) {
                        $id = spl_object_hash($resource);
                        if(isset(static::$poolResource[$id])) {
                            $deferred = static::$poolDeferred[$id];
                            $statement = static::$poolStatement[$id];
                            if ($type === 'links') {
                                /**
                                 * @var $queryResult \mysqli_result
                                 */
                                if (false === $queryResult = $resource->reap_async_query()) {
                                    $deferred->exception(new Exception($resource->error));
                                } elseif(true === $queryResult) {
                                    $resource->affected_rows;
                                    //$statement->setResult($queryResult);
                                    $deferred->result($statement);
                                } else {
                                    $statement->setResult($queryResult);
                                    $deferred->result($statement);
                                }
                            }elseif ($type === 'errors') {
                                $deferred->exception(new Exception($resource->error));
                            }elseif ($type === 'reject') {
                                $deferred->exception(new Exception('Query was rejected.'));
                            }
                            unset(static::$poolDeferred[$id], static::$poolResource[$id], static::$poolStatement[$id]);

                            $statement->getConnection()->free();
                        }
                    }
                }
                if (empty(static::$poolResource)) {
                    static::$poolTask->remove();
                    static::$poolTask = null;
                }
            }, 0.001);
        }
    }
}