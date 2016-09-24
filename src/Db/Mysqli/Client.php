<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Db\ClientInterface;

class Client implements ClientInterface{

    private $poolDeferred   = [];
    private $poolStatement  = [];

    public function createSchema(array $config = []){
        if(!isset($config['class'])) {
            $config['class'] = __NAMESPACE__ . '\Schema';
        }
        return Friday::createObject($config);
    }

    public function createConnection(array $config = []){
        if(!isset($config['class'])) {
            $config['class'] = __NAMESPACE__ . '\Connection';
        }
        return Friday::createObject($config);
    }


    public function addPoll(){

    }
}