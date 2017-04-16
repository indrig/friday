<?php
namespace Friday\Console;

use Friday\Base\AbstractApplication;
use Friday\Base\Exception\Exception;
use Friday\Base\Exception\ExitException;
use Friday\Base\Exception\InvalidRouteException;

class Application extends AbstractApplication{
    /**
     * The option name for specifying the application configuration file path.
     */
    const OPTION_APPCONFIG = 'appconfig';

    /**
     * @var string the default route of this application. Defaults to 'help',
     * meaning the `help` command.
     */
    public $defaultRoute = 'help';

    /**
     * @event Event an event raised before the application starts to handle a request.
     */
    const EVENT_BEFORE_REQUEST = 'beforeRequest';
    /**
     * @event Event an event raised after the application successfully handles a request (before the response is sent out).
     */
    const EVENT_AFTER_REQUEST = 'afterRequest';
    /**
     * Application state used by [[state]]: application just started.
     */
    const STATE_BEGIN = 0;
    /**
     * Application state used by [[state]]: application is initializing.
     */
    const STATE_INIT = 1;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_BEFORE_REQUEST]].
     */
    const STATE_BEFORE_REQUEST = 2;
    /**
     * Application state used by [[state]]: application is handling the request.
     */
    const STATE_HANDLING_REQUEST = 3;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_AFTER_REQUEST]]..
     */
    const STATE_AFTER_REQUEST = 4;
    /**
     * Application state used by [[state]]: application is about to send response.
     */
    const STATE_SENDING_RESPONSE = 5;
    /**
     * Application state used by [[state]]: application has ended.
     */
    const STATE_END = 6;

    /**
     * @var Controller
     */
    public $controller;

    /**
     * @var integer the current application state during a request handling life cycle.
     * This property is managed by the application. Do not modify this property.
     */
    public $state;

    /**
     * @var string the requested route
     */
    public $requestedRoute;

    /**
     * @var boolean whether to enable the commands provided by the core framework.
     * Defaults to true.
     */
    public $enableCoreCommands = true;

    /**
     * Initialize the application.
     */
    public function init()
    {
        parent::init();
        if ($this->enableCoreCommands) {
            foreach ($this->coreCommands() as $id => $command) {
                if (!isset($this->controllerMap[$id])) {
                    $this->controllerMap[$id] = $command;
                }
            }
        }
        // ensure we have the 'help' command so that we can list the available commands
        if (!isset($this->controllerMap['help'])) {
            $this->controllerMap['help'] = 'Friday\Console\Controller\HelpController';
        }
    }

    public function run(){
        try {

            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);
            $this->state = self::STATE_HANDLING_REQUEST;
            $response = $this->handleRequest($this->getRequest());

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);

            $this->state = self::STATE_SENDING_RESPONSE;
            $response->send();

            $this->state = self::STATE_END;

            return $response->exitStatus;

        } catch (ExitException $e) {

            $this->end($e->statusCode, isset($response) ? $response : null);
            return $e->statusCode;

        }
    }

    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'request'       => ['class' => Request::class],
            'response'      => ['class' => Response::class],
            'errorHandler'  => ['class' => ErrorHandler::class],
        ]);
    }

    /**
     * Returns the request component.
     * @return Request the request component.
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * Returns the response component.
     * @return Response the response component.
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled
     * @return Response the resulting response
     */
    public function handleRequest($request)
    {
        list ($route, $params) = $request->resolve();
        $this->requestedRoute = $route;
        $result = $this->runAction($route, $params);

        if ($result instanceof Response) {
            return $result;
        } else {
            $response = $this->getResponse();
            $response->exitStatus = $result;

            return $response;
        }
    }

    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     *
     * For example, to run `public function actionTest($a, $b)` assuming that the controller has options the following
     * code should be used:
     *
     * ```php
     * \Friday::$app->runAction('controller/test', ['option' => 'value', $a, $b]);
     * ```
     *
     * @param string $route the route that specifies the action.
     * @param array $params the parameters to be passed to the action
     * @return integer|Response the result of the action. This can be either an exit code or Response object.
     * Exit code 0 means normal, and other values mean abnormal. Exit code of `null` is treaded as `0` as well.
     * @throws Exception if the route is invalid
     */
    public function runAction($route, $params = [])
    {
        try {
            $res = parent::runAction($route, $params);
            return is_object($res) ? $res : (int)$res;
        } catch (InvalidRouteException $e) {
            throw new Exception("Unknown command \"$route\".", 0, $e);
        }
    }

    /**
     * Terminates the application.
     * This method replaces the `exit()` function by ensuring the application life cycle is completed
     * before terminating the application.
     * @param integer $status the exit status (value 0 means normal exit while other values mean abnormal exit).
     * @param Response $response the response to be sent. If not set, the default application [[response]] component will be used.
     * @throws ExitException if the application is in testing mode
     */
    public function end($status = 0, $response = null)
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ? : $this->getResponse();
            $response->send();
        }

        if (FRIDAY_ENV_TEST || FRIDAY_ENV_DEV) {
            throw new ExitException($status);
        } else {
            exit($status);
        }
    }

    /**
     * Returns the configuration of the built-in commands.
     * @return array the configuration of the built-in commands.
     */
    public function coreCommands()
    {
        return [
            'help'          => \Friday\Console\Controller\HelpController::class,
            'web-server'    => \Friday\Console\Controller\WebServerController::class,
        ];
    }
}