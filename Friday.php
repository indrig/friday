<?php
require_once(__DIR__ . '/AbstractFriday.php');

final class Friday extends AbstractFriday{
    private static $isInit = false;

    /**
     *
     */
    public static function init(){
        if(static::$isInit){
           return;
        }
        /**
         * Register Friday autoloader
         */
        static::$classMap = require(__DIR__ . '/autoload_classmap.php');

        spl_autoload_register([__CLASS__, 'autoload'], true, true);



        static::$container = new Friday\Di\Container();

        static::$isInit = true;
    }
}

Friday::init();
