<?php
namespace ImiApp\ApiServer\Controller;

use Imi\Redis\Redis;
use Imi\Aop\Annotation\Inject;
use ImiApp\AMQP\Test\TestMessage;
use Imi\Controller\HttpController;
use ImiApp\AMQP\Test2\TestMessage2;
use Imi\Server\Route\Annotation\Route;
use Imi\Server\Route\Annotation\Action;
use Imi\Server\Route\Annotation\Controller;

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
     * @return void
     */
    public function index()
    {
        return $this->response->write('');
    }

    /**
     * @Action
     *
     * @param integer $memberId
     * @return void
     */
    public function publish($memberId = 19260817)
    {
        $message = new TestMessage;
        $message->setMemberId($memberId);
        $r1 = $this->testPublisher->publish($message);
        
        $message2 = new TestMessage2;
        $message2->setMemberId($memberId);
        $message2->setContent('memberId:' . $memberId);
        $r2 = $this->testPublisher2->publish($message2);

        return [
            'r1'    =>  $r1,
            'r2'    =>  $r2,
        ];
    }

    /**
     * @Action
     *
     * @param integer $memberId
     * @return void
     */
    public function consume($memberId)
    {
        $r1 = Redis::get('imi-amqp:consume:1:' . $memberId);
        $r2 = Redis::get('imi-amqp:consume:2:' . $memberId);
        return [
            'r1'    =>  $r1,
            'r2'    =>  $r2,
        ];
    }

}
