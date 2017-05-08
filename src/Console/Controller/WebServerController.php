<?php
namespace Friday\Console\Controller;

use Friday;
use Friday\Console\Controller;
use Friday\Stream\Event\ContentEvent;

/**
 * Runs Friday built-in async web server
 *
 * @param string $address address to serve on. Either "host" or "host:port".
 *
 * @return integer
 */
class WebServerController extends Controller{

    public $defaultAction = 'start';

    public function actionStart($address = '127.0.0.1'){
        /**
         * @var Friday\Base\ChildProcess $process
         */
        $process = Friday::createObject([
            'class' => Friday\Base\InnerChildProcess::class,
            'cmd'   => 'php '  . Friday\Helper\AliasHelper::getAlias('@root/test.php')
        ]);


        $process->on('exit', function(Friday\Base\Event\ExitEvent $event) {
            echo "Child exit {$event->code}, {$event->termSignal}\n";
        });

        Friday::$app->getLooper()->task(function () use ($process){
            $process->start();
        });

        return Friday::$app->getLooper();
    }
}