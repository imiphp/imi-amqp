<?php
namespace ImiApp\AMQP\Test;

use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BasePublisher;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Annotation\Connection;

/**
 * @Bean("TestPublisher")
 * @Connection(host="127.0.0.1", port=5672, user="guest", password="guest")
 * @Consumer(tag="tag-imi", queue="queue-imi-1", exchange="exchange-imi", routingKey="imi-1")
 * @Queue(name="queue-imi-1", routingKey="imi-1")
 * @Exchange(name="exchange-imi")
 */
class TestPublisher extends BasePublisher
{

}
