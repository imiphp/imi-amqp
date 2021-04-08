<?php

namespace ImiApp\ApiServer\Controller;

use Imi\Aop\Annotation\Inject;
use Imi\Controller\HttpController;
use Imi\Redis\Redis;
use Imi\Server\Route\Annotation\Action;
use Imi\Server\Route\Annotation\Controller;
use Imi\Server\Route\Annotation\Route;
use ImiApp\AMQP\Test\TestMessage;
use ImiApp\AMQP\Test2\TestMessage2;

/**
 * @Controller("/")
 */
class IndexController extends HttpController
{
    /**
     * @Inject("TestPublisher")
     *
     * @var \ImiApp\AMQP\Test\TestPublisher
     */
    protected $testPublisher;

    /**
     * @Inject("TestPublisher2")
     *
     * @var \ImiApp\AMQP\Test2\TestPublisher2
     */
    protected $testPublisher2;

    /**
     * @Action
     * @Route("/")
     *
     * @return mixed
     */
    public function index()
    {
        return $this->response->write('');
    }

    /**
     * @Action
     *
     * @param int $memberId
     *
     * @return mixed
     */
    public function publish($memberId = 19260817)
    {
        $message = new TestMessage();
        $message->setMemberId($memberId);
        $r1 = $this->testPublisher->publish($message);

        $message2 = new TestMessage2();
        $message2->setMemberId($memberId);
        $message2->setContent('memberId:' . $memberId);
        $r2 = $this->testPublisher2->publish($message2);

        return [
            'r1'    => $r1,
            'r2'    => $r2,
        ];
    }

    /**
     * @Action
     *
     * @param int $memberId
     *
     * @return mixed
     */
    public function consume($memberId)
    {
        $r1 = Redis::get($key1 = 'imi-amqp:consume:1:' . $memberId);
        $r2 = Redis::get($key2 = 'imi-amqp:consume:2:' . $memberId);
        Redis::del($key1, $key2);

        return [
            'r1'    => $r1,
            'r2'    => $r2,
        ];
    }
}
