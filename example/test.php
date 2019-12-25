<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

define('HOST', 'localhost');
define('PORT', 5672);
define('USER', 'guest');
define('PASS', 'guest');
define('VHOST', '/');

\Swoole\Runtime::enableCoroutine();

// publish
go(function(){

    
    $exchange = 'router';
    $queue = 'msgs';
    
    $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
    $channel = $connection->channel();
    $channel->close();
    $channel = $connection->channel();

    /*
        The following code is the same both in the consumer and the producer.
        In this way we are sure we always have a queue to consume from and an
            exchange where to publish messages.
    */
    
    /*
        name: $queue
        passive: false
        durable: true // the queue will survive server restarts
        exclusive: false // the queue can be accessed in other channels
        auto_delete: false //the queue won't be deleted once the channel is closed.
    */
    $channel->queue_declare($queue, false, true, false, false);
    
    /*
        name: $exchange
        type: direct
        passive: false
        durable: true // the exchange will survive server restarts
        auto_delete: false //the exchange won't be deleted once the channel is closed.
    */
    
    $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
    
    // $channel->set_ack_handler(function () use (&$result) {
    //     $result = true;
    // });
    $channel->queue_bind($queue, $exchange);
    while(true)
    {
        
        $messageBody = json_encode([
            'id'    =>  mt_rand(),
        ]);
        $message = new AMQPMessage($messageBody, array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange);
        // $channel->wait_for_pending_acks_returns(10);
        fwrite(STDOUT, 'publish:' . $messageBody . PHP_EOL);
        // sleep(1);
        \Swoole\Coroutine::sleep(0.5);
    }
    $channel->close();
    $connection->close();
});

function consumer(AMQPStreamConnection $connection, $consumerTag)
{
    $channel = $connection->channel();
    $exchange = 'router';
    $queue = 'msgs';
    /*
        The following code is the same both in the consumer and the producer.
        In this way we are sure we always have a queue to consume from and an
            exchange where to publish messages.
    */

    /*
        name: $queue
        passive: false
        durable: true // the queue will survive server restarts
        exclusive: false // the queue can be accessed in other channels
        auto_delete: false //the queue won't be deleted once the channel is closed.
    */
    $channel->queue_declare($queue, false, true, false, false);
    $channel->queue_declare('q2', false, true, false, false);

    /*
        name: $exchange
        type: direct
        passive: false
        durable: true // the exchange will survive server restarts
        auto_delete: false //the exchange won't be deleted once the channel is closed.
    */

    $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
    $channel->exchange_declare('router2', AMQPExchangeType::DIRECT, false, true, false);

    $channel->queue_bind($queue, $exchange);

    $channel->basic_consume($queue, $consumerTag, false, false, false, false, function(\PhpAmqpLib\Message\AMQPMessage $message) use($channel, $consumerTag){
        $channel->basic_ack($message->getDeliveryTag());
        fwrite(STDOUT, $consumerTag . '-' . \Swoole\Coroutine::getuid() . ':' . $message->getBody() . PHP_EOL);
        // 模拟耗时任务
        \Swoole\Coroutine::sleep(mt_rand(10, 500) / 1000);
    });

    $channel->basic_consume('q2', $consumerTag, false, false, false, false, function(\PhpAmqpLib\Message\AMQPMessage $message) use($channel, $consumerTag){
        $channel->basic_ack($message->getDeliveryTag());
        fwrite(STDOUT, $consumerTag . '-' . \Swoole\Coroutine::getuid() . ':' . $message->getBody() . PHP_EOL);
        // 模拟耗时任务
        \Swoole\Coroutine::sleep(mt_rand(10, 500) / 1000);
    });

    // Loop as long as the channel has callbacks registered
    while ($channel ->is_consuming()) {
        $channel->wait();
    }

}
return;
go(function(){

    $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
    // 
    go(function() use($connection){
        consumer($connection, 'consumer1');
    });
    
    // go(function() use($connection){
    //     consumer($connection, 'consumer1');
    // });
    
    // go(function() use($channel){
    //     consumer($channel, 'consumer1');
    // });
    
    // go(function() use($channel){
    //     consumer($channel, 'consumer1');
    // });
    
    // go(function() use($channel){
    //     consumer($channel, 'consumer1');
    // });
    
});