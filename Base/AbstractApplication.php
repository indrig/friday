<?php
namespace Friday\Base;

use Friday;
use Friday\Base\Exception\InvalidConfigException;

class AbstractApplication extends Module {
    /**
     * @var \Friday\Base\Module[]
     */
    public $loadedModules = [];

    /**
     * Constructor.
     * @param array $config name-value pairs that will be used to initialize the object properties.
     * Note that the configuration must contain both [[id]] and [[basePath]].
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function __construct(array $config = [])
    {
        Friday::$app = $this;
        static::setInstance($this);

       // $this->state = self::STATE_BEGIN;

       // $this->preInit($config);

       // $this->registerErrorHandler($config);

        Component::__construct($config);
    }

    public function run(){

    }
}