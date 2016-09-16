<?php
namespace Friday\Web;

use Fridaty\base\InvalidRouteException;
use Friday;
use Friday\Base\BaseObject;
use Friday\Base\EventTrait;
use Friday\Base\Exception\RuntimeException;
use Friday\Promise\Deferred;

/**
 * Class ConnectionContext
 * @package Friday\Web
 *
 * @property Request $request
 * @property Response $response
 * @property string|null $requestedRoute
 */
class ConnectionContext extends BaseObject {

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
     * @return Request
     */
    public function getRequest(){
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse(){
        return $this->_response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return static
     */
    public static function create(Request $request, Response $response){
        $context = new static();
        $context->_request  = $request;
        $context->_response = $response;

        $request->setConnectionContext($context);
        $response->setConnectionContext($context);

        return $context;
    }

    public function getRequestedRoute(){
        return $this->_requestedRoute;
    }

    public function setRequestedRoute($requestedRoute){
        if($this->_requestedRoute !== null) {
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

            $controller->setConnectionContext($this);

           /* $oldController = Yii::$app->controller;
            Yii::$app->controller = $controller;
            $result = $controller->runAction($actionID, $params);
            Yii::$app->controller = $oldController;
*/
           Friday\Helper\RunLoopHelper::post(function () use ($deferred, $controller, $actionID, $params) {
               $deferred->resolve($controller->runAction($actionID, $params));
           });
        } else {
            Friday\Helper\RunLoopHelper::post(function () use ($deferred) {
                $deferred->resolve(new InvalidRouteException("Unable to resolve the request '{$route}'."));
            });
        }

        return $deferred->promise();
    }

}