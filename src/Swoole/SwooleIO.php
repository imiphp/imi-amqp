<?php

declare(strict_types=1);

namespace Imi\AMQP\Swoole;

use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Wire\IO\AbstractIO;

/**
 * @source https://github.com/swoole/php-amqplib/blob/master/PhpAmqpLib/Wire/IO/SwooleIO.php
 */
class SwooleIO extends AbstractIO
{
    public const READ_BUFFER_WAIT_INTERVAL = 100000;

    protected float $read_write_timeout;

    /**
     * @var resource|null
     */
    protected $context;

    protected bool $tcp_nodelay = false;

    protected bool $ssl = false;

    private ?\Swoole\Coroutine\Client $sock = null;

    private string $buffer = '';

    /**
     * @param resource|null $context
     */
    public function __construct(
        string $host,
        int $port,
        float $connection_timeout,
        float $read_write_timeout,
        $context = null,
        bool $keepalive = false,
        int $heartbeat = 0
    ) {
        if (0 !== $heartbeat && ($read_write_timeout < ($heartbeat * 2)))
        {
            throw new \InvalidArgumentException('read_write_timeout must be at least 2x the heartbeat');
        }
        $this->host = $host;
        $this->port = $port;
        $this->connection_timeout = $connection_timeout;
        $this->read_write_timeout = $read_write_timeout;
        $this->context = $context;
        $this->keepalive = $keepalive;
        $this->heartbeat = $heartbeat;
        $this->initial_heartbeat = $heartbeat;
    }

    /**
     * Set ups the connection.
     *
     * @return void
     *
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function connect()
    {
        $sock = new \Swoole\Coroutine\Client(\SWOOLE_SOCK_TCP);
        if (!$sock->connect($this->host, $this->port, $this->connection_timeout))
        {
            throw new AMQPRuntimeException(sprintf('Error Connecting to server(%s): %s ', $sock->errCode, swoole_strerror($sock->errCode)), $sock->errCode);
        }
        $this->sock = $sock;
    }

    /**
     * Reconnects the socket.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }

    /**
     * @param int $len
     *
     * @return string|false
     *
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     */
    public function read($len)
    {
        $this->check_heartbeat();

        while (true)
        {
            if ($len <= \strlen($this->buffer))
            {
                $data = substr($this->buffer, 0, $len);
                $this->buffer = substr($this->buffer, $len);
                $this->last_read = microtime(true);

                return $data;
            }

            if (!$this->sock || !$this->sock->connected)
            {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $read_buffer = $this->sock->recv($this->read_write_timeout ?: -1);
            if (false === $read_buffer)
            {
                if (110 === $this->sock->errCode)
                {
                    throw new AMQPTimeoutException('Error receiving data, errno=' . $this->sock->errCode);
                }
                else
                {
                    throw new AMQPRuntimeException('Error receiving data, errno=' . $this->sock->errCode);
                }
            }

            if ('' === $read_buffer)
            {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $this->buffer .= $read_buffer;
        }

        // @phpstan-ignore-next-line
        return false;
    }

    /**
     * @param string $data
     *
     * @return void
     *
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function write($data)
    {
        $buffer = $this->sock->send($data);

        if (false === $buffer)
        {
            throw new AMQPConnectionClosedException('Error sending data, errno=' . $this->sock->errCode);
        }

        if (0 === $buffer && !$this->sock->connected)
        {
            throw new AMQPConnectionClosedException('Broken pipe or closed connection');
        }

        $this->last_write = microtime(true);
    }

    /**
     * @return void
     */
    public function close()
    {
        if ($this->sock)
        {
            $this->sock->close();
            $this->sock = null;
        }
        // @phpstan-ignore-next-line
        $this->last_read = null;
        // @phpstan-ignore-next-line
        $this->last_write = null;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        // @phpstan-ignore-next-line
        return $this->sock;
    }

    /**
     * @param int $sec
     * @param int $usec
     *
     * @return int|mixed
     */
    protected function do_select($sec, $usec)
    {
        return 1;
    }

    /**
     * Heartbeat logic: check connection health here.
     *
     * @return void
     *
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function check_heartbeat()
    {
    }
}
