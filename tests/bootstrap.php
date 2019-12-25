<?php
$loader = require dirname(__DIR__) . '/vendor/autoload.php';

(function(){
    \Swoole\Runtime::enableCoroutine();
    $redis = new \Redis;
    if(!$redis->connect('127.0.0.1', 6379))
    {
        exit('Redis connect failed');
    }
    $redis->del($redis->keys('imi-amqp:*'));
    $redis->close();
})();
