<?php
namespace ImiApp\Process;

use Imi\Process\BaseProcess;
use Imi\Aop\Annotation\Inject;
use Imi\Process\Annotation\Process;

/**
 * @Process(name="TestProcess")
 */
class TestProcess extends BaseProcess
{
    /**
     * @Inject("TestConsumer")
     *
     * @var \ImiApp\AMQP\Test\TestConsumer
     */
    protected $testConsumer;

    /**
     * @Inject("TestConsumer2")
     *
     * @var \ImiApp\AMQP\Test2\TestConsumer2
     */
    protected $testConsumer2;

    public function run(\Swoole\Process $process)
    {
        go(function(){
            do {
                $this->testConsumer->run();
            } while(true);
        });
        go(function(){
            do {
                $this->testConsumer2->run();
            } while(true);
        });
    }

}
