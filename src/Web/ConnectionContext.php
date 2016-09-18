<?php
namespace Friday\Web;

use Friday;
use Friday\Base\EventTrait;
use Friday\Base\Exception\RuntimeException;
use Friday\Base\Exception\InvalidRouteException;
use Friday\Di\ServiceLocator;
use Friday\Promise\Deferred;
use Throwable;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 * @property User $user
 * @property Session $session
 * @property string|null $requestedRoute
 * @property Friday\EventLoop\LoopInterface $loop
 * @property Controller $controller
 */
class ConnectionContext extends ServiceLocator
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
     * @var Friday\EventLoop\LoopInterface
     */
    private $_loop;

    /**
     * @var Controller
     */
    private $_controller;


    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * @return Response
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
     * @return Friday\Promise\PromiseInterface
     */
    public function runAction($route, $params = [])
    {
        $deferred = new Deferred();
        $parts = Friday::$app->createController($route);
        if (is_array($parts)) {
            /* @var $controller Controller */
            list($controller, $actionID) = $parts;
            $this->_requestedRoute = $route;

            $this->setController($controller);


            $this->post(function () use ($deferred, $controller, $actionID, $params) {
                $deferred->resolve($controller->runAction($actionID, $params));
            });
        } else {
            $this->post(function () use ($deferred, $route) {
                $deferred->reject(new InvalidRouteException("Unable to resolve the request '{$route}'."));
            });
        }

        return $deferred->promise();
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
     */
    public function post(callable $callback)
    {
        $this->loop->addTimer(0.000001, function () use ($callback) {

            $application = Friday::$app;

            $oldContext = $application->currentContext;

            $application->currentContext = $this;

            try {
                call_user_func($callback);
            } catch (Throwable $throwable) {
                $this->error($throwable);
            }

            $application->currentContext = $oldContext;
        });
    }

    /**
     * @param callable $callback
     * @param float $delay
     * @return Friday\EventLoop\TimerInterface
     */
    public function postDelayed(callable $callback, float $delay)
    {
        $this->loop->addTimer($delay, function () use ($callback) {
            $application = Friday::$app;

            $oldContext = $application->currentContext;

            $application->currentContext = $this;
            try {
                call_user_func($callback);
            } catch (Throwable $throwable) {
                $this->error($throwable);
            }
            $application->currentContext = $oldContext;
        });
    }

    /**
     * @return Friday\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        if ($this->_loop === null) {
            $this->_loop = Friday::$app->runLoop;
        }

        return $this->_loop;
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
     * @return User
     */
    public function getUser(){
        return $this->get('user');
    }


    /**
     * @return mixed
     */
    public function getSession(){
        return $this->get('session');
    }


    public function finish(){
        $this->response->end();

        //TODO: unset all
        Friday::$app->detachContext($this);
    }

}