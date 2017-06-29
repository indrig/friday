<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\ContextInterface;
use Friday\Base\Deferred;
use Friday\Base\EventTrait;
use Friday\Base\Exception\RuntimeException;
use Friday\Base\Exception\InvalidRouteException;
use Friday\Di\ServiceLocator;
use Throwable;
use SplObjectStorage;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 * @property User $user
 * @property Session $session
 * @property string|null $requestedRoute
 * @property Friday\Base\Looper $looper
 * @property Controller $controller
 * @property bool $isFinished
 */
class ConnectionContext extends ServiceLocator implements ContextInterface
{
    const EVENT_CONNECTION_CONTENT_BEFORE_RUN_ACTION = 'before-run-action';
    const EVENT_CONNECTION_CONTENT_AFTER_RUN_ACTION = 'after-run-action';

    const EVENT_CONNECTION_CONTENT_BEFORE_ROUTING = 'before-routing';
    const EVENT_CONNECTION_CONTENT_AFTER_ROUTING = 'after-routing';

    const EVENT_CONNECTION_CONTENT_ERROR = 'error';

    const EVENT_CONNECTION_CONTENT_CLOSE = 'close';

    use EventTrait;

    /**
     * @var string|null
     */
    private $_requestedRoute;

    /**
     * @var Friday\Base\Looper
     */
    private $_looper;

    /**
     * @var Controller
     */
    private $_controller;

    /**
     * @var SplObjectStorage
     */
    private $_timers;

    /**
     * @var bool
     */
    private $_isFinished = false;

    /**
     * @var Session
     */
    private $_session;

    /**
     * @var View
     */
    private $_view;

    /**
     * @var AssetManager
     */
    private $_assetManager;

    /**
     * @var User
     */
    private $_user;

    public function init()
    {
        parent::init();

        $this->_timers = new SplObjectStorage();
    }

    /**
     * @return Request|Friday\Base\Component
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * @return Response|Friday\Base\Component
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return static
     */
    public static function create(Request $request, Response $response)
    {
        $context = new static();
        $context->set('request', $request);
        $context->set('response', $response);

        $request->setConnectionContext($context);
        $response->setConnectionContext($context);

        return $context;
    }

    /**
     * @return null|string
     */
    public function getRequestedRoute()
    {
        return $this->_requestedRoute;
    }

    /**
     * @param $requestedRoute
     * @return $this
     */
    public function setRequestedRoute($requestedRoute)
    {
        if ($this->_requestedRoute !== null) {
            throw new RuntimeException('User context parameter "requestedRoute" already set.');
        }
        $this->_requestedRoute = $requestedRoute;
        return $this;
    }

    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     * @param string $route the route that specifies the action.
     * @param array $params the parameters to be passed to the action
     * @return Awaitable
     */
    public function runAction($route, $params = []) : Awaitable
    {
        $deferred = new Deferred();
        $parts = Friday::$app->createController($route);
        if (is_array($parts)) {
            /* @var $controller Controller */
            list($controller, $actionID) = $parts;
            $this->_requestedRoute = $route;

            $this->setController($controller);

            $this->task(function () use ($deferred, $controller, $actionID, $params) {
                $deferred->result($controller->runAction($actionID, $params));
            });
        } else {
            $this->task(function () use ($deferred, $route) {
                $deferred->exception(new InvalidRouteException("Unable to resolve the request '{$route}'."));
            });
        }

        return $deferred->awaitable();
    }

    /**
     * @param null $throwable
     */
    public function error($throwable = null)
    {
        Friday::$app->errorHandler->handleException($throwable);
    }

    /**
     * @param callable $callback
     * @return Friday\Base\Task
     */
    public function task(callable $callback)
    {
        $timer = $this->looper->task(function ($timer) use ($callback) {
            $application = Friday::$app;

            $oldContext = $application->getContext();

            $application->setContext($this);

            try {
                call_user_func($callback);
            } catch (Throwable $throwable) {
                $this->error($throwable);
            }

            $application->setContext($oldContext);

            $this->_timers->detach($timer);

        });
        $this->_timers->attach($timer);

        return $timer;
    }

    /**
     * @param callable $callback
     * @param float $delay
     * @return Friday\Base\Task
     */
    public function taskWithDelayed(callable $callback, float $delay)
    {
        $timer = $this->looper->taskWithDelayed(function ($timer) use ($callback) {
            $application = Friday::$app;

            $oldContext = $application->getContext();

            $application->setContext($this);
            try {
                call_user_func($callback);
            } catch (Throwable $throwable) {
                $this->error($throwable);
            }
            $application->setContext($oldContext);

            $this->_timers->detach($timer);
        }, $delay);

        $this->_timers->attach($timer);

        return $timer;
    }

    /**
     * @return Friday\Base\Looper
     */
    public function getLooper()
    {
        if ($this->_looper === null) {
            $this->_looper = Friday::$app->getLooper();
        }

        return $this->_looper;
    }

    /**
     * @return mixed
     */
    public function getController(){
        return $this->_controller;
    }

    /**
     * @param Controller $controller
     * @return $this
     */
    public function setController($controller){
        $controller->setConnectionContext($this);
        $this->_controller = $controller;
        return $this;
    }

    /**
     * @return User|Friday\Base\Component
     */
    public function getUser(){
        return $this->get('user');
    }


    /**
     * @return Session
     */
    public function getSession(){

        if($this->_session === null){
            $this->_session = Friday::createObject([
                'class' => 'Friday/Web/Session'
            ]);
            $this->_session->setConnectionContext($this);

        }

        return $this->_session;
    }

    /**
     * @return View
     */
    public function getView(){

        if($this->_view === null){
            $this->_view = Friday::createObject([
                'class' => 'Friday\Web\View'
            ]);
            $this->_view->setConnectionContext($this);
        }

        return $this->_view;
    }

    /**
     * @return AssetManager
     */
    public function getAssetsManager(){

        if($this->_assetManager === null){
            $this->_assetManager = Friday::createObject([
                'class' => 'Friday\Web\AssetManager'
            ]);
            $this->_assetManager->setConnectionContext($this);
        }

        return $this->_assetManager;
    }

    public function finish(){

        $this->response->end();

        $this->destroy();

        Friday::$app->detachContext($this);
        $this->_isFinished = true;
    }

    /**
     *
     */
    protected function destroy(){
        $this->clearEvents();
        $this->_controller = null;
        $components =   $this->getComponents(false);
        foreach ($components as $component) {
            if(method_exists($component, 'setConnectionContext')) {
                $component->setConnectionContext(null);
            }
        }

        if($this->_session !== null){
            unset($this->_session);
        }


        if($this->_view !== null){
            unset($this->_view);
        }

        if($this->_assetManager !== null){
            unset($this->_assetManager);
        }
    }

    /**
     * @return bool
     */
    public function getIsFinished(){
        return $this->_isFinished;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [];
    }

}