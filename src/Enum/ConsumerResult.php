<?php

namespace Imi\AMQP\Enum;

use Imi\Enum\Annotation\EnumItem;
use Imi\Enum\BaseEnum;

/**
 * 消费者执行结果.
 */
abstract class ConsumerResult extends BaseEnum
{
    /**
     * 用于消息消费成功
     *
     * @EnumItem("确认消息")
     */
    const ACK = 1;

    /**
     * 用于消息消费失败.
     *
     * @EnumItem("否定消息")
     */
    const NACK = 2;

    /**
     * 用于消息消费失败，并重回队列.
     *
     * @EnumItem("否定消息，并重回队列")
     */
    const NACK_REQUEUE = 3;

    /**
     * @EnumItem("拒绝消息")
     */
    const REJECT = 4;

    /**
     * @EnumItem("拒绝消息，并重回队列")
     */
    const REJECT_REQUEUE = 5;
}
