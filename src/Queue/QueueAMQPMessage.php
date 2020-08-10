<?php
namespace Imi\AMQP\Queue;

use Imi\Queue\Model\Message;

class QueueAMQPMessage extends Message
{
    /**
     * AMQP 消息
     *
     * @var \Imi\AMQP\Contract\IMessage
     */
    protected $amqpMessage;

    /**
     * Get aMQP 消息
     *
     * @return \Imi\AMQP\Contract\IMessage
     */ 
    public function getAmqpMessage()
    {
        return $this->amqpMessage;
    }

    /**
     * Set aMQP 消息
     *
     * @param \Imi\AMQP\Contract\IMessage $amqpMessage  AMQP 消息
     *
     * @return self
     */ 
    public function setAmqpMessage(\Imi\AMQP\Contract\IMessage $amqpMessage)
    {
        $this->amqpMessage = $amqpMessage;
        $this->loadFromArray($this->getAmqpMessage()->getBodyData());

        return $this;
    }

    // /**
    //  * 获取消息内容
    //  *
    //  * @return string
    //  */
    // public function getMessage(): string
    // {
    //     if(!$this->message)
    //     {
    //         $this->message = json_encode($this->amqpMessage->getBodyData()['data']);
    //     }
    //     return $this->message;
    // }

    // /**
    //  * 设置消息内容
    //  *
    //  * @param string $message
    //  * @return void
    //  */
    // public function setMessage(string $message)
    // {
    //     $this->message = $message;
    //     $this->amqpMessage->setBody($message);
    // }

    // /**
    //  * 将当前对象作为数组返回
    //  * @return array
    //  */
    // public function toArray(): array
    // {
    //     return [
    //         'messageId'     =>  $this->messageId,
    //         'retryCount'    =>  $this->retryCount,
    //         'maxRetryCount' =>  $this->maxRetryCount,
    //         'message'       =>  $this->getMessage(),
    //         'workingTimeout'=>  $this->workingTimeout,
    //     ];
    // }

    // /**
    //  * 从数组加载数据
    //  *
    //  * @param array $data
    //  * @return void
    //  */
    // public function loadFromArray(array $data)
    // {
    //     foreach($data as $k => $v)
    //     {
    //         if(!$this->amqpMessage && 'message' === $k)
    //         {
    //             $amqpMessage = new \Imi\AMQP\Message;
    //             $amqpMessage->setBody($v);
    //             $this->amqpMessage = $amqpMessage;
    //         }
    //         $this->$k = $v;
    //     }
    // }

}
