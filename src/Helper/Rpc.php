<?php
namespace Friday\Helper;

class Rpc{
    /**
     * @param $method
     * @param array $params
     *
     */
    public static function call($method, $params = []){
        $id = '';
        Json::encode(['method' => $method, 'params' => $params]);
    }

}