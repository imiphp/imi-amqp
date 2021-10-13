<?php

declare(strict_types=1);

namespace Imi\AMQP\Base;

use Imi\AMQP\Base\Traits\TAMQP;
use Imi\AMQP\Contract\IConsumer;
use Imi\AMQP\Contract\IMessage;
use Imi\AMQP\Enum\ConsumerResult;
use Imi\App;
use Imi\Util\Imi;
use function Yurun\Swoole\Coroutine\goWait;

/**
 * 消费者基类.
 */
abstract class BaseConsumer implements IConsumer
{
    use TAMQP;

    public function __construct()
    {
        $this->initConfig();
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        $this->connection = $this->getConnection();
        $this->channel = $this->connection->channel();
        $this->declareConsumer();
        $this->bindConsumer();
        while ($this->channel && $this->channel->is_consuming())
        {
            $this->channel->wait();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function stop(): void
    {
        if ($this->channel)
        {
            $this->channel->close();
            $this->channel = null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->stop();
        $this->connection = null;
    }

    /**
     * 绑定消费者.
     */
    protected function bindConsumer(): void
    {
        foreach ($this->consumers as $consumer)
        {
            foreach ((array) $consumer->queue as $queueName)
            {
                $messageClass = $consumer->message ?? \Imi\AMQP\Message::class;
                $this->channel->basic_consume($queueName, $consumer->tag, false, false, false, false, function (\PhpAmqpLib\Message\AMQPMessage $message) use ($messageClass) {
                    try
                    {
                        /** @var \Imi\AMQP\Message $messageInstance */
                        $messageInstance = new $messageClass();
                        $messageInstance->setAMQPMessage($message);
                        if (Imi::checkAppType('swoole'))
                        {
                            $result = goWait(function () use ($messageInstance) {
                                return $this->consume($messageInstance);
                            });
                        }
                        else
                        {
                            $result = $this->consume($messageInstance);
                        }
                        switch ($result)
                        {
                            case ConsumerResult::ACK:
                                $this->channel->basic_ack($message->getDeliveryTag());
                                break;
                            case ConsumerResult::NACK:
                                $this->channel->basic_nack($message->getDeliveryTag());
                                break;
                            case ConsumerResult::NACK_REQUEUE:
                                $this->channel->basic_nack($message->getDeliveryTag(), false, true);
                                break;
                            case ConsumerResult::REJECT:
                                $this->channel->basic_reject($message->getDeliveryTag(), false);
                                break;
                            case ConsumerResult::REJECT_REQUEUE:
                                $this->channel->basic_reject($message->getDeliveryTag(), true);
                                break;
                        }
                    }
                    catch (\Throwable $th)
                    {
                        App::getBean('ErrorLog')->onException($th);
                    }
                });
            }
        }
    }

    /**
     * 消费任务
     *
     * @return mixed
     */
    abstract protected function consume(IMessage $message);
}
