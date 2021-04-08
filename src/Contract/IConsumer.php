<?php

namespace Imi\AMQP\Contract;

/**
 * 消费者.
 */
interface IConsumer
{
    /**
     * 运行消费循环.
     *
     * @return void
     */
    public function run();

    /**
     * 停止消费循环.
     *
     * @return void
     */
    public function stop();

    /**
     * 关闭.
     *
     * @return void
     */
    public function close();
}
