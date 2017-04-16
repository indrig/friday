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
    public function actionIndex($address){
        /**
         * @var Friday\Base\ChildProcess $process
         */
        $process = Friday::createObject([
            'class' => Friday\Base\ChildProcess::class,
            'cmd'   => 'ping ya.ru'
        ]);


        $process->on('exit', function(Friday\Base\Event\ExitEvent $event) {
            echo "Child exit\n";
        });

        Friday::$app->getLooper()->task(function () use ($process){
            $process->start();
            $process->stdout->on(Friday\Stream\Stream::EVENT_CONTENT, function(ContentEvent $event) {
                echo "Child script says: " . $event->content;
            });
        });

        return Friday::$app->getLooper();
    }
}