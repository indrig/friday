<?php
namespace Friday\Base;

use Friday\Base\Exception\InvalidArgumentException;
use Friday\Helper\Console;
use Friday\Helper\Json;
use Friday\Stream\Event\ContentEvent;
use Friday\Stream\Stream;
use Throwable;

class InnerChildProcess extends ChildProcess{

    public function start(Looper $loop = null, $interval = 0.1)
    {
        parent::start($loop, $interval);

        $this->stdout->on(Stream::EVENT_CONTENT, function(ContentEvent $event) {
            try {
                $command = Json::decode($event->content);
            }catch (InvalidArgumentException $exception) {
                echo $event->content;
                return;
            }


            if(is_array($command) && isset($command['id']) && isset($command['method'])) {
                if(!isset($command['params'])) {
                    $params = [];
                } else {
                    $params = (array)$command['params'];

                }

                $id = $command['id'];
                try {

                    $result = call_user_func_array($command['method'], $params);
                    $this->commandResult($id, $result);
                    echo "Command {$id} - result\n";
                } catch (Throwable $throwable){
                    $this->commandError($id, $throwable);
                    echo "Command {$id} - error\n";
                }
            }

        });
    }

    public function commandResult($id, $result){

        $json = Json::encode([
            'id'        => $id,
            'result'    => $result,
            'error'    => null
        ]);

        $this->stdin->write($json);
    }

    public function commandError($id, $error){

        $json = Json::encode([
            'id'        => $id,
            'result'    => null,
            'error'    => $error
        ]);

        $this->stdin->write($json);
    }
}
