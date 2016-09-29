<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Exception\InvalidRouteException;
use Friday\Base\ResultOrExceptionWrapperInterface;
use Friday\Helper\AliasHelper;
use Friday\Helper\FileHelper;
use Friday\Stream\Stream;
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
 */
class Application extends AbstractApplication
{

    /**
     * @var SplObjectStorage
     */
    protected $_contexts;

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
    public function detachContext(ConnectionContext $context)
    {
        $this->_contexts->detach($context);
    }

    /**
     *
     */
    public function run()
    {
        $looper = $this->getLooper();

        $this->server
            ->on(Server::EVENT_REQUEST, [$this, 'handleRequest'])
            ->run();


       // $looper->taskPeriodic(function () {
       //     Friday\Helper\Console::stdout('memory_usage: ' . number_format(memory_get_usage(true), 0, '.', ' ') . "b\n");
       // }, 2);

        $looper->loop();

    }

    /**
     * @param RequestEvent $event
     */
    public function handleRequest(RequestEvent $event)
    {

        $connectionContent = ConnectionContext::create($event->request, $event->response);


        $this->_contexts->attach($connectionContent);

        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_ROUTING, new ConnectionContextEvent([
            'connectionContent' => $connectionContent
        ]));

        $this->setContext($connectionContent);

        $path = $connectionContent->getRequest()->getPath();
        if($webRoot = AliasHelper::getAlias('@webroot', false)){
            if($realpath = @realpath($webRoot . $path)) {

                if(substr(str_replace('\\', '/', $realpath), strlen($webRoot)) === $path){
                    if(is_file($realpath)) {
                        $response = $connectionContent->getResponse();
                        try {
                            $response->sendFile($realpath)->send();

                        }catch (Throwable $throwable){
                            Friday::$app->errorHandler->handleException($throwable);
                        }
                        return;
                    }
                }
            }
        }

        $event->request->resolve()->await(
        //Success
            function (ResultOrExceptionWrapperInterface $result) use ($connectionContent) {
                if ($result->isSucceeded()) {
                    //Select controller and action
                    list ($route, $params) = $result->getResult();
                    Friday::trace("Route requested: '{$route}'", __METHOD__);
                    try {
                        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_BEFORE_RUN_ACTION, new ConnectionContextEvent([
                            'connectionContent' => $connectionContent
                        ]));

                        $connectionContent->runAction($route, $params)->await(function (ResultOrExceptionWrapperInterface $result) use ($connectionContent) {

                            if ($result->isSucceeded()) {

                                $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_RUN_ACTION, new ConnectionContextEvent([
                                    'connectionContent' => $connectionContent
                                ]));

                                $result = $result->getResult();
                                if ($result instanceof Awaitable) {
                                    $result->await(function ($result) use ($connectionContent) {
                                        if($result instanceof Throwable){
                                            Friday::$app->errorHandler->handleException($result);
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
                                    });
                                } elseif ($result instanceof Response) {
                                    $response = $result;
                                    $response->send();
                                } else {
                                    $response = $connectionContent->response;
                                    if ($result !== null) {
                                        $response->data = $result;
                                    }
                                    $response->send();
                                }
                            } else {
                                $throwable = $result->getException();

                                if ($throwable !== null) {
                                    if ($throwable instanceof InvalidRouteException) {
                                        $throwable = new NotFoundHttpException('Page not found.');
                                    }
                                }

                                $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR, new ConnectionContextErrorEvent([
                                    'connectionContent' => $connectionContent,
                                    'error' => $throwable
                                ]));

                                Friday::$app->errorHandler->handleException($throwable);
                            }

                        }, true);

                    } catch (Throwable $throwable) {
                        $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_ERROR, new ConnectionContextErrorEvent([
                            'connectionContent' => $connectionContent,
                            'error' => $throwable
                        ]));

                        Friday::$app->errorHandler->handleException($throwable);
                    }

                    $this->trigger(ConnectionContext::EVENT_CONNECTION_CONTENT_AFTER_ROUTING, new ConnectionContextEvent([
                        'connectionContent' => $connectionContent
                    ]));
                } else {
                    Friday::$app->errorHandler->handleException($result->getException());
                }
            }, true
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
        ]);
    }



    /**
     * @return Request|null
     */
    public function getRequest()
    {
        if ($this->_context !== null) {
            return $this->getContext()->getRequest();
        }
        return null;
    }

    /**
     * @return Response|null
     */
    public function getResponse()
    {
        if ($this->_context !== null) {
            return $this->getContext()->getResponse();
        }
        return null;
    }

    /**
     * @return Controller|null
     */
    public function getController()
    {
        if ($this->_context !== null) {
            return $this->getContext()->controller;
        }
        return null;
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_context !== null) {
            return $this->getContext()->user;
        }
        return null;
    }

    /**
     * @return Session|null
     */
    public function getSession()
    {
        if ($this->_context !== null) {
            return $this->getContext()->session;
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

    /**
     * @return Friday\Base\Component|null
     */
    public function getView(){
        return $this->getContext()->getView();
    }
}