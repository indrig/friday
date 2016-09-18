<?php
namespace Friday\Helper;

use Friday;
use Friday\EventLoop\LoopInterface;

class RunLoopHelper{
    protected static $loop;

    /**
     * @return LoopInterface
     */
    protected static function loop(){
        if(static::$loop === null) {
            static::$loop = Friday::$app->runLoop;
        }

        return static::$loop;
    }

    /**
     * @param callable $callback
     * @return Friday\EventLoop\TimerInterface
     */
    public static function post(callable $callback){
        return static::loop()->addTimer(0.000001, $callback);
    }

    /**
     * @param callable $callback
     * @param float $delay
     * @return Friday\EventLoop\TimerInterface
     */
    public static function postDelayed(callable $callback, float $delay){
        return static::loop()->addTimer($delay, $callback);
    }
}