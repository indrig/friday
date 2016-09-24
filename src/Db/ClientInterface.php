<?php
namespace Friday\Db;

interface ClientInterface{

    public function createSchema(array $config = []);

    public function createConnection(array $config = []);
}