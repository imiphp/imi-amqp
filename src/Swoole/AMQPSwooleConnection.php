<?php
namespace Imi\AMQP\Swoole;

use PhpAmqpLib\Connection\AbstractConnection;

/**
 * @source https://github.com/swoole/php-amqplib/blob/master/PhpAmqpLib/Connection/AMQPSwooleConnection.php
 */
class AMQPSwooleConnection extends AbstractConnection
{
    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        $connection_timeout = 3.0,
        $read_write_timeout = 3.0,
        $context = null,
        $keepalive = false,
        $heartbeat = 0,
        $channel_rpc_timeout = 0.0,
        $ssl_protocol = null
    ) {
        $io = new SwooleIO($host, $port, $connection_timeout, $read_write_timeout, $context, $keepalive, $heartbeat);

        parent::__construct(
            $user,
            $password,
            $vhost,
            $insist,
            $login_method,
            $login_response,
            $locale,
            $io,
            $heartbeat,
            $connection_timeout,
            $channel_rpc_timeout
        );
    }

}