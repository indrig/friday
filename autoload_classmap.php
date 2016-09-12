<?php
return [
    'Friday\Base\Event' => __DIR__ . '/Base/Event.php',
    'Friday\Base\ArrayableInterface' => __DIR__ . '/Base/ArrayableInterface.php',
    'Friday\Base\BaseObject' => __DIR__ . '/Base/BaseObject.php',
    'Friday\Base\Behavior' => __DIR__ . '/Base/Behavior.php',
    'Friday\Base\Component' => __DIR__ . '/Base/Component.php',
    'Friday\Base\Module' => __DIR__ . '/Base/Module.php',
    'Friday\Base\AbstractApplication' => __DIR__ . '/Base/AbstractApplication.php',
    'Friday\Base\ConfigurableInterface' => __DIR__ . '/Base/ConfigurableInterface.php',
    'Friday\Base\Exception\ExceptionInterface' => __DIR__ . '/Base/Exception/ExceptionInterface.php',
    'Friday\Base\Exception\BadMethodCallException' => __DIR__ . '/Base/Exception/BadMethodCallException.php',
    'Friday\Base\Exception\InvalidArgumentException' => __DIR__ . '/Base/Exception/InvalidArgumentException.php',
    'Friday\Base\Exception\InvalidConfigException' => __DIR__ . '/Base/Exception/InvalidConfigException.php',
    'Friday\Base\Exception\PropertyAccessException' => __DIR__ . '/Base/Exception/PropertyAccessException.php',
    'Friday\Base\Exception\RuntimeException' => __DIR__ . '/Base/Exception/RuntimeException.php',
    'Friday\Base\Exception\UnknownClassException' => __DIR__ . '/Base/Exception/UnknownClassException.php',
    'Friday\Base\Exception\UnknownPropertyException' => __DIR__ . '/Base/Exception/UnknownPropertyException.php',

    'Friday\Helper\AliasHelper' => __DIR__ . '/Helper/AliasHelper.php',
    'Friday\Helper\ArrayHelper' => __DIR__ . '/Helper/ArrayHelper.php',

    'Friday\Di\Container' => __DIR__ . '/Di/Container.php',
    'Friday\Di\Instance' => __DIR__ . '/Di/Instance.php',
    'Friday\Di\ServiceLocator' => __DIR__ . '/Di/ServiceLocator.php',
    'Friday\Di\Exception\NotInstantiableException' => __DIR__ . '/Di/Exception/NotInstantiableException.php',

    'Friday\Web\Application' => __DIR__ . '/Web/Application.php',

    'Friday\EventLoop\NextTickQueue'      => __DIR__ . '/EventLoop/NextTickQueue.php',
    'Friday\EventLoop\FutureTickQueue'    => __DIR__ . '/EventLoop/FutureTickQueue.php',
    'Friday\EventLoop\LoopInterface'      => __DIR__ . '/EventLoop/LoopInterface.php',
    'Friday\EventLoop\StreamSelectLoop'   => __DIR__ . '/EventLoop/StreamSelectLoop.php',
    'Friday\EventLoop\Timer'              => __DIR__ . '/EventLoop/Timer.php',
    'Friday\EventLoop\Timers'             => __DIR__ . '/EventLoop/Timers.php',
    'Friday\EventLoop\TimerInterface'     => __DIR__ . '/EventLoop/TimerInterface.php',

];