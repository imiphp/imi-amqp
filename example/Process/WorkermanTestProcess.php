<?php

declare(strict_types=1);

namespace AMQPApp\Process;

use AMQPApp\AMQP\Test\TestConsumer;
use Imi\AMQP\Contract\IConsumer;
use Imi\Aop\Annotation\Inject;
use Imi\Log\Log;
use Imi\Workerman\Process\Annotation\Process;
use Imi\Workerman\Process\BaseProcess;
use Workerman\Worker;

#[Process(name: 'TestProcess1')]
class WorkermanTestProcess extends BaseProcess
{
    #[Inject(name: 'TestConsumer')]
    protected TestConsumer $testConsumer;

    public function run(Worker $process): void
    {
        $this->runConsumer($this->testConsumer);
    }

    private function runConsumer(IConsumer $consumer): void
    {
        try
        {
            $consumer->run();
        }
        catch (\Throwable $th)
        {
            Log::error($th);
            sleep(3);
            $this->runConsumer($consumer);
        }
    }
}
