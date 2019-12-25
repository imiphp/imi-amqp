<?php
namespace ImiApp\AMQP\Test;

use Imi\Bean\Annotation\Bean;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BaseConsumer;
use Imi\AMQP\Contract\IMessage;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Enum\ConsumerResult;
use Imi\AMQP\Annotation\Connection;

/**
 * 启动一个新连接消费
 * 
 * @Bean("TestConsumer")
 * @Connection(host="127.0.0.1", port=5672, user="guest", password="guest")
 * @Consumer(tag="tag-imi", queue="queue-imi-1", message=\ImiApp\AMQP\Test\TestMessage::class)
 */
class TestConsumer extends BaseConsumer
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
        return ConsumerResult::ACK;
    }

}
