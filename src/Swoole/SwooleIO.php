<?php
namespace Imi\AMQP\Swoole;

use PhpAmqpLib\Wire\IO\AbstractIO;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;

/**
 * @source https://github.com/swoole/php-amqplib/blob/master/PhpAmqpLib/Wire/IO/SwooleIO.php
 */
class SwooleIO extends AbstractIO
{
    const READ_BUFFER_WAIT_INTERVAL = 100000;

    /** @var float */
    protected $read_write_timeout;

    /** @var resource */
    protected $context;

    /**
     * @var bool
     */
    protected $tcp_nodelay = false;

    /**
     * @var bool
     */
    protected $ssl = false;

    /** @var Swoole\Coroutine\Client */
    private $sock;

    private $buffer = '';

    public function __construct(
        $host,
        $port,
        $connection_timeout,
        $read_write_timeout,
        $context = null,
        $keepalive = false,
        $heartbeat = 0
    ) {
        if ($heartbeat !== 0 && ($read_write_timeout < ($heartbeat * 2))) {
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
     * @return void
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     */
    public function connect()
    {
        $sock = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        if (!$sock->connect($this->host, $this->port, $this->connection_timeout))
        {
            throw new AMQPRuntimeException(
                sprintf(
                    'Error Connecting to server(%s): %s ',
                    $sock->errCode,
                    swoole_strerror($sock->errCode)
                ),
                $sock->errCode
            );
        }
        $this->sock = $sock;
    }

    /**
     * Reconnects the socket
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }

    /**
     * @param int $len
     * @return string
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     */
    public function read($len)
    {
        $this->check_heartbeat();

        do
        {
            if ($len <= strlen($this->buffer))
            {
                $data = substr($this->buffer, 0, $len);
                $this->buffer = substr($this->buffer, $len);
                $this->last_read = microtime(true);

                return $data;
            }

            if (!$this->sock->connected)
            {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $read_buffer = $this->sock->recv($this->read_write_timeout ? $this->read_write_timeout : -1);
            if ($read_buffer === false)
            {
                if(110 === $this->sock->errCode)
                {
                    throw new AMQPTimeoutException('Error receiving data, errno=' . $this->sock->errCode);
                }
                else
                {
                    throw new AMQPRuntimeException('Error receiving data, errno=' . $this->sock->errCode);
                }
            }

            if ($read_buffer === '')
            {
                throw new AMQPConnectionClosedException('Broken pipe or closed connection');
            }

            $this->buffer .= $read_buffer;

        } while (true);


        return false;
    }

    /**
     * @param string $data
     * @throws \PhpAmqpLib\Exception\AMQPIOException
     * @throws \PhpAmqpLib\Exception\AMQPSocketException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function write($data)
    {
        $buffer = $this->sock->send($data);

        if ($buffer === false)
        {
            throw new AMQPRuntimeException('Error sending data');
        }

        if ($buffer === 0 && !$this->sock->connected)
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
        if($this->sock)
        {
            $this->sock->close();
            $this->sock = null;
        }
        $this->last_read = null;
        $this->last_write = null;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return int|mixed
     */
    protected function do_select($sec, $usec)
    {
        $this->check_heartbeat();

        return 1;
    }
}