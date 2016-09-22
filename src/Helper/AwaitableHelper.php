<?php
namespace Friday\Helper;

use Friday\Base\Awaitable;

class AwaitableHelper{

    /**
     * @param Awaitable[] $awaitables
     * @param bool $withWrapper
     * @return Awaitable
     */
    public static function all(array $awaitables, bool  $withWrapper = false){
        return new Awaitable(function ($resolve, $reject) use ($awaitables, $withWrapper)  {
            $toResolve = count($awaitables);

            $resolveCallback = $resolve;
            $values    = [];
            foreach ($awaitables as $key => $awaitable) {
                $awaitable->await(function ($result) use (&$values, &$toResolve, $resolveCallback, $key){
                    $values[$key] = $result;

                    if (0 === --$toResolve) {
                        call_user_func($resolveCallback, $values);
                    }
                }, false);
            }
        });
    }
}