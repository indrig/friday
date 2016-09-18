<?php
namespace Friday\Web;

use Friday;
use Friday\Web\Event\ConnectionContextEvent;
use Friday\Web\Event\RequestEvent;
use Friday\Base\AbstractApplication;
use SplObjectStorage;
use Throwable;

/**
 * Class Application
 * @package Friday\Web
 *
 * @property Server $server
 * @property UrlManager $urlManager
 * @property View $view
 * @property ConnectionContext|null $currentContext
 */
class Application extends AbstractApplication {

    /**
     * @var SplObjectStorage
     */
    protected $_contexts;

    /**
     * @var ConnectionContext
     */
    protected $_currentContext;

    public function init()
    {
        parent::init();

        $this->_contexts = new SplObjectStorage();
    }

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

        $this->_contexts->attach($connectionContent);

        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_ROUTING, new ConnectionContextEvent([
            'connectionContent' => $connectionContent
        ]));

        $event->request->resolve()->then(
            //Success
            function (array $params) use($connectionContent) {
                $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_ROUTING, new ConnectionContextEvent([
                    'connectionContent' => $connectionContent
                ]));

                //Select controller and action
                list ($route, $params) = $params;
                Friday::trace("Route requested: '{$route}'", __METHOD__);
                try {
                    $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_RUN_ACTION,new ConnectionContextEvent([
                        'connectionContent' => $connectionContent
                    ]));
                    $connectionContent->runAction($route, $params)->then(function ($result) use($connectionContent){
                        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_RUN_ACTION, new ConnectionContextEvent([
                            'connectionContent' => $connectionContent
                        ]));

                        if ($result instanceof Response) {
                            $response = $result;
                        } else {
                            $response = $connectionContent->response;
                            if ($result !== null) {
                                $response->data = $result;
                            }
                        }

                       /* $response->send() -> then(function () use ($connectionContent){
                            $this->_contexts->detach($connectionContent);
                        }, function () use ($connectionContent){
                            $this->_contexts->detach($connectionContent);
                        });*/
                    }, function ($throwable = null) use ($connectionContent) {
                        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR,new ConnectionContextEvent([
                            'connectionContent' => $connectionContent,
                            'error' => $throwable
                        ]));

                        $connectionContent->error($throwable);
                    });
                }catch (Throwable $throwable) {
                    $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR,new ConnectionContextEvent([
                        'connectionContent' => $connectionContent,
                        'error' => $throwable
                    ]));

                    $connectionContent->error($throwable);
                }
            },
            //Error
            function (Throwable $throwable) use($connectionContent) {
                $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR,new ConnectionContextEvent([
                    'connectionContent' => $connectionContent,
                    'error' => $throwable
                ]));

                $connectionContent->error($throwable);
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
            'urlManager' => ['class' => 'Friday\Web\UrlManager'],
            'errorHandler' => ['class' => 'Friday\Web\ErrorHandler'],
            'view' => ['class' => 'Friday\Web\View'],
        ]);
    }

    /**
     * @param $connectionContext
     * @return $this
     */
    public function setCurrentContext($connectionContext){
        $this->_currentContext = $connectionContext;
        return $this;
    }

    /**
     * @return ConnectionContext
     */
    public function getCurrentContext(){
        return $this->_currentContext;
    }
}