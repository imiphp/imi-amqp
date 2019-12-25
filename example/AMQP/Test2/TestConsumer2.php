<?php
namespace ImiApp\AMQP\Test2;

use Imi\Redis\Redis;
use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BaseConsumer;
use Imi\AMQP\Contract\IMessage;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Enum\ConsumerResult;
use Imi\AMQP\Annotation\Connection;

/**
 * 使用连接池中的连接消费
 * 
 * @Bean("TestConsumer2")
 * @Consumer(tag="tag-imi", queue="queue-imi-2", message=\ImiApp\AMQP\Test2\TestMessage2::class)
 */
class TestConsumer2 extends BaseConsumer
{
    /**
     * 消费任务
     *
     * @param \Imi\AMQP\Contract\IMessage $message
     * @return void
     */
    protected function consume(IMessage $message)
    {
        var_dump(__CLASS__, $message->getBody(), get_class($message));
        // $messageInstance = TestMessage2::fromBody($message);
        // $data = $messageInstance->getBodyData();
        // Redis::set('imi-amqp:consume:' . $message->getBody())
        return ConsumerResult::ACK;
    }

}
