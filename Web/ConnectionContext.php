<?php
namespace Friday\Web;

use Friday;
use Friday\Base\BaseObject;
use Friday\Base\EventTrait;
use Friday\Base\Exception\RuntimeException;
use Friday\Base\Exception\InvalidRouteException;
use Friday\Promise\Deferred;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 * @property string|null $requestedRoute
 * @property Friday\EventLoop\LoopInterface $loop
 */
class ConnectionContext extends BaseObject
{
    const EVENT_CONNECTION_CONTENT_BEFORE_RUN_ACTION = 'before-run-action';
    const EVENT_CONNECTION_CONTENT_AFTER_RUN_ACTION = 'after-run-action';

    const EVENT_CONNECTION_CONTENT_BEFORE_ROUTING = 'before-routing';
    const EVENT_CONNECTION_CONTENT_AFTER_ROUTING = 'after-routing';

    const EVENT_CONNECTION_CONTENT_ERROR = 'error';

    const EVENT_CONNECTION_CONTENT_CLOSE = 'close';
    use EventTrait;

    /**
     * @var Request
     */
    private $_request;

    /**
     * @var Response
     */
    private $_response;

    /**
     * @var string|null
     */
    private $_requestedRoute;

    /**
     * @var Friday\EventLoop\LoopInterface
     */
    private $_loop;

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return static
     */
    public static function create(Request $request, Response $response)
    {
        $context = new static();
        $context->_request = $request;
        $context->_response = $response;

        $request->setConnectionContext($context);
        $response->setConnectionContext($context);

        return $context;
    }

    public function getRequestedRoute()
    {
        return $this->_requestedRoute;
    }

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
throw new \RuntimeException('aaa');
        if (is_array($parts)) {
            /* @var $controller Controller */
            list($controller, $actionID) = $parts;
            $this->_requestedRoute = $route;

            $controller->setConnectionContext($this);

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
        $response = $this->response;

        $response->send();
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
            call_user_func($callback);
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
            call_user_func($callback);
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


}