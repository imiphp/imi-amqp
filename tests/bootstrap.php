<?php

use Imi\App;
use Imi\Event\Event;
use Imi\Event\EventParam;
use Imi\Server\Event\Param\WorkerExitEventParam;
use Swoole\Coroutine;
use Swoole\Runtime;
use Yurun\Swoole\CoPool\CoPool;
use Yurun\Swoole\CoPool\Interfaces\ICoTask;
use Yurun\Swoole\CoPool\Interfaces\ITaskParam;

/**
 * @return bool
 */
function checkHttpServerStatus()
{
    $serverStarted = false;
    for ($i = 0; $i < 60; ++$i)
    {
        sleep(1);
        $context = stream_context_create(['http' => ['timeout' => 1]]);
        if ('' === @file_get_contents('http://127.0.0.1:8080/', false, $context))
        {
            $serverStarted = true;
            break;
        }
    }

    return $serverStarted;
}

/**
 * 开启服务器.
 *
 * @return void
 */
function startServer()
{
    $dirname = dirname(__DIR__);
    $servers = [
        'HttpServer'    => [
            'start'         => $dirname . '/example/bin/start-server.sh',
            'stop'          => $dirname . '/example/bin/stop-server.sh',
            'checkStatus'   => 'checkHttpServerStatus',
        ],
    ];

    $pool = new CoPool(swoole_cpu_num(), 16,
        // 定义任务匿名类，当然你也可以定义成普通类，传入完整类名
        // @phpstan-ignore-next-line
        new class() implements ICoTask {
            /**
             * 执行任务
             *
             * @param ITaskParam $param
             *
             * @return mixed
             */
            public function run(ITaskParam $param)
            {
                ($param->getData())();
                // 执行任务
                return true; // 返回任务执行结果，非必须
            }
        }
    );
    $pool->run();

    $taskCount = count($servers);
    $completeTaskCount = 0;
    foreach ($servers as $name => $options)
    {
        // 增加任务，异步回调
        $pool->addTaskAsync(function () use ($options, $name) {
            // start server
            $cmd = $options['start'];
            echo "Starting {$name}...", \PHP_EOL;
            `{$cmd}`;

            register_shutdown_function(function () use ($name, $options) {
                // stop server
                $cmd = $options['stop'];
                echo "Stoping {$name}...", \PHP_EOL;
                `{$cmd}`;
                echo "{$name} stoped!", \PHP_EOL, \PHP_EOL;
            });

            if (($options['checkStatus'])())
            {
                echo "{$name} started!", \PHP_EOL;
            }
            else
            {
                throw new \RuntimeException("{$name} start failed");
            }
        }, function (ITaskParam $param, $data) use (&$completeTaskCount) {
            // 异步回调
            ++$completeTaskCount;
        });
    }

    while ($completeTaskCount < $taskCount)
    {
        usleep(10000);
    }
    $pool->stop();
}

(function () {
    $redis = new \Redis();
    if (!$redis->connect(imiGetEnv('REDIS_SERVER_HOST', '127.0.0.1'), 6379))
    {
        exit('Redis connect failed');
    }
    $redis->del($redis->keys('imi-amqp:*'));
    $redis->close();
})();

startServer();

\Imi\Event\Event::on('IMI.INIT_TOOL', function (EventParam $param) {
    $data = $param->getData();
    $data['skip'] = true;
    \Imi\Tool\Tool::init();
});
\Imi\Event\Event::on('IMI.INITED', function (EventParam $param) {
    Runtime::enableCoroutine();
    App::initWorker();
    $param->stopPropagation();
}, 1);
App::run('ImiApp');

Coroutine::defer(function () {
    Event::trigger('IMI.MAIN_SERVER.WORKER.EXIT', [], null, WorkerExitEventParam::class);
});
