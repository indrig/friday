<?php
namespace Friday\Web;

use Friday\Web\Event\RequestEvent;
use Friday\Base\AbstractApplication;

/**
 * Class Application
 * @package Friday\Web
 *
 * @property Server $server
 *
 */
class Application extends AbstractApplication {
    /**
     *
     */
    public function run()
    {
        $this->server
            ->on(Server::EVENT_REQUEST, [$this, 'handleRequest'])
            ->run();

        $this->runLoop->run();

    }

    /**
     * @param RequestEvent $event
     */
    public function handleRequest(RequestEvent $event){
        var_dump('handleRequest');
       // var_dump($event->request->post());
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