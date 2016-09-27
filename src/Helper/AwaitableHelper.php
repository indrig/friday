<?php
namespace Friday\Helper;

use Friday\Base\Awaitable;
use Friday\Base\Deferred;

class AwaitableHelper{

    /**
     * @param Awaitable[] $awaitables
     * @param bool $withWrapper
     * @return Awaitable
     */
    public static function all(array $awaitables, bool  $withWrapper = false){
        return new Awaitable(function ($resolve, $reject) use ($awaitables, $withWrapper)  {
            if(0 === $toResolve = count($awaitables)){
                call_user_func($resolve, []);

            } else {
                $values    = [];
                foreach ($awaitables as $key => $awaitable) {
                    $awaitable->await(function ($result) use (&$values, &$toResolve, $resolve, $key){
                        $values[$key] = $result;

                        if (0 === --$toResolve) {
                            call_user_func($resolve, $values);
                        }
                    }, false);
                }
            }

        });
    }

    public static function result($result = null){
        $deferred = new Deferred();
        $deferred->result($result);
        return $deferred->awaitable();
    }

    public static function exception($throwable = null){
        $deferred = new Deferred();
        $deferred->exception($throwable);
        return $deferred->awaitable();
    }
}