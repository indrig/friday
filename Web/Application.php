<?php
namespace Friday\Web;

use Firday\Web\Event\RequestEvent;
use Friday\Base\AbstractApplication;

/**
 * Class Application
 * @package Friday\Web
 *
 * @property Server $server
 *
 */
class Application extends AbstractApplication {
    public function run()
    {
        $server = $this->server;

        $server->on(Server::EVENT_REQUEST, [$this, 'handleRequest']);
        $server->run();
        /*$this->runLoop->addPeriodicTimer(10, function (){
            var_dump('test');
        });*/
        $this->runLoop->run();

    }

    /**
     * @param RequestEvent $event
     */
    public function handleRequest(RequestEvent $event){
        echo  'connection';
    }
    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'server' => ['class' => 'Friday\Web\Server']
        ]);
    }
}