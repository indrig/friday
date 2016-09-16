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
                Friday::trace("Route requested: '$route'", __METHOD__);

                $connectionContent->setRequestedRoute($route);
            },
            //Error
            function (Throwable $throwable) use($connectionContent) {
                //Close connection end render error response
                Friday::error($throwable);
            }
        );
    }

    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     * @param string $route the route that specifies the action.
     * @param array $params the parameters to be passed to the action
     * @return mixed the result of the action.
     * @throws InvalidRouteException if the requested route cannot be resolved into an action successfully
     */
    public function runAction($route, $params = [])
    {

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