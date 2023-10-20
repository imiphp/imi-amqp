<?php

declare(strict_types=1);

namespace Imi\AMQP\Pool;

use Imi\Log\Log;
use Imi\Pool\BasePoolResource;
use Imi\Swoole\Util\Coroutine;
use Imi\Util\Imi;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPWriter;
use Swoole\Coroutine\Channel;

/**
 * AMQP 客户端连接池的资源.
 */
class AMQPResource extends BasePoolResource
{
    /**
     * 重置状态的 Channel，重置中不为 null.
     *
     * 为兼容无 Swoole 的环境，所以声明为非强类型
     *
     * @noRector
     */
    private ?\Swoole\Coroutine\Channel $resetingChannel = null;

    private bool $closed = false;

    public function __construct(\Imi\Pool\Interfaces\IPool $pool,
        /**
         * AMQP 客户端.
         */
        private AbstractConnection $connection)
    {
        parent::__construct($pool);
        $this->closed = !$connection->isConnected();
    }

    /**
     * 打开
     */
    public function open(): bool
    {
        if (!$this->connection->isConnected())
        {
            $this->connection->reconnect();
        }

        $result = $this->connection->isConnected();
        $this->closed = !$result;

        return $result;
    }

    /**
     * 关闭.
     */
    public function close(): void
    {
        $this->closed = true;
        if ($this->resetingChannel)
        {
            $this->resetingChannel->pop(30);
        }
        try
        {
            if (!Imi::checkAppType('swoole') || Coroutine::isIn())
            {
                $this->connection->close();
            }
        }
        catch (\Exception)
        {
            return;
        }
    }

    /**
     * 获取对象实例.
     *
     * @return \PhpAmqpLib\Connection\AbstractConnection
     */
    public function getInstance()
    {
        return $this->connection;
    }

    /**
     * 重置资源，当资源被使用后重置一些默认的设置.
     */
    public function reset(): void
    {
        if ($this->closed)
        {
            return;
        }
        $inSwoole = \defined('SWOOLE_VERSION') && Coroutine::isIn();
        if ($inSwoole)
        {
            $this->resetingChannel = new Channel();
        }
        $connection = $this->connection;
        foreach ($connection->channels as $key => $channel)
        {
            if (0 === $key)
            {
                continue;
            }
            if ($channel instanceof \PhpAmqpLib\Channel\AMQPChannel)
            {
                try
                {
                    $channel->close();
                }
                catch (\Throwable)
                {
                    /* Ignore closing errors */
                }
            }
            unset($this->connection->channels[$key]);
        }
        if ($inSwoole)
        {
            $channel = $this->resetingChannel;
            $this->resetingChannel = null;
            $channel->push(1);
        }
    }

    /**
     * 检查资源是否可用.
     */
    public function checkState(): bool
    {
        if (!$this->isOpened())
        {
            return false;
        }
        try
        {
            $pkt = new AMQPWriter();
            $pkt->write_octet(8);
            $pkt->write_short(0);
            $pkt->write_long(0);
            $pkt->write_octet(0xCE);
            $this->connection->write($pkt->getvalue());

            return true;
        }
        catch (\Throwable $th)
        {
            Log::error($th);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isOpened(): bool
    {
        return !$this->closed && $this->connection->isConnected();
    }
}
