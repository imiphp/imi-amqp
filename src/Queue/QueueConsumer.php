<?php

declare(strict_types=1);

namespace Imi\AMQP\Queue;

use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Annotation\Queue;
use Imi\AMQP\Base\BaseConsumer;
use Imi\AMQP\Contract\IMessage;
use Imi\AMQP\Contract\IQueueConsumer;
use Imi\AMQP\Message;

class QueueConsumer extends BaseConsumer implements IQueueConsumer
{
    /**
     * 队列.
     */
    private ?\SplQueue $queue = null;

    public function __construct(
        /**
         * 本地缓存的队列长度.
         */
        protected int $queueLength, array $exchanges, array $queues, array $consumers, ?string $poolName = null)
    {
        parent::__construct();
        $this->poolName = $poolName;

        $list = [];
        foreach ($exchanges as $exchange)
        {
            $list[] = new Exchange(...$exchange);
        }
        $this->exchanges = $list;

        $list = [];
        foreach ($queues as $queue)
        {
            $list[] = new Queue(...$queue);
        }
        $this->queues = $list;

        $list = [];
        foreach ($consumers as $consumer)
        {
            $list[] = new Consumer(...$consumer);
        }
        $this->consumers = $list;

        $this->reopen();
    }

    /**
     * {@inheritDoc}
     */
    protected function initConfig(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function reopen(): void
    {
        if ($this->channel)
        {
            $this->close();
        }
        $this->connection = $this->getConnection();
        if ($this->connection->isConnected())
        {
            $this->channel = $this->connection->channel();
        }
        $this->queue = new \SplQueue();
    }

    /**
     * {@inheritDoc}
     */
    public function pop(float $timeout): ?Message
    {
        if (!$this->channel || !$this->channel->is_consuming())
        {
            $this->reopen();
            $this->channel->basic_qos(0, 1, false);
            $this->declareConsumer();
            $this->bindConsumer();
        }
        try
        {
            if ($this->queue->isEmpty())
            {
                $this->channel->wait(null, false, $timeout);
            }

            return $this->queue->pop() ?: null;
        }
        catch (\PhpAmqpLib\Exception\AMQPTimeoutException)
        {
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function bindConsumer(): void
    {
        foreach ($this->consumers as $consumer)
        {
            foreach ((array) $consumer->queue as $queueName)
            {
                $messageClass = $consumer->message ?? \Imi\AMQP\Message::class;
                $this->channel->basic_consume($queueName, $consumer->tag, false, false, false, false, function (\PhpAmqpLib\Message\AMQPMessage $message) use ($messageClass): void {
                    /** @var \Imi\AMQP\Message $messageInstance */
                    $messageInstance = new $messageClass();
                    $messageInstance->setAMQPMessage($message);
                    $this->consume($messageInstance);
                });
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function consume(IMessage $message): void
    {
        $this->queue->push($message);
    }
}
