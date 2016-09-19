<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Exception\InvalidRouteException;
use Friday\Promise\ExtendedPromiseInterface;
use Friday\Promise\PromiseInterface;
use Friday\Web\Event\ConnectionContextErrorEvent;
use Friday\Web\Event\ConnectionContextEvent;
use Friday\Web\Event\RequestEvent;
use Friday\Base\AbstractApplication;
use Friday\Web\HttpException\NotFoundHttpException;
use SplObjectStorage;
use Throwable;

/**
 * Class Application
 * @package Friday\Web
 *
 * @property Server $server
 * @property UrlManager $urlManager
 * @property View $view
 * @property Request $request
 * @property User $user
 * @property Session $session
 * @property Response $response
 * @property Controller $controller
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

    /**
     * @var string|boolean the layout that should be applied for views in this application. Defaults to 'main'.
     * If this is false, layout will be disabled.
     */
    public $layout = 'main';

    public function init()
    {
        parent::init();

        $this->_contexts = new SplObjectStorage();
    }

    /**
     * @param ConnectionContext $context
     */
    public function detachContext(ConnectionContext $context){
        $this->_contexts->detach($context);
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

       /* $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_ROUTING, new ConnectionContextEvent([
            'connectionContent' => $connectionContent
        ]));
*/
        $event->request->resolve()->then(
            //Success
            function (array $params) use($connectionContent) {
              /*  $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_ROUTING, new ConnectionContextEvent([
                    'connectionContent' => $connectionContent
                ]));
*/
                //Select controller and action
                list ($route, $params) = $params;
                Friday::trace("Route requested: '{$route}'", __METHOD__);
                try {
                   /* $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_RUN_ACTION,new ConnectionContextEvent([
                        'connectionContent' => $connectionContent
                    ]));
*/
                    $connectionContent->runAction($route, $params)->then(function ($result) use($connectionContent){
  /*                      $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_RUN_ACTION, new ConnectionContextEvent([
                            'connectionContent' => $connectionContent
                        ]));
*/
                        if($result instanceof ExtendedPromiseInterface) {
                            $result->always(function ($result) use ($connectionContent){
                                if ($result instanceof Response) {
                                    $response = $result;
                                    $response->send();
                                } else {
                                    $response = $connectionContent->response;
                                    if ($result !== null) {
                                        $response->data = $result;
                                    }
                                    $response->send();
                                }
                            });
                        }elseif ($result instanceof Response) {
                            $response = $result;
                            $response->send();
                        } else {
                            $response = $connectionContent->response;
                            if ($result !== null) {
                                $response->data = $result;
                            }
                            $response->send();
                        }
                        
                    }, function ($throwable = null) use ($connectionContent) {
                        if($throwable !== null) {
                            if($throwable instanceof InvalidRouteException) {
                                $throwable = new NotFoundHttpException('Page not found.');
                            }
                        }

                       /* $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR, new ConnectionContextErrorEvent([
                            'connectionContent' => $connectionContent,
                            'error' => $throwable
                        ]));*/

                        Friday::$app->errorHandler->handleException($throwable);
                    });
                }catch (Throwable $throwable) {
                    /*$this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR,new ConnectionContextErrorEvent([
                        'connectionContent' => $connectionContent,
                        'error' => $throwable
                    ]));
*/
                    Friday::$app->errorHandler->handleException($throwable);
                }
            },
            //Error
            function (Throwable $throwable) use($connectionContent) {
                if($throwable !== null) {
                    if($throwable instanceof InvalidRouteException) {
                        $throwable = new NotFoundHttpException('Page not found.');
                    }
                }
                /*$this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR,new ConnectionContextErrorEvent([
                    'connectionContent' => $connectionContent,
                    'error' => $throwable
                ]));
*/
                Friday::$app->errorHandler->handleException($throwable);
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

    /**
     * @return Request|null
     */
    public function getRequest(){
        if($this->_currentContext !== null) {
            return $this->currentContext->getRequest();
        }
        return null;
    }

    /**
     * @return Response|null
     */
    public function getResponse(){
        if($this->_currentContext !== null) {
            return $this->currentContext->getResponse();
        }
        return null;
    }

    /**
     * @return Controller|null
     */
    public function getController(){
        if($this->_currentContext !== null) {
            return $this->currentContext->controller;
        }
        return null;
    }

    /**
     * @return User|null
     */
    public function getUser(){
        if($this->_currentContext !== null) {
            return $this->currentContext->user;
        }
        return null;
    }

    /**
     * @return Session|null
     */
    public function getSession(){
        if($this->_currentContext !== null) {
            return $this->currentContext->session;
        }
        return null;
    }

    private $_homeUrl;

    /**
     * @return string the homepage URL
     */
    public function getHomeUrl()
    {
        if ($this->_homeUrl === null) {
            return $this->getRequest()->getBaseUrl() . '/';
        } else {
            return $this->_homeUrl;
        }
    }

    /**
     * @param string $value the homepage URL
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }
}