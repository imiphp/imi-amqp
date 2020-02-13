<?php
namespace Imi\AMQP\Base\Traits;

use Imi\Log\Log;
use Imi\Bean\BeanFactory;
use Imi\Pool\PoolManager;
use Imi\AMQP\Pool\AMQPPool;
use Imi\AMQP\Annotation\Queue;
use Imi\Aop\Annotation\Inject;
use Imi\AMQP\Annotation\Consumer;
use Imi\AMQP\Annotation\Exchange;
use Imi\AMQP\Annotation\Publisher;
use Imi\AMQP\Annotation\Connection;
use Imi\Bean\Annotation\AnnotationManager;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

trait TAMQP
{
    /**
     * @Inject("AMQP")
     *
     * @var \Imi\AMQP\Pool\AMQP
     */
    protected $amqp;

    /**
     * 连接
     *
     * @var \PhpAmqpLib\Connection\AbstractConnection
     */
    protected $connection;

    /**
     * 频道
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;

    /**
     * 队列配置列表
     *
     * @var \Imi\AMQP\Annotation\Queue[]
     */
    protected $queues;

    /**
     * 交换机配置列表
     *
     * @var \Imi\AMQP\Annotation\Exchange[]
     */
    protected $exchanges;

    /**
     * 发布者列表
     *
     * @var \Imi\AMQP\Annotation\Publisher[]
     */
    protected $publishers;

    /**
     * 消费者列表
     *
     * @var \Imi\AMQP\Annotation\Consumer[]
     */
    protected $consumers;

    /**
     * 初始化配置
     *
     * @return void
     */
    protected function initConfig()
    {
        $class = BeanFactory::getObjectClass($this);
        $this->queues = AnnotationManager::getClassAnnotations($class, Queue::class);
        $this->exchanges = AnnotationManager::getClassAnnotations($class, Exchange::class);
        $this->publishers = AnnotationManager::getClassAnnotations($class, Publisher::class);
        $this->consumers = AnnotationManager::getClassAnnotations($class, Consumer::class);
    }

    /**
     * 获取连接对象
     *
     * @return \PhpAmqpLib\Connection\AbstractConnection
     */
    protected function getConnection(): AbstractConnection
    {
        $class = BeanFactory::getObjectClass($this);
        $connectionConfig = AnnotationManager::getClassAnnotations($class, Connection::class)[0] ?? null;
        if($connectionConfig)
        {
            if(null === $connectionConfig->poolName)
            {
                if(null !== $connectionConfig->host && null !== $connectionConfig->port && null !== $connectionConfig->user && null !== $connectionConfig->password)
                {
                    $connectionByPool = false;
                }
                else
                {
                    $connectionByPool = true;
                }
            }
        }
        else
        {
            $connectionByPool = true;
        }
        if($connectionByPool)
        {
            return AMQPPool::getInstance($connectionConfig->poolName ?? $this->amqp->getDefaultPoolName());
        }
        else
        {
            return new AMQPStreamConnection(
                $connectionConfig->host,
                $connectionConfig->port,
                $connectionConfig->user,
                $connectionConfig->password,
                $connectionConfig->vhost,
                $connectionConfig->insist,
                $connectionConfig->loginMethod, $connectionConfig->loginResponse,
                $connectionConfig->locale, $connectionConfig->connectionTimeout,
                $connectionConfig->readWriteTimeout,
                $connectionConfig->context,
                $connectionConfig->keepalive,
                $connectionConfig->heartbeat,
                $connectionConfig->channelRpcTimeout,
                $connectionConfig->sslProtocol
            );
        }
    }

    /**
     * 定义
     *
     * @return void
     */
    protected function declare()
    {
        foreach($this->exchanges as $exchange)
        {
            Log::debug(sprintf('exchangeDeclare: %s, %s', $exchange->name, $exchange->type));
            $this->channel->exchange_declare($exchange->name, $exchange->type, $exchange->passive, $exchange->durable, $exchange->autoDelete, $exchange->internal, $exchange->nowait, $exchange->arguments, $exchange->ticket);
        }
        foreach($this->queues as $queue)
        {
            Log::debug(sprintf('queueDeclare: %s', $queue->name, $exchange->type));
            $this->channel->queue_declare($queue->name, $queue->passive, $queue->durable, $queue->exclusive, $queue->autoDelete, $queue->nowait, $queue->arguments, $queue->ticket);
        }
    }

    /**
     * 定义发布者
     *
     * @return void
     */
    protected function declarePublisher()
    {
        $this->declare();
        foreach($this->publishers as $publisher)
        {
            foreach((array)$publisher->queue as $queueName)
            {
                if('' === $queueName)
                {
                    continue;
                }
                foreach((array)$publisher->exchange as $exchangeName)
                {
                    Log::debug(sprintf('queueBind: %s, %s, %s', $queueName, $exchangeName, $publisher->routingKey));
                    $this->channel->queue_bind($queueName, $exchangeName, $publisher->routingKey);
                }
            }
        }
    }

    /**
     * 定义消费者
     *
     * @return void
     */
    protected function declareConsumer()
    {
        $this->declare();
        foreach($this->consumers as $consumer)
        {
            foreach((array)$consumer->queue as $queueName)
            {
                foreach((array)$consumer->exchange as $exchangeName)
                {
                    Log::debug(sprintf('queueBind: %s, %s, %s', $queueName, $exchangeName, $consumer->routingKey));
                    $this->channel->queue_bind($queueName, $exchangeName, $consumer->routingKey);
                }
            }
        }
    }

}
