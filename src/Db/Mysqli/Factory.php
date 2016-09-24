<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Db\FactoryInterface;

class Factory implements FactoryInterface{
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
}