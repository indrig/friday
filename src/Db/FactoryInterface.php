<?php
namespace Friday\Db;

interface FactoryInterface{

    public function createSchema(array $config = []);

    public function createConnection(array $config = []);
}