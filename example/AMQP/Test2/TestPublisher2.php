<?php
namespace ImiApp\AMQP\Test2;

use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BasePublisher;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Annotation\Connection;

/**
 * @Bean("TestPublisher2")
 * @Consumer(tag="tag-imi", queue="queue-imi-2", exchange="exchange-imi", routingKey="imi-2")
 * @Queue(name="queue-imi-2", routingKey="imi-2")
 * @Exchange(name="exchange-imi")
 */
class TestPublisher2 extends BasePublisher
{

}
