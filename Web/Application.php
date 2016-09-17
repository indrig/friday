<?php
namespace Friday\Web;

use Friday;
use Friday\Web\Event\RequestEvent;
use Friday\Base\AbstractApplication;
use Throwable;

/**
 * Class Application
 * @package Friday\Web
 *
 * @property Server $server
 * @property UrlManager $urlManager
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
       $connectionContent = ConnectionContext::create($event->request, $event->response);

        $event->request->resolve()->then(
            //Success
            function (array $params) use($connectionContent) {
                //Select controller and action
                list ($route, $params) = $params;
                Friday::trace("Route requested: '{$route}'", __METHOD__);
                try {
                    $connectionContent->runAction($route, $params)->then(function () use($connectionContent){
                        Friday::error('action rin.');
                        },
                        function ($throwable = null) use ($connectionContent) {
                            if($throwable === null) {
                                Friday::error('Unknown error.');
                            } else {
                                Friday::error($throwable);
                            }

                            $connectionContent->response->send();

                        });
                }catch (Throwable $throwable) {
                    Friday::error($throwable);
                    $connectionContent->response->send();
                }
            },
            //Error
            function (Throwable $throwable) use($connectionContent) {
                //Close connection end render error response
                Friday::error($throwable);

            }
        );
    }


    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'server' => ['class' => 'Friday\Web\Server'],
            'urlManager' => ['class' => 'Friday\Web\UrlManager']
        ]);
    }
}